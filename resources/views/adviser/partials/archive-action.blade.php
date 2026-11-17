@if($record->archived_at || app(\App\Services\AdviserArchive::class)->canArchive($record))
<form method="POST" action="{{ route('adviser.archive.store') }}" class="inline-flex">
    @csrf
    <input type="hidden" name="record_type" value="{{ $record instanceof \App\Models\Session ? 'session' : 'duty' }}">
    <input type="hidden" name="record_id" value="{{ $record->id }}">
    @if($record->archived_at)<input type="hidden" name="restore" value="1">@endif
    @if($record->archived_at)<x-ui.icon-button icon="undo" label="Restore" type="submit" />@else<x-ui.icon-button icon="archive" label="Archive" type="submit" />@endif
</form>
@endif
