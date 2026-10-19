/* Public pickup queue. First successful feed seeds alerts without replaying old calls. */
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
    const connectionText = board.querySelector('[data-queue-status]');
    const retry = board.querySelector('[data-queue-retry]');
    const live = board.querySelector('[data-queue-announcement]');
    const enable = board.querySelector('[data-queue-unlock]');
    const fullscreen = board.querySelector('[data-queue-fullscreen]');
    const clock = board.querySelector('[data-queue-clock]');
    const POLL_INTERVAL = 6000;
    const MAX_RETRY = 60000;
    let known = null;
    let timer = null;
    let activeRequest = null;
    let backoff = POLL_INTERVAL;
    let audioEnabled = false;
    let pendingImmediateRetry = false;

    function renderNumbers(target, values) {
        target.replaceChildren();
        if (!values.length) {
            const placeholder = document.createElement('p');
            placeholder.className = 'queue-empty';
            placeholder.textContent = 'No orders yet';
            target.append(placeholder);
            return;
        }
        values.forEach((value) => {
            const tile = document.createElement('span');
            tile.className = 'queue-number';
            tile.textContent = String(value);
            target.append(tile);
        });
    }

    function schedule(ms) {
        root.clearTimeout(timer);
        timer = null;
        if (!document.hidden) timer = root.setTimeout(poll, ms);
    }

    function setConnection(message) {
        connectionText.textContent = message;
        connection.hidden = !message;
    }

    async function poll() {
        if (document.hidden || activeRequest) return;
        if (root.navigator?.onLine === false) {
            setConnection('Offline — waiting for network');
            schedule(Math.min(backoff, 15000));
            return;
        }
        const controller = new AbortController();
        activeRequest = controller;
        const timeout = root.setTimeout(() => controller.abort(), 10000);
        retry.disabled = true;
        try {
            const response = await root.fetch(feedUrl, {
                cache: 'no-store', credentials: 'same-origin',
                headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if (!response.ok) {
                const error = new Error('Queue feed unavailable');
                error.status = response.status;
                const retryAfter = Number.parseInt(response.headers.get('Retry-After'), 10);
                error.retryAfter = Number.isFinite(retryAfter) ? retryAfter * 1000 : 0;
                throw error;
            }
            const data = await response.json();
            if (!Array.isArray(data.preparing) || !Array.isArray(data.ready) ||
                !data.ready.every((r) => r && typeof r.number === 'string' && Number.isFinite(Number(r.calls)))) {
                throw new Error('Invalid queue feed');
            }

            const fresh = computeNewlyReady(known || new Set(data.ready.map((r) => `${r.number}:${r.calls}`)), data.ready);
            const newlyReady = known === null ? [] : fresh.newlyReady;
            known = fresh.keys;
            renderNumbers(preparing, data.preparing);
            renderNumbers(ready, data.ready.map((r) => r.number));
            calling.textContent = data.ready.length ? data.ready[0].number : '—';

            if (newlyReady.length) {
                // The feed sorts by the most recent call; don't announce every stale call after an outage.
                const newest = newlyReady[0];
                calling.textContent = newest.number;
                calling.classList.remove('queue-flash');
                void calling.offsetWidth;
                calling.classList.add('queue-flash');
                live.textContent = `Order number ${newest.number}, ready for pickup.`;
                if (audioEnabled && data.sound) root.CafeteriaChime?.play();
                if (audioEnabled && data.voice) {
                    const spokenNumber = Number.parseInt(newest.number.match(/\d+$/)?.[0] || '', 10);
                    root.setTimeout(() => root.CafeteriaChime?.announce(
                        `Order number ${Number.isFinite(spokenNumber) ? spokenNumber : newest.number}, ready for pickup.`
                    ), data.sound ? 900 : 0);
                }
            }
            backoff = POLL_INTERVAL;
            setConnection('');
        } catch (error) {
            if (controller.signal.aborted && document.hidden) return;
            const limited = error.status === 429;
            backoff = limited ? Math.max(30000, error.retryAfter || 0) : Math.min(MAX_RETRY, Math.max(POLL_INTERVAL * 2, backoff * 2));
            backoff = Math.min(MAX_RETRY, backoff);
            setConnection(limited ? 'Updates limited — retrying soon' :
                root.navigator?.onLine === false ? 'Offline — waiting for network' :
                    error.status === 404 ? 'Queue feed unavailable — retrying' : 'Reconnecting to queue…');
        } finally {
            root.clearTimeout(timeout);
            if (activeRequest === controller) activeRequest = null;
            retry.disabled = false;
            if (pendingImmediateRetry && !document.hidden) {
                pendingImmediateRetry = false;
                schedule(0);
            } else schedule(backoff);
        }
    }

    function refreshNow() {
        root.clearTimeout(timer);
        timer = null;
        if (activeRequest) {
            activeRequest.abort();
            // Do not overlap polls: run again when the cancelled request settles.
            pendingImmediateRetry = true;
            return;
        }
        poll();
    }

    retry?.addEventListener('click', refreshNow);
    root.addEventListener('online', refreshNow);
    root.addEventListener('offline', () => {
        setConnection('Offline — waiting for network');
        if (activeRequest) activeRequest.abort();
    });
    document.addEventListener('visibilitychange', () => {
        root.clearTimeout(timer);
        timer = null;
        if (document.hidden) activeRequest?.abort();
        else refreshNow();
    });
    enable?.addEventListener('click', async () => {
        audioEnabled = await root.CafeteriaChime?.unlock() === true;
        if (audioEnabled) { enable.hidden = true; root.CafeteriaChime.play(); }
        else enable.textContent = 'Sound is unavailable. Tap to try again';
    });
    fullscreen?.addEventListener('click', () => {
        if (document.fullscreenElement) document.exitFullscreen?.();
        else document.documentElement.requestFullscreen?.();
    });
    function updateClock() {
        clock.textContent = new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
        }).format(new Date());
    }
    updateClock();
    root.setInterval(updateClock, 1000);
    poll();
})(typeof window === 'undefined' ? {} : window);
