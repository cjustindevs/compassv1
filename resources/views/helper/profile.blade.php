@extends('layouts.helper')

@section('title', 'Profile')

@section('heading', 'My Profile')
@section('subheading', 'Your helper information and practice overview.')

@section('content')
<div class="hf-page">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 hf-profile-grid">

        <!-- Profile card -->
        <div class="lg:col-span-1">
            <div class="card" style="text-align:center;padding:32px 24px;">
                <div class="avatar-lg" style="margin:0 auto 16px;">{{ \Illuminate\Support\Str::substr($helper->full_name, 0, 2) }}</div>
                <div class="text-lg font-bold text-gray-800">{{ $helper->full_name }}</div>
                <div class="text-sm text-gray-500 mt-1">Psychology Helper</div>
                <div class="text-sm text-gray-400 mt-1">{{ $helper->email }}</div>

                <hr class="divider">

                <div class="grid grid-cols-2 gap-3 text-center">
                    <div>
                        <div class="text-2xl font-bold text-gray-800">{{ $totalSessions }}</div>
                        <div class="text-[10px] text-gray-400 uppercase">Total Sessions</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800">{{ $completedSessions }}</div>
                        <div class="text-[10px] text-gray-400 uppercase">Completed</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-800">{{ $reportsCount }}</div>
                        <div class="text-[10px] text-gray-400 uppercase">Docs</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ $competencyScore !== null ? $competencyScore.'%' : 'Not evaluated' }}</div>
                        <div class="text-[10px] text-gray-400 uppercase">Competency</div>
                    </div>
                </div>

                <hr class="divider">

                <div class="text-left">
                    <div class="flex justify-between mb-2">
                        <span class="text-sm text-gray-500">Status</span>
                        <span class="text-sm font-semibold {{ $helper->status === 'available' ? 'text-green-600' : 'text-yellow-600' }}">{{ ucfirst($helper->status ?? 'offline') }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-sm text-gray-500">Last readiness</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $readiness ? $readiness->result_label : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Language</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $helper->preferred_language ?? 'English' }}</span>
                    </div>
                    <div class="flex justify-between mt-2">
                        <span class="text-sm text-gray-500">Adviser</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $helper->adviser?->full_name ?? '—' }}</span>
                    </div>
                    @if($helper->adviser)
                        <div class="text-right mt-1">
                            <a class="link text-sm" href="mailto:{{ $helper->adviser->email ?? $helper->adviser->user?->email }}">{{ $helper->adviser->email ?? $helper->adviser->user?->email }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Edit form -->
        <div class="lg:col-span-2">
            <div class="card mb-6">
                <div class="card-header">
                    <h3>Edit Profile</h3>
                </div>
                <form method="POST" action="{{ route('helper.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hf-form-grid">
                        <div class="form-group">
                            <label class="form-label">First name</label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $helper->first_name) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last name</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $helper->last_name) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" placeholder="Short intro about yourself">{{ old('bio', $helper->bio ?? '') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hf-form-grid">
                        <div class="form-group">
                            <label class="form-label" for="profile-phone">Phone Number / Read-only</label>
                            <input type="text" id="profile-phone" class="form-control" readonly value="{{ $helper->phone ?? 'Not recorded' }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Preferred language</label>
                            <input type="text" name="preferred_language" class="form-control" value="{{ old('preferred_language', $helper->preferred_language ?? 'English') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Declared interests (subject to adviser verification)</label>
                        <input type="text" name="specializations" class="form-control" value="{{ old('specializations', $helper->declared_specializations ?? '') }}" placeholder="e.g. Anxiety, Family Stress, Academics">
                    </div>

                    <hr class="divider">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 hf-form-grid">

                        <div class="form-group">
                            <label class="form-label" for="profile-email">Email / Read-only</label>
                            <input type="email" id="profile-email" class="form-control" readonly value="{{ $user->email }}">
                        </div>
                    </div>

                    <p class="hf-muted" style="margin-bottom:16px">Email and phone are managed by the system. Contact your Adviser or administrator if a correction is needed.</p>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </form>
            </div>

        </div>

    </div>

</div>
@endsection