@once
@if(is_array(session('completion_notice')))
@php($completion = session('completion_notice'))
<dialog id="completionNotice" class="focus-panel-dialog" open aria-labelledby="completionNoticeTitle">
    <header><h2 id="completionNoticeTitle">{{ $completion['title'] }}</h2><button type="button" onclick="document.getElementById('completionNotice').close()">Close</button></header>
    <p>{{ $completion['message'] }}</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px"><a class="focus-panel-button" href="{{ $completion['url'] }}">{{ $completion['label'] }}</a>@if(!empty($completion['secondary']))<a class="focus-panel-button" href="{{ $completion['secondary'] }}">View history</a>@endif</div>
</dialog>
<script>document.addEventListener('DOMContentLoaded',()=>{const dialog=document.getElementById('completionNotice');if(dialog?.showModal){dialog.close();dialog.showModal();}});</script>
@endif
@endonce
