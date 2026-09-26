@php
    $notice = session('error') ?? session('success') ?? session('info') ?? session('status');
@endphp
@if(is_string($notice) || $errors->any())
<dialog id="workflowNotice" aria-labelledby="workflowNoticeTitle" style="border:1px solid #e5e7eb;border-radius:16px;padding:24px;width:min(440px,calc(100vw - 32px));color:#163b2d;">
    <h2 id="workflowNoticeTitle" class="text-lg font-semibold mb-3">{{ $errors->any() || session('error') ? 'Please review your request' : 'Status update' }}</h2>
    @if(is_string($notice))<p class="text-sm mb-4" role="status">{{ $notice }}</p>@endif
    @if($errors->any())
        <ul class="text-sm mb-4 list-disc pl-5" role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif
    <form method="dialog"><button class="px-4 py-2 rounded-lg bg-green-600 text-white font-semibold" autofocus>Continue</button></form>
</dialog>
<style>#workflowNotice::backdrop { background:rgba(15,35,25,.5); }</style>
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
