@extends('layouts.app')
@section('title', 'Watching live session - COMPASS')
@section('content')
<div class="adviser-page-content lw-page" data-live-feed="{{ route('adviser.live.feed', $session->id) }}" data-live-notes="{{ route('adviser.live.notes', $session->id) }}">
    @php($risk = $session->risk_level ?? 'low')
    <x-ui.page-header title="Live session" description="Read-only supervision, recorded in the audit log. Reading the chat is not shown to the participants; when you listen to a call, they see that an Adviser is listening." :back-href="route('adviser.live.index')" back-label="Back to Live sessions" />

    <x-ui.card>
        <dl class="ui-summary">
            <div><dt>Status</dt><dd><x-ui.badge tone="red" class="lw-live" data-live-badge>Live</x-ui.badge></dd></div>
            <div><dt>Helper</dt><dd>{{ $session->helper?->full_name ?? 'Helper' }}</dd></div>
            <div><dt>Seeker</dt><dd>{{ $session->seeker?->generated_alias ?? 'Seeker' }}</dd></div>
            <div><dt>Concern</dt><dd>{{ $session->concern_label ?? 'General concern' }}</dd></div>
            <div><dt>Risk</dt><dd><x-ui.badge :tone="in_array($risk, ['high', 'emergency']) ? 'red' : ($risk === 'moderate' ? 'amber' : 'neutral')">{{ ucfirst($risk) }} risk</x-ui.badge></dd></div>
            <div><dt>Started</dt><dd>{{ $session->start_time?->copy()->timezone('Asia/Manila')->format('g:i A') }} PHT</dd></div>
        </dl>
    </x-ui.card>

    {{-- Live voice supervision: listen receive-only (your microphone is never used; nothing is recorded). --}}
    <section class="ui-card lw-listen" data-voice-listen="{{ route('adviser.live.listen.start', $session->id) }}" data-listen-state="idle" aria-labelledby="lwListenTitle">
        <div class="lw-listen-copy">
            <span class="lw-listen-icon" aria-hidden="true"><x-ui-icon name="phone" /></span>
            <div>
                <h2 class="ui-card-title" id="lwListenTitle">Listen to the call</h2>
                <p class="ui-card-sub" data-listen-status role="status" aria-live="polite">{{ $listenBlocker ?? 'A call is connected. Listening is receive-only: your microphone is never used and nothing is recorded.' }}</p>
            </div>
        </div>
        <div class="lw-listen-actions">
            <button type="button" class="btn-primary" data-listen-open @disabled($listenBlocker)><x-ui-icon name="play-circle" /> Listen</button>
            <button type="button" class="btn-secondary" data-listen-play hidden>Play audio</button>
            <button type="button" class="btn-outline-danger" data-listen-stop hidden><x-ui-icon name="stop-circle" /> Stop listening</button>
        </div>
        <div data-listen-audio hidden></div>
    </section>
    <dialog id="voiceListenDialog" class="workflow-dialog" aria-labelledby="voiceListenTitle">
        <header class="workflow-dialog__header"><div><h2 id="voiceListenTitle">Listen to this call</h2><p>The Seeker and the Helper will see a notice that an Adviser is listening. This is recorded in the audit log.</p></div><button type="button" data-dialog-close aria-label="Close"><x-ui-icon name="close" /></button></header>
        <form>
            <div class="workflow-dialog__body lw-listen-form">
                <label for="lwListenPurpose">Purpose <span aria-hidden="true">*</span></label>
                <select id="lwListenPurpose" name="purpose" required>
                    <option value="">Select a purpose</option>
                    @foreach(\App\Services\VoiceSupervisionService::PURPOSES as $purpose)<option value="{{ $purpose }}">{{ ucwords(str_replace('_', ' ', $purpose)) }}</option>@endforeach
                </select>
                <label for="lwListenReason">Reason <span aria-hidden="true">*</span></label>
                <textarea id="lwListenReason" name="reason" rows="3" minlength="10" maxlength="500" required placeholder="For example: Checking how the Helper handles a first voice call."></textarea>
                <p class="lw-error" data-listen-error role="alert"></p>
            </div>
            <footer class="workflow-dialog__footer"><button type="button" class="btn-secondary" data-dialog-close>Cancel</button><button type="submit" class="btn-primary">Start listening</button></footer>
        </form>
    </dialog>

    <div class="lw-grid">
        <section class="ui-card" aria-labelledby="lwTranscript">
            <h2 class="ui-card-title" id="lwTranscript">Conversation</h2>
            <div class="lw-messages" data-live-messages aria-live="polite">
                <p class="lw-empty" data-live-empty>Loading messages…</p>
            </div>
        </section>
        <aside class="ui-card lw-coach" aria-labelledby="lwCoach">
            <h2 class="ui-card-title" id="lwCoach">Notes</h2>
            <p class="ui-card-sub">Send a coach note that only {{ $session->helper?->first_name ?? 'the Helper' }} sees, or keep a private note for yourself. Private notes appear again when you evaluate this session.</p>
            <form data-live-note-form>
                @csrf
                <label for="lwNote" class="sr-only">Note</label>
                <textarea id="lwNote" name="note" rows="3" maxlength="600" required placeholder="For example: Reflect their feelings back before suggesting steps."></textarea>
                <label class="lw-private"><input type="checkbox" name="private" value="1"> Keep private (not sent to the Helper)</label>
                <button type="submit" class="btn-primary" data-note-submit>Send to Helper</button>
                <p class="lw-error" data-live-note-error role="alert" hidden></p>
            </form>
            <h3>Notes</h3>
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
.lw-listen { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 16px; margin-bottom: 16px; }
.lw-listen-copy { display: flex; align-items: flex-start; gap: 12px; min-width: 0; flex: 1 1 320px; }
.lw-listen-icon { display: inline-grid; place-items: center; flex: none; width: 40px; height: 40px; border-radius: 50%; background: #ecfdf3; color: #087341; }
.lw-listen[data-listen-state="listening"] .lw-listen-icon { background: #027039; color: #fff; }
.lw-listen .ui-card-sub { margin: 2px 0 0; }
.lw-listen-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.lw-listen-form { display: grid; gap: 8px; }
.lw-listen-form select, .lw-listen-form textarea { width: 100%; }
.lw-private { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #334155; }
.lw-private input { width: 16px; height: 16px; accent-color: #027039; }
.lw-notes li.is-private { background: #fffbeb; border: 1px dashed #fcd34d; }
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
    const addNote = (n) => {
        if (notesList.querySelector('[data-note-id="' + n.id + '"]')) return;
        notesEmpty.hidden = true; const li = el('li', n.private ? 'is-private' : ''); li.dataset.noteId = n.id;
        li.append(el('small', '', n.time + (n.private ? ' · Private, only you' : (n.read ? ' · Sent · seen' : ' · Sent to Helper'))), document.createTextNode(n.note));
        notesList.prepend(li); notesAfter = Math.max(notesAfter, n.id);
    };
    const privateBox = form.querySelector('input[name="private"]');
    const submitLabel = form.querySelector('[data-note-submit]');
    privateBox.addEventListener('change', () => { submitLabel.textContent = privateBox.checked ? 'Save private note' : 'Send to Helper'; });
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
            const res = await fetch(root.dataset.liveNotes, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ note, private: privateBox.checked }) });
            if (!res.ok) throw new Error(res.status === 404 ? 'The session is no longer live.' : 'The note could not be saved. Try again.');
            const data = await res.json();
            addNote(data.note); form.note.value = '';
        } catch (err) { error.textContent = err.message; error.hidden = false; }
        finally { if (!stopped) button.disabled = false; }
    });
    poll();
    timer = setInterval(poll, 4000);
    document.addEventListener('visibilitychange', poll);
})();
</script>
<script src="{{ asset('js/voice-listen.js') }}?v={{ filemtime(public_path('js/voice-listen.js')) }}" defer></script>
@endsection
