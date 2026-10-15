(() => {
    const clock = document.querySelector('[data-readiness-retry-at]');
    if (!clock) return;
    const end = Number(clock.dataset.readinessRetryAt);
    if (!Number.isFinite(end)) return;
    function update() {
        const minutes = Math.max(0, Math.ceil((end - Date.now()) / 60000));
        const output = document.querySelector('[data-readiness-minutes]');
        if (output) output.textContent = minutes;
        if (!minutes) {
            const note = document.querySelector('[data-readiness-unlocked]');
            if (note) note.hidden = false;
        }
    }
    update(); setInterval(update, 1000);
})();
