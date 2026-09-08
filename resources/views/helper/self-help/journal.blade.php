@extends('layouts.helper')

@section('title', 'Private Journal')
@section('heading', 'Private Journal')
@section('subheading', 'A safe space for your thoughts. Only you can see these entries.')

@section('content')
    @if(session('success'))
        <div class="alert alert-success" data-flash><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- New entry -->
        <div class="card">
            <div class="card-header"><h3>New Entry</h3></div>
            <form method="POST" action="{{ route('helper.self-help.journal.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">How are you feeling right now?</label>
                    <select name="mood" class="form-control">
                        <option value="good">😊 Good</option>
                        <option value="okay">😐 Okay</option>
                        <option value="neutral">😌 Neutral</option>
                        <option value="anxious">😟 Anxious</option>
                        <option value="sad">😢 Sad</option>
                        <option value="tired">😴 Tired</option>
                    </select>
                    @error('mood')<p class="text-sm text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Your thoughts <span class="text-red-500">*</span></label>
                    <textarea name="content" class="form-control" rows="7" placeholder="What's on your mind? This is just for you...">{{ old('content') }}</textarea>
                    @error('content')<p class="text-sm text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-save mr-1"></i> Save Entry</button>
            </form>
        </div>

        <!-- Past entries -->
        <div class="card">
            <div class="card-header"><h3>Past Entries</h3></div>
            @forelse($entries as $entry)
                <div class="p-3 mb-3 rounded-xl" style="background:var(--gray-50);border:1px solid var(--gray-200);">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-400">{{ $entry->created_at->format('M d, Y h:i A') }}</span>
                        @if($entry->mood)
                            <span class="pill">{{ $entry->mood }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-700" style="white-space:pre-wrap;">{{ $entry->content }}</p>
                </div>
            @empty
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <p>No journal entries yet. Write your first one to get started.</p>
                </div>
            @endforelse
            {{ $entries->links() }}
        </div>
    </div>

    <div class="mt-6 text-center">
        <a href="{{ route('helper.self-help') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Self-Care
        </a>
    </div>
@endsection
