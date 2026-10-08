@if($record->archived_at || app(\App\Services\AdviserArchive::class)->canArchive($record))
<form method="POST" action="{{ route('adviser.archive.store') }}" class="inline">
    @csrf
    <input type="hidden" name="record_type" value="{{ $record instanceof \App\Models\Session ? 'session' : 'duty' }}">
    <input type="hidden" name="record_id" value="{{ $record->id }}">
    @if($record->archived_at)<input type="hidden" name="restore" value="1">@endif
    <button type="submit" class="av-button">{{ $record->archived_at ? 'Restore' : 'Archive' }}</button>
</form>
@endif
