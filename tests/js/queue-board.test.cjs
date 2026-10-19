'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/js/queue-board.js', 'utf8');
const context = {module: {exports: {}}, window: {}};
vm.runInNewContext(source, context);
const {computeNewlyReady} = context.module.exports;
const r = (number, calls) => ({number, calls, called_at: '2026-10-20T12:00:00+08:00'});
let old = new Set(['042:1']);
let result = computeNewlyReady(old, [r('042', 1)]);
assert.equal(result.newlyReady.length, 0, 'The first feed must not replay old calls.');
result = computeNewlyReady(result.keys, [r('042', 1), r('043', 1)]);
assert.equal(result.newlyReady[0].number, '043');
result = computeNewlyReady(result.keys, [r('042', 2), r('043', 1)]);
assert.equal(result.newlyReady[0].calls, 2, 'A re-call must retrigger.');

function createBoard(responses) {
    const timers = new Map();
    const nodes = {};
    let timerId = 0;
    const events = {};
    function element() {
        return {
            hidden: false, textContent: '', children: [], listeners: {},
            classList: {add() {}, remove() {}},
            replaceChildren(...children) { this.children = children; },
            append(child) { this.children.push(child); },
            addEventListener(type, callback) { this.listeners[type] = callback; },
        };
    }
    for (const name of ['preparing', 'ready', 'calling', 'connection', 'status', 'retry',
        'announcement', 'unlock', 'fullscreen', 'clock']) {
        nodes[name] = element();
    }
    const document = {
        hidden: false,
        querySelector(selector) {
            if (selector === '[data-queue-board]') {
                return {dataset: {feedUrl: '/queue-board/feed'}, querySelector: inner => nodes[inner.match(/data-queue-(\w+)/)?.[1]]};
            }
            return null;
        },
        createElement: element,
        addEventListener(name, callback) { events[name] = callback; },
    };
    const window = {
        document, navigator: {onLine: true},
        fetch: async () => {
            assert.ok(responses.length > 0, 'No more mock responses');
            const item = responses.shift();
            return {
                ok: item.status === 200,
                status: item.status,
                headers: {get(name) { return name === 'Retry-After' ? item.retryAfter || null :
                    name === 'Content-Type' ? item.type || 'application/json' : null; }},
                json: async () => item.body,
            };
        },
        setTimeout(callback, ms) { const id = ++timerId; timers.set(id, {callback, ms}); return id; },
        clearTimeout(id) { timers.delete(id); },
        setInterval() { return 0; },
        addEventListener(name, callback) { events[name] = callback; },
        CafeteriaChime: {play() {}},
    };
    vm.runInNewContext(source, {window, AbortController, Intl, Date, Set, Number, String, Error});
    return {nodes, timers, events, responses};
}
const settle = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const board = createBoard([
        {status: 503, retryAfter: '300', body: {code: 'queue_setup_required'}},
        {status: 200, body: {preparing: ['001'], ready: [{number: '042', calls: 1}], sound: false, voice: false}},
    ]);
    await settle();
    assert.match(board.nodes.status.textContent, /setup required/i);
    assert.equal(board.nodes.connection.hidden, false);
    assert.equal(board.nodes.preparing.children[0].textContent, 'Queue updates unavailable');
    assert.ok([...board.timers.values()].some(timer => timer.ms === 300000), 'Setup errors should back off.');
    board.nodes.retry.listeners.click();
    await settle();
    assert.equal(board.nodes.connection.hidden, true, 'Recovery clears the error banner.');
    assert.equal(board.nodes.preparing.children[0].textContent, '001');
    assert.equal(board.nodes.ready.children[0].textContent, '042');
    assert.equal(board.nodes.calling.textContent, '042');
    assert.ok([...board.timers.values()].some(timer => timer.ms === 6000), 'Recovery restores normal polling.');

    const failing = createBoard([
        {status: 500, type: 'text/html', body: null},
        {status: 500, type: 'text/html', body: null},
        {status: 200, body: {preparing: [], ready: [], sound: false, voice: false}},
    ]);
    await settle();
    assert.match(failing.nodes.status.textContent, /server unavailable/i);
    assert.ok([...failing.timers.values()].some(timer => timer.ms === 60000));
    failing.nodes.retry.listeners.click();
    await settle();
    assert.ok([...failing.timers.values()].some(timer => timer.ms === 120000), 'Repeated 500s slow polling.');
    failing.nodes.retry.listeners.click();
    await settle();
    assert.equal(failing.nodes.preparing.children[0].textContent, 'No orders yet');
    assert.equal(failing.nodes.connection.hidden, true);

    const limited = createBoard([{status: 429, retryAfter: '90', body: {code: 'rate_limited'}}]);
    await settle();
    assert.match(limited.nodes.status.textContent, /limited/i);
    assert.ok([...limited.timers.values()].some(timer => timer.ms === 90000), 'Honors Retry-After.');
    console.log('Queue board connection, 500/503/429 backoff, recovery and privacy tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
