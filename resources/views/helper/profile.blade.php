@extends('layouts.helper')

@section('title', 'Profile')

@section('heading', 'My Profile')
@section('subheading', 'Your helper information and practice overview.')

@section('content')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Profile card -->
        <div class="lg:col-span-1">
            <div class="card" style="text-align:center;padding:32px 24px;">
                <div class="avatar-lg" style="margin:0 auto 16px;">{{ \Illuminate\Support\Str::substr($helper->full_name, 0, 2) }}</div>
                <div class="text-lg font-bold text-gray-800">{{ $helper->full_name }}</div>
                <div class="text-sm text-gray-500 mt-1">Psychology Helper</div>
                <div class="text-sm text-gray-400 mt-1"><i class="fas fa-envelope mr-1"></i>{{ $helper->email }}</div>

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
                        <div class="text-2xl font-bold text-green-600">{{ $competencyScore }}%</div>
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $helper->phone ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Preferred language</label>
                            <input type="text" name="preferred_language" class="form-control" value="{{ old('preferred_language', $helper->preferred_language ?? 'English') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Specializations</label>
                        <input type="text" name="specializations" class="form-control" value="{{ old('specializations', $helper->specializations ?? '') }}" placeholder="e.g. Anxiety, Family Stress, Academics">
                    </div>

                    <hr class="divider">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Account name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </form>
            </div>

            <!-- Recent sessions -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Sessions</h3>
                    <a href="{{ route('helper.cases') }}" class="link">View all</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Seeker</th>
                                <th>Concern</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSessions as $session)
                                <tr>
                                    <td class="font-medium">{{ $session->reference_number }}</td>
                                    <td>{{ $session->seeker->generated_alias ?? 'Seeker' }}</td>
                                    <td class="text-sm text-gray-500">{{ Illuminate\Support\Str::limit($session->concern->concern_name ?? '—', 24) }}</td>
                                    <td><span class="status-badge {{ str_replace('_', '-', $session->session_status) }}">{{ $session->status_label }}</span></td>
                                    <td>{{ $session->created_at?->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-6 text-gray-400">No sessions yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

@endsection