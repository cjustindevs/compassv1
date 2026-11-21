@include('partials.completion-notice')
@php
    $notice = collect(['error', 'success', 'info', 'status'])
        ->reject(fn ($key) => in_array($key, $inlineNotices ?? [], true))
        ->map(fn ($key) => session($key))->first(fn ($value) => is_string($value));
    $showErrors = $errors->any() && ! ($inlineErrors ?? false);
    // Confirmations are non-blocking toasts; only errors need a modal acknowledgement.
    $noticeType = collect(['error', 'success', 'info', 'status'])
        ->reject(fn ($key) => in_array($key, $inlineNotices ?? [], true))
        ->first(fn ($key) => is_string(session($key)));
    $toastOnly = is_string($notice) && ! $showErrors && $noticeType !== 'error';
    $noticeProblem = $showErrors || session('error');
@endphp
@if($toastOnly)
<div hidden data-compass-toast data-type="{{ $noticeType === 'info' ? 'info' : 'success' }}" data-message="{{ $notice }}"></div>
@elseif(is_string($notice) || $showErrors)
<dialog id="workflowNotice" class="cm-dialog" data-tone="{{ $noticeProblem ? 'danger' : 'primary' }}" aria-labelledby="workflowNoticeTitle">
    <div class="cm-scroll">
        <div class="cm-head">
            <span class="cm-icon" aria-hidden="true"><x-ui-icon :name="$noticeProblem ? 'alert-circle' : 'check-circle'" /></span>
            <div>
                <h2 id="workflowNoticeTitle" class="cm-title">{{ $noticeProblem ? 'Please review your request' : 'Status update' }}</h2>
                @if(is_string($notice))<p class="cm-text" role="status">{{ $notice }}</p>@endif
            </div>
        </div>
        @if($showErrors)
            <div class="cm-body"><ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
    </div>
    <form method="dialog" class="cm-actions"><button class="cm-btn cm-btn--primary" autofocus>Continue</button></form>
</dialog>
<script>
(() => {
    const notice = document.getElementById('workflowNotice');
    // Let an identity/consent dialog complete first instead of stacking dialogs.
    const showNotice = () => {
        const existing = document.querySelector('dialog[open]');
        if (existing && existing !== notice) {
            existing.addEventListener('close', showNotice, {once: true});
            return;
        }
        if (!notice.open) notice.showModal();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showNotice, {once: true});
    else showNotice();
})();
</script>
@endif
