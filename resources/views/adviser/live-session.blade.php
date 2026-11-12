@extends('layouts.app')
@section('title', 'Watching live session - COMPASS')
@section('content')
<div class="adviser-page-content lw-page" data-live-feed="{{ route('adviser.live.feed', $session->id) }}" data-live-notes="{{ route('adviser.live.notes', $session->id) }}">
    @php($risk = $session->risk_level ?? 'low')
    <x-ui.page-header title="Live session" description="Read-only. The Seeker and Helper cannot see that you are watching; this view is recorded in the audit log under supervision consent." :back-href="route('adviser.live.index')" back-label="Back to Live sessions" />

    <x-ui.card>
        <dl class="ui-summary">
            <div><dt>Status</dt><dd><x-ui.badge tone="red" class="lw-live" data-live-badge>Live</x-ui.badge></dd></div>
            <div><dt>Helper</dt><dd>{{ $session->helper?->full_name ?? 'Helper' }}</dd></div>
            <div><dt>Seeker</dt><dd>{{ $session->seeker?->generated_alias ?? 'Seeker' }}</dd></div>
            <div><dt>Concern</dt><dd>{{ $session->concern?->concern_name ?? 'General concern' }}</dd></div>
            <div><dt>Risk</dt><dd><x-ui.badge :tone="in_array($risk, ['high', 'emergency']) ? 'red' : ($risk === 'moderate' ? 'amber' : 'neutral')">{{ ucfirst($risk) }} risk</x-ui.badge></dd></div>
            <div><dt>Started</dt><dd>{{ $session->start_time?->copy()->timezone('Asia/Manila')->format('g:i A') }} PHT</dd></div>
        </dl>
    </x-ui.card>

    <div class="lw-grid">
        <section class="ui-card" aria-labelledby="lwTranscript">
            <h2 class="ui-card-title" id="lwTranscript">Conversation</h2>
            <div class="lw-messages" data-live-messages aria-live="polite">
                <p class="lw-empty" data-live-empty>Loading messages…</p>
            </div>
        </section>
        <aside class="ui-card lw-coach" aria-labelledby="lwCoach">
            <h2 class="ui-card-title" id="lwCoach">Coach the Helper privately</h2>
            <p class="ui-card-sub">Only {{ $session->helper?->first_name ?? 'the Helper' }} sees these notes, in a panel beside their chat. Keep them short and practical.</p>
            <form data-live-note-form>
                @csrf
                <label for="lwNote" class="sr-only">Coach note</label>
                <textarea id="lwNote" name="note" rows="3" maxlength="600" required placeholder="For example: Reflect their feelings back before suggesting steps."></textarea>
                <button type="submit" class="btn-primary">Send to Helper</button>
                <p class="lw-error" data-live-note-error role="alert" hidden></p>
            </form>
            <h3>Notes sent</h3>
            <ul class="lw-notes" data-live-notes-list><li class="lw-empty" data-live-notes-empty>No notes yet.</li></ul>
        </aside>
    </div>
</div>
<style>
.lw-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
.lw-grid .ui-card { margin: 0; }
.lw-live.is-ended { background: #F3F4F6; color: #4B5563; }
.lw-messages { height: min(62vh, 640px); overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding: 8px; background: #F9FAFB; border-radius: 12px; }
.lw-msg { max-width: 75%; padding: 8px 12px; border-radius: 12px; font-size: 14px; line-height: 1.5; overflow-wrap: anywhere; }
.lw-msg small { display: block; font-size: 11px; opacity: .75; margin-bottom: 2px; }
.lw-msg.from-seeker { align-self: flex-start; background: #fff; border: 1px solid #E5E7EB; color: #1F2937; }
.lw-msg.from-helper { align-self: flex-end; background: #04A052; color: #fff; }
.lw-empty { color: #6B7280; font-size: 13px; margin: auto; text-align: center; }
.lw-coach { position: sticky; top: calc(12px + env(safe-area-inset-top, 0px)); }
.lw-coach form { display: grid; gap: 8px; margin: 12px 0; }
.lw-coach textarea { border: 1px solid #D1D5DB; border-radius: 12px; padding: 10px 12px; font: inherit; font-size: 16px; resize: vertical; }
.lw-coach button:disabled { opacity: .6; cursor: default; }
.lw-coach h3 { margin: 0 0 8px; font-size: 15px; font-weight: 700; }
.lw-error { margin: 0; font-size: 13px; color: #DC2626; }
.lw-notes { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; max-height: 280px; overflow-y: auto; }
.lw-notes li { font-size: 14px; background: #F9FAFB; border-radius: 12px; padding: 8px 12px; color: #1F2937; overflow-wrap: anywhere; }
.lw-notes li small { display: block; font-size: 12px; color: #6B7280; }
@media (max-width: 1000px) { .lw-grid { grid-template-columns: 1fr; } .lw-coach { position: static; } }
@media (max-width: 700px) { .lw-msg { max-width: 90%; } }
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
