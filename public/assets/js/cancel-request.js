(() => {
    'use strict';
    document.querySelectorAll('[data-cancel-request-card]').forEach(card => {
        const clock = card.querySelector('[data-cancel-countdown]');
        const form = card.querySelector('[data-cancel-request-form]');
        const expired = card.querySelector('[data-cancel-expired]');
        if (!clock || !form) return;
        let seconds = Math.max(0, Number.parseInt(clock.dataset.secondsLeft, 10) || 0);
        const tick = () => {
            clock.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
            if (seconds === 0) {
                form.hidden = true;
                if (expired) expired.hidden = false;
                clearInterval(timer);
            } else {
                seconds--;
            }
        };
        const timer = setInterval(tick, 1000);
        tick();
    });
})();
