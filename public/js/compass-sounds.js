/*
 * COMPASS sounds: a soft two-note chime for new messages and notifications,
 * a gentle ringtone for an incoming voice call, and a quiet ringback for the
 * caller. Generated with Web Audio, so no sound files are downloaded.
 *
 * Browsers only allow sound after the person has clicked or typed on the
 * page, so the first interaction unlocks audio; before that, sounds are
 * skipped silently. Usage: window.compassSounds.chime(),
 * window.compassSounds.ring('incoming' | 'ringback'), window.compassSounds.stopRing().
 */
(function () {
    if (window.compassSounds) return;
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let ctx = null;
    let unlocked = false;
    let lastChime = 0;
    let ringTimer = null;
    let ringKind = null;

    const context = () => {
        if (!AudioCtx) return null;
        if (!ctx) {
            try { ctx = new AudioCtx(); } catch (e) { return null; }
        }
        return ctx;
    };
    const unlock = () => {
        const audio = context();
        if (!audio) return;
        if (audio.state === 'suspended') audio.resume().catch(() => {});
        unlocked = true;
    };
    ['pointerdown', 'keydown', 'touchstart'].forEach((type) => window.addEventListener(type, unlock, { capture: true, passive: true }));

    const tone = (freq, start, duration, volume, type) => {
        const audio = context();
        if (!audio || audio.state !== 'running') return;
        const osc = audio.createOscillator();
        const gain = audio.createGain();
        const at = audio.currentTime + start;
        osc.type = type || 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0, at);
        gain.gain.linearRampToValueAtTime(volume, at + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.001, at + duration);
        osc.connect(gain).connect(audio.destination);
        osc.start(at);
        osc.stop(at + duration + 0.05);
    };

    const chime = () => {
        const now = Date.now();
        if (!unlocked || now - lastChime < 1500) return;
        lastChime = now;
        tone(880, 0, 0.25, 0.14);
        tone(1318.5, 0.12, 0.4, 0.14);
    };

    const patterns = {
        // Incoming call: a short four-note phrase every 1.6 s.
        incoming: { every: 1600, play: () => [659.3, 784, 987.8, 784].forEach((f, i) => tone(f, i * 0.16, 0.35, 0.13, 'triangle')) },
        // Caller waiting: one soft double tone every 3 s.
        ringback: { every: 3000, play: () => { tone(440, 0, 0.9, 0.05); tone(480, 0, 0.9, 0.05); } },
    };

    const stopRing = () => {
        clearInterval(ringTimer);
        ringTimer = null;
        ringKind = null;
    };
    const ring = (kind) => {
        const pattern = patterns[kind];
        if (!pattern) return stopRing();
        if (ringKind === kind) return;
        stopRing();
        ringKind = kind;
        pattern.play();
        ringTimer = setInterval(pattern.play, pattern.every);
        // Never ring forever: calls time out long before this.
        setTimeout(() => { if (ringKind === kind) stopRing(); }, 60000);
    };

    window.addEventListener('pagehide', stopRing);
    window.compassSounds = { chime, ring, stopRing };
})();
