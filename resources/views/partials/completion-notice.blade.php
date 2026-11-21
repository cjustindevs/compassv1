@once
@if(is_array(session('completion_notice')))
@php($completion = session('completion_notice'))
<dialog id="completionNotice" class="cm-dialog" data-tone="primary" open aria-labelledby="completionNoticeTitle">
    <div class="cm-head">
        <span class="cm-icon" aria-hidden="true"><x-ui-icon name="check-circle" /></span>
        <div><h2 id="completionNoticeTitle" class="cm-title">{{ $completion['title'] }}</h2><p class="cm-text">{{ $completion['message'] }}</p></div>
    </div>
    <div class="cm-actions">
        <button type="button" class="cm-btn cm-btn--secondary" onclick="document.getElementById('completionNotice').close()">Close</button>
        @if(!empty($completion['secondary']))<a class="cm-btn cm-btn--secondary" href="{{ $completion['secondary'] }}">View history</a>@endif
        <a class="cm-btn cm-btn--primary" href="{{ $completion['url'] }}">{{ $completion['label'] }}</a>
    </div>
</dialog>
<script>document.addEventListener('DOMContentLoaded',()=>{const dialog=document.getElementById('completionNotice');if(dialog?.showModal){dialog.close();dialog.showModal();}});</script>
@endif
@endonce
