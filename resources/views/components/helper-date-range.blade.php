@props(['from', 'to'])
<div class="hf-range" data-date-range>
    <label>Date Range (Philippine Time)</label>
    <input type="hidden" name="date_range" value="{{ $from }} to {{ $to }}" data-range-value>
    <button type="button" class="form-control hf-range-button" data-range-toggle aria-label="Choose Date Range in Philippine Time" aria-expanded="false"><span data-range-label>{{ \Illuminate\Support\Carbon::parse($from)->format('M d, Y') }} - {{ \Illuminate\Support\Carbon::parse($to)->format('M d, Y') }}</span></button>
    <div class="hf-range-panel" hidden>
        <label>Start date<input type="date" value="{{ $from }}" data-range-start class="form-control" required></label>
        <label>End date<input type="date" value="{{ $to }}" data-range-end class="form-control" required></label>
        <p class="hf-muted">Choose up to 366 days, then apply your filters.</p>
        <button type="button" data-range-done class="btn btn-secondary btn-sm">Done</button>
    </div>
</div>
