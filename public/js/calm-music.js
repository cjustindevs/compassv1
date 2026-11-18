// Calming music generated in the browser: a slow, soft four-chord pad with a few gentle
// pentatonic bell notes and a light echo. No audio files, so nothing to license or download.
(function () {
    const CHORD_SECONDS = 8;
    const freq = (midi) => 440 * Math.pow(2, (midi - 69) / 12);
    // Cmaj7 → Am7 → Fmaj7 → G6, voiced low and close.
    const CHORDS = [[48, 55, 64, 71], [45, 52, 60, 67], [41, 48, 57, 64], [43, 50, 59, 64]];
    // C major pentatonic, one octave above middle C.
    const MELODY = [72, 74, 76, 79, 81];
    const BEATS = [0.5, 2.5, 4.5, 6.5];

    function random(seed) {
        let s = (seed >>> 0) || 1;
        return () => (s = (Math.imul(s, 1664525) + 1013904223) >>> 0) / 4294967296;
    }

    // Notes for cycles [from, from + count): {kind: 'pad'|'bell', time (s from cycle 0), duration, freq}.
    function plan(from, count, seed) {
        const events = [];
        for (let cycle = from; cycle < from + count; cycle++) {
            const r = random((seed || 1) * 7919 + cycle);
            const t0 = cycle * CHORD_SECONDS;
            CHORDS[cycle % CHORDS.length].forEach((midi) => events.push({ kind: 'pad', time: t0, duration: CHORD_SECONDS + 2, freq: freq(midi) }));
            let bells = BEATS.filter(() => r() < 0.55);
            if (bells.length === 0) bells = [BEATS[Math.floor(r() * BEATS.length)]];
            bells.forEach((beat) => events.push({ kind: 'bell', time: t0 + beat, duration: 3, freq: freq(MELODY[Math.floor(r() * MELODY.length)]) }));
        }
        return events;
    }

    function createPlayer(AudioCtx) {
        let ctx = null, master = null, bus = null, timer = null, startAt = 0, next = 0, seed = 1;

        function voice(event) {
            const at = startAt + event.time;
            if (at + event.duration < ctx.currentTime) return;
            const osc = ctx.createOscillator();
            osc.type = event.kind === 'pad' ? 'triangle' : 'sine';
            osc.frequency.value = event.freq;
            const gain = ctx.createGain();
            const peak = event.kind === 'pad' ? 0.045 : 0.035;
            gain.gain.setValueAtTime(0.0001, at);
            gain.gain.exponentialRampToValueAtTime(peak, at + (event.kind === 'pad' ? 2.5 : 0.04));
            gain.gain.exponentialRampToValueAtTime(0.0001, at + event.duration);
            osc.connect(gain).connect(bus);
            osc.start(at);
            osc.stop(at + event.duration + 0.1);
        }

        function scheduleNext() { plan(next, 1, seed).forEach(voice); next += 1; }

        function start() {
            ctx = new AudioCtx();
            master = ctx.createGain(); master.gain.value = 0.0001;
            bus = ctx.createBiquadFilter(); bus.type = 'lowpass'; bus.frequency.value = 1800;
            const delay = ctx.createDelay(); delay.delayTime.value = 0.45;
            const feedback = ctx.createGain(); feedback.gain.value = 0.3;
            const wet = ctx.createGain(); wet.gain.value = 0.25;
            bus.connect(master);
            bus.connect(delay); delay.connect(feedback).connect(delay); delay.connect(wet).connect(master);
            master.connect(ctx.destination);
            master.gain.exponentialRampToValueAtTime(0.8, ctx.currentTime + 3);
            seed = Math.floor(Math.random() * 100000) + 1;
            startAt = ctx.currentTime + 0.1; next = 0;
            scheduleNext(); scheduleNext();
            timer = setInterval(scheduleNext, CHORD_SECONDS * 1000);
        }

        function stop() {
            if (!ctx) return;
            const c = ctx, m = master; ctx = null;
            clearInterval(timer); timer = null;
            m.gain.cancelScheduledValues(c.currentTime);
            m.gain.setValueAtTime(Math.max(m.gain.value, 0.0001), c.currentTime);
            m.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + 1.5);
            setTimeout(() => c.close(), 1600);
        }

        return { start, stop, get playing() { return ctx !== null; } };
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { plan, CHORD_SECONDS };
    if (typeof window !== 'undefined') window.CompassCalmMusic = { plan, createPlayer, CHORD_SECONDS };
})();
