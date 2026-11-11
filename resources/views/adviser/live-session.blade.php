@extends('layouts.app')
@section('title', 'Watching live session - COMPASS')
@section('content')
<div class="adviser-page-content lw-page" data-live-feed="{{ route('adviser.live.feed', $session->id) }}" data-live-notes="{{ route('adviser.live.notes', $session->id) }}">
    <a class="lw-back" href="{{ route('adviser.live.index') }}">&larr; All live sessions</a>
    <header class="lw-head">
        <div>
            <h1>{{ $session->helper?->full_name ?? 'Helper' }} <span class="lw-with">with {{ $session->seeker?->generated_alias ?? 'Seeker' }}</span></h1>
            <p><span class="lw-live" data-live-badge>Live</span> {{ $session->concern?->concern_name ?? 'General concern' }} · {{ ucfirst($session->risk_level ?? 'low') }} risk · started {{ $session->start_time?->copy()->timezone('Asia/Manila')->format('g:i A') }} PHT</p>
        </div>
        <p class="lw-privacy">Read-only. The Seeker and Helper cannot see that you are watching; this view is recorded in the audit log under supervision consent.</p>
    </header>

    <div class="lw-grid">
        <section class="lw-card" aria-labelledby="lwTranscript">
            <h2 id="lwTranscript">Conversation</h2>
            <div class="lw-messages" data-live-messages aria-live="polite">
                <p class="lw-empty" data-live-empty>Loading messages…</p>
            </div>
        </section>
        <aside class="lw-card lw-coach" aria-labelledby="lwCoach">
            <h2 id="lwCoach">Coach the Helper privately</h2>
            <p class="lw-sub">Only {{ $session->helper?->first_name ?? 'the Helper' }} sees these notes, in a panel beside their chat. Keep them short and practical.</p>
            <form data-live-note-form>
                @csrf
                <label for="lwNote" class="sr-only">Coach note</label>
                <textarea id="lwNote" name="note" rows="3" maxlength="600" required placeholder="For example: Reflect their feelings back before suggesting steps."></textarea>
                <button type="submit">Send to Helper</button>
                <p class="lw-error" data-live-note-error role="alert" hidden></p>
            </form>
            <h3>Notes sent</h3>
            <ul class="lw-notes" data-live-notes-list><li class="lw-empty" data-live-notes-empty>No notes yet.</li></ul>
        </aside>
    </div>
</div>
<style>
.lw-page { padding: 20px 28px 32px; display: grid; gap: 12px; }
.lw-back { font-size: 13px; font-weight: 600; color: #047a3e; text-decoration: none; }
.lw-head { display: flex; justify-content: space-between; gap: 12px 24px; flex-wrap: wrap; align-items: flex-end; }
.lw-head h1 { margin: 0; font-size: 24px; font-weight: 800; color: #163b2d; }
.lw-with { font-weight: 500; color: #64746b; font-size: 16px; }
.lw-head p { margin: 4px 0 0; font-size: 13px; color: #64746b; }
.lw-live { display: inline-block; font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #fff; background: #d03b3b; border-radius: 99px; padding: 2px 9px; margin-right: 6px; }
.lw-live.is-ended { background: #94a39a; }
.lw-privacy { max-width: 46ch; font-size: 12px !important; background: #f6faf7; border: 1px solid #dfe9e3; border-radius: 10px; padding: 8px 12px; }
.lw-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 14px; align-items: start; }
.lw-card { background: #fff; border: 1px solid #dfe9e3; border-radius: 14px; padding: 14px 16px; display: grid; gap: 10px; }
.lw-card h2 { margin: 0; font-size: 14px; font-weight: 700; color: #163b2d; }
.lw-card h3 { margin: 6px 0 0; font-size: 13px; font-weight: 700; color: #163b2d; }
.lw-messages { height: min(62vh, 640px); overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding: 4px; background: #f8fbf9; border-radius: 10px; }
.lw-msg { max-width: 75%; padding: 8px 12px; border-radius: 12px; font-size: 14px; line-height: 1.45; }
.lw-msg small { display: block; font-size: 11px; opacity: .75; margin-bottom: 2px; }
.lw-msg.from-seeker { align-self: flex-start; background: #fff; border: 1px solid #e2e8e4; color: #26443a; }
.lw-msg.from-helper { align-self: flex-end; background: #078749; color: #fff; }
.lw-empty { color: #64746b; font-size: 13px; margin: auto; text-align: center; }
.lw-coach { position: sticky; top: calc(12px + env(safe-area-inset-top, 0px)); }
.lw-sub { margin: 0; font-size: 13px; color: #64746b; }
.lw-coach form { display: grid; gap: 8px; }
.lw-coach textarea { border: 1px solid #cbd5cf; border-radius: 10px; padding: 9px 11px; font: inherit; resize: vertical; }
.lw-coach button { min-height: 40px; border: 0; border-radius: 10px; background: #078749; color: #fff; font-weight: 700; cursor: pointer; }
.lw-coach button:disabled { opacity: .6; cursor: default; }
.lw-error { margin: 0; font-size: 12px; color: #a32626; }
.lw-notes { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; max-height: 280px; overflow-y: auto; }
.lw-notes li { font-size: 13px; background: #f6faf7; border-radius: 10px; padding: 8px 10px; color: #26443a; }
.lw-notes li small { display: block; font-size: 11px; color: #64746b; }
@media (max-width: 1000px) { .lw-grid { grid-template-columns: 1fr; } .lw-coach { position: static; } }
@media (max-width: 700px) { .lw-page { padding: 16px; } .lw-msg { max-width: 90%; } }
</style>
<script>
(() => {
    const root = document.querySelector('[data-live-feed]');
    if (!root) return;
    const box = root.querySelector('[data-live-messages]');
    const empty = root.querySelector('[data-live-empty]');
    const notesList = root.querySelector('[data-live-notes-list]');
    const notesEmpty = root.querySelector('[data-live-notes-empty]');
    const badge = root.querySelector('[data-live-badge]');
    const form = root.querySelector('[data-live-note-form]');
    const error = root.querySelector('[data-live-note-error]');
    const token = form.querySelector('input[name="_token"]').value;
    let after = 0, notesAfter = 0, stopped = false, timer = null;
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; };
    const addNote = (n) => { notesEmpty.hidden = true; const li = el('li'); li.append(el('small', '', n.time + (n.read ? ' · seen' : '')), document.createTextNode(n.note)); notesList.prepend(li); notesAfter = Math.max(notesAfter, n.id); };
    async function poll() {
        if (stopped || document.hidden) return;
        try {
            const res = await fetch(root.dataset.liveFeed + '?after=' + after + '&notes_after=' + notesAfter, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
            data.messages.forEach((m) => {
                const msg = el('div', 'lw-msg from-' + m.from);
                msg.append(el('small', '', m.name + ' · ' + m.time), document.createTextNode(m.text));
                box.append(msg); after = Math.max(after, m.id);
            });
            if (after > 0) empty.hidden = true; else empty.textContent = 'No messages yet.';
            data.notes.forEach(addNote);
            if (nearBottom) box.scrollTop = box.scrollHeight;
            if (!data.live) {
                stopped = true; clearInterval(timer);
                badge.textContent = 'Ended'; badge.classList.add('is-ended');
                form.querySelector('button').disabled = true;
                const end = el('p', 'lw-empty', 'This session has ended. Review it from Pending Evaluations once the Helper submits documentation.');
                box.append(end);
            }
        } catch (_) { /* keep the last view during a brief network drop */ }
    }
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const button = form.querySelector('button');
        const note = form.note.value.trim();
        if (!note) return;
        button.disabled = true; error.hidden = true;
        try {
            const res = await fetch(root.dataset.liveNotes, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ note }) });
            if (!res.ok) throw new Error(res.status === 404 ? 'The session is no longer live.' : 'The note could not be sent. Try again.');
            const data = await res.json();
            addNote(data.note); form.reset();
        } catch (err) { error.textContent = err.message; error.hidden = false; }
        finally { if (!stopped) button.disabled = false; }
    });
    poll();
    timer = setInterval(poll, 4000);
    document.addEventListener('visibilitychange', poll);
})();
</script>
@endsection
