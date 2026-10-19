'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/js/sidebar-scroll.js', 'utf8');

function makeElement() {
    const listeners = new Map();
    return {
        scrollTop: 0,
        listeners,
        addEventListener(name, fn) { listeners.set(name, fn); },
        querySelectorAll() { return [makeElement()]; },
        trigger(name, event = {}) { listeners.get(name)?.(event); },
    };
}
const state = new Map();
function page() {
    const desktop = makeElement();
    const mobile = makeElement();
    const offcanvas = makeElement();
    const window = {addEventListener() {}};
    const document = {
        body: {dataset: {sidebarRole: 'admin'}},
        querySelector(selector) {
            if (selector === 'meta[name="app-base-url"]') return {content: 'https://example.com/cafeteria/'};
            if (selector === '[data-persistent-sidebar]') return desktop;
            if (selector === '[data-mobile-sidebar-scroll]') return mobile;
            return null;
        },
        getElementById() {return offcanvas;},
    };
    const sessionStorage = {
        getItem(key) {return state.get(key) ?? null;},
        setItem(key, value) {state.set(key, value);},
    };
    vm.runInNewContext(source, {window, document, sessionStorage, location: {origin: 'https://example.com'}});
    return {desktop, mobile, offcanvas};
}
const first = page();
first.desktop.scrollTop = 496;
first.desktop.trigger('scroll');
first.mobile.scrollTop = 218;
first.mobile.trigger('scroll');
const next = page();
assert.equal(next.desktop.scrollTop, 496, 'Desktop nav should resume at its previous position.');
next.offcanvas.trigger('shown.bs.offcanvas');
assert.equal(next.mobile.scrollTop, 218, 'Mobile nav should resume when reopened.');
console.log('Sidebar scroll persistence tests passed.');
