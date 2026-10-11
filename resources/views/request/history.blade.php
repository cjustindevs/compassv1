@extends('layouts.app')
@section('title','COMPASS - Request History')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/seeker-request.css') }}?v={{ filemtime(public_path('css/seeker-request.css')) }}">
@endpush
@section('content')
@php($inlineErrors = true)
@php($inlineNotices = ['success'])
<x-seeker-step title="Request History">
    <div class="seeker-toolbar">
        <p class="text-sm text-gray-500">Your support requests and feedback &middot; Dates use Philippine Time.</p>
        <a class="seeker-button" href="{{ route($activeRequest ? 'request.matching' : 'request.screening') }}">{{ $activeRequest ? 'View active request' : 'Request support' }}</a>
    </div>
    <section class="request-section">
        <form method="GET" class="request-history-filters" action="{{ route('seeker.requests') }}">
            <label>Search reference or concern<input name="q" maxlength="80" value="{{ request('q') }}" placeholder="R-0047 or concern"></label>
            <label>Status<select name="status"><option value="">All statuses</option>
                @foreach(['waiting'=>'Waiting / Awaiting acceptance','active'=>'Chat ready','completed'=>'Completed','feedback_pending'=>'Feedback pending','review'=>'Adviser review','emergency'=>'Emergency support','expired'=>'Expired','cancelled'=>'Cancelled','no_show'=>'No-show'] as $value=>$label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label>From<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>To<input type="date" name="to" value="{{ request('to') }}"></label>
            <button class="seeker-button" type="submit">Apply</button>
            <a class="seeker-button secondary" href="{{ route('seeker.requests') }}">Reset</a>
        </form>
        <h2>Your requests <span class="text-gray-500 font-normal">({{ $requests->total() }})</span></h2>
        <div class="seeker-table-wrap" tabindex="0" aria-label="Support request records">
            <table class="seeker-table">
                <thead><tr><th>Reference</th><th>Concern</th><th>Date (PHT)</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($requests as $item)
                    <tr>
                        <td class="font-semibold">{{ $item->reference_number }}</td>
                        <td>{{ $item->concern?->concern_name ?? 'Not recorded' }}</td>
                        <td>{{ ($item->submitted_at ?? $item->created_date ?? $item->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}<br><span class="text-xs text-gray-500">{{ $item->submitted_at ? 'Submitted' : 'Created' }}</span></td>
                        <td><span class="seeker-badge">{{ \App\Services\SeekerRequestPresentation::status($item) }}</span></td>
                        <td><div class="flex flex-wrap gap-2">
                            <a class="text-green-700 underline" href="{{ route('seeker.requests.show',$item) }}">View details</a>
                            @if($item->session_status === 'completed' && !$item->evaluation)
                            <a class="text-green-700 underline" href="{{ route('session.evaluation',['session_id'=>$item->id]) }}">Give feedback</a>
                            @elseif($item->evaluation)
                            <a class="text-green-700 underline" href="{{ route('seeker.requests.show',$item) }}#feedback">View feedback</a>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ request()->filled('q') || request()->filled('status') || request()->filled('from') || request()->filled('to') ? 'No requests match these filters. Try another period or reset the filters.' : 'No requests yet. Your support history will appear here.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $requests->links() }}</div>
    </section>
</x-seeker-step>
@endsection
