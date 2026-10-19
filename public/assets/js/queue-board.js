/* Public board: pure alert diff; first successful response is silent. */
(function (root) {
    'use strict';
    function computeNewlyReady(previousKeys, readyList) {
        const next = new Set(readyList.map((entry) => `${entry.number}:${entry.calls}`));
        const newlyReady = readyList.filter((entry) => !previousKeys.has(`${entry.number}:${entry.calls}`));
        return { keys: next, newlyReady };
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { computeNewlyReady };
    if (!root.document) return;
    const document = root.document;
    const board = document.querySelector('[data-queue-board]');
    if (!board) return;
    const feedUrl = board.dataset.feedUrl;
    const preparing = board.querySelector('[data-queue-preparing]');
    const ready = board.querySelector('[data-queue-ready]');
    const calling = board.querySelector('[data-queue-calling]');
    const connection = board.querySelector('[data-queue-connection]');
    const live = board.querySelector('[data-queue-announcement]');
    const enable = board.querySelector('[data-queue-unlock]');
    const fullscreen = board.querySelector('[data-queue-fullscreen]');
    const clock = board.querySelector('[data-queue-clock]');
    let known = null;
    let timer = null;
    let backoff = 4000;
    let audioEnabled = false;
    const renderNumbers = (target, values) => {
        target.replaceChildren();
        if (values.length === 0) {
            const placeholder = document.createElement('p');
            placeholder.className = 'queue-empty';
            placeholder.textContent = 'No orders yet';
            target.append(placeholder);
        }
        values.forEach((value) => {
            const tile = document.createElement('span');
            tile.className = 'queue-number';
            tile.textContent = String(value);
            target.append(tile);
        });
    };
    function schedule(ms) { clearTimeout(timer); if (!document.hidden) timer = setTimeout(poll, ms); }
    async function poll() {
        if (document.hidden) return;
        try {
            const response = await fetch(feedUrl, { cache: 'no-store', credentials: 'same-origin' });
            if (!response.ok) throw new Error('Unable to refresh queue');
            const data = await response.json();
            const fresh = computeNewlyReady(known || new Set(data.ready.map((r) => `${r.number}:${r.calls}`)), data.ready);
            const newlyReady = known === null ? [] : fresh.newlyReady;
            known = fresh.keys;
            renderNumbers(preparing, data.preparing);
            renderNumbers(ready, data.ready.map((r) => r.number));
            if (data.ready.length) calling.textContent = data.ready[0].number;
            else calling.textContent = '—';
            if (newlyReady.length) {
                const newest = data.ready[0];
                calling.textContent = newest.number;
                calling.classList.remove('queue-flash');
                void calling.offsetWidth;
                calling.classList.add('queue-flash');
                live.textContent = newlyReady.map((r) => `Order number ${r.number}, ready for pickup.`).join(' ');
                for (const entry of newlyReady) {
                    if (audioEnabled && data.sound) root.CafeteriaChime?.play();
                    if (audioEnabled && data.voice) root.setTimeout(() => root.CafeteriaChime?.announce(`Order number ${Number.parseInt(entry.number.match(/\d+$/)?.[0] || entry.number, 10) || entry.number}, ready for pickup.`), data.sound ? 900 : 0);
                }
            }
            backoff = 4000;
            connection.hidden = true;
        } catch (_) {
            connection.hidden = false;
            backoff = Math.min(30000, backoff * 2);
        }
        schedule(backoff);
    }
    enable?.addEventListener('click', async () => {
        audioEnabled = await root.CafeteriaChime?.unlock() === true;
        if (audioEnabled) { enable.hidden = true; root.CafeteriaChime.play(); }
        else enable.textContent = 'Sound is unavailable. Tap to try again';
    });
    fullscreen?.addEventListener('click', () => {
        if (document.fullscreenElement) document.exitFullscreen?.();
        else document.documentElement.requestFullscreen?.();
    });
    const updateClock = () => { clock.textContent = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).format(new Date()); };
    updateClock();
    setInterval(updateClock, 1000);
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (!document.hidden) poll();
    });
    poll();
})(typeof window === 'undefined' ? {} : window);
