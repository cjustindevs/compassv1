<div class="request-draft-controls">
    <div>
        <button type="button" class="btn-outline" data-save-draft>Save draft</button>
        <button type="button" class="btn-outline" data-discard-draft @disabled(!$draft)>Discard saved draft</button>
    </div>
    <p class="text-sm text-gray-500">Drafts expire {{ \App\Models\SeekerRequestDraft::RETENTION_DAYS }} days after saving. They are not submitted or monitored by staff. {{ ($draftStage ?? 'screening') === 'preferences' ? 'Finish your review and select Submit Request to enter matching.' : 'Select Continue to record your screening and request the next support step.' }}</p>
    <p class="text-sm" data-draft-status role="status" aria-live="polite">@if($draft)Draft restored · Saved {{ $draft->updated_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT.@endif</p>
</div>
