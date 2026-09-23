@extends('layouts.app')

@section('title', 'Helper Detail – COMPASS')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }
        .card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #e5e7eb; box-shadow: 0 4px 20px rgba(0,0,0,0.01); }
        .competency-level { padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .competency-level.expert { background: #dcfce7; color: #166534; }
        .competency-level.advanced { background: #dbeafe; color: #1e40af; }
        .competency-level.intermediate { background: #fef3c7; color: #92400e; }
        .competency-level.beginner { background: #fee2e2; color: #991b1b; }
        .competency-level.trainee { background: #e5e7eb; color: #6b7280; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        @media (max-width: 768px) {
            .card { padding: 16px; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center gap-4 mb-6">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Helper Detail</h1>
                <p class="text-sm text-gray-500 hidden sm:block">
                    Performance history and supervision
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <!-- Back Link -->
        <a href="{{ route('adviser.helpers') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Helpers
        </a>

        @php($verificationVerified = ($helper->verification_status ?? 'pending') === 'verified')
        <div class="card mb-6">
            <div class="card-header">
                <h3><i class="fas fa-id-card-clip mr-2 text-green-700" aria-hidden="true"></i>Institutional eligibility and training</h3>
                <span class="pill {{ $verificationVerified ? '' : 'pill-warning' }}">
                    {{ $verificationVerified ? 'Verified' : 'Pending verification' }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mb-4">
                Verify current DWCC psychology enrollment, recognized membership and completed orientation or training before this helper can receive assignments.
            </p>

            @if($verificationVerified)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <div class="rounded-xl border border-green-100 bg-green-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-green-700 font-semibold">Status</p>
                        <p class="text-sm font-semibold text-gray-800 mt-1"><i class="fas fa-circle-check text-green-600 mr-1" aria-hidden="true"></i>Verified</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Last verified</p>
                        <p class="text-sm font-semibold text-gray-800 mt-1">{{ $helper->verified_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Expires</p>
                        <p class="text-sm font-semibold text-gray-800 mt-1">{{ $helper->verification_expires_at?->timezone('Asia/Manila')->format('M d, Y') ?? 'No expiry set' }}</p>
                    </div>
                </div>
                @if($helper->qualification_evidence)
                    <p class="text-sm text-gray-600 mb-4"><span class="font-semibold text-gray-700">Evidence on file:</span> {{ $helper->qualification_evidence }}</p>
                @endif
            @else
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 mb-4" role="status">
                    <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5" aria-hidden="true"></i>
                    <p class="text-sm text-amber-800">This helper cannot receive assignments until an adviser records eligibility and training verification.</p>
                </div>
            @endif

            @if($errors->any())
                <p role="alert" class="text-sm text-red-600 mb-3"><i class="fas fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ $errors->first() }}</p>
            @endif

            <details class="border border-gray-200 rounded-xl p-4" {{ $verificationVerified ? '' : 'open' }}>
                <summary class="font-semibold text-sm text-gray-700 cursor-pointer">
                    <i class="fas fa-pen-to-square mr-1" aria-hidden="true"></i>{{ $verificationVerified ? 'Update verification' : 'Record verification' }}
                </summary>
                <form method="POST" action="{{ route('adviser.helper.verify', $helper->id) }}" class="form-container mt-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <label class="checkbox-group requirement-card">
                            <input type="checkbox" name="currently_enrolled" value="1" required>
                            <span class="requirement-icon"><i class="fas fa-graduation-cap" aria-hidden="true"></i></span>
                            <span>
                                <strong class="block text-sm font-semibold text-gray-800">Current DWCC psychology enrollment</strong>
                                <span class="text-xs text-gray-500">Enrollment confirmed for this term</span>
                            </span>
                        </label>
                        <label class="checkbox-group requirement-card">
                            <input type="checkbox" name="recognized_member" value="1" required>
                            <span class="requirement-icon"><i class="fas fa-id-badge" aria-hidden="true"></i></span>
                            <span>
                                <strong class="block text-sm font-semibold text-gray-800">Recognized membership</strong>
                                <span class="text-xs text-gray-500">Member of a recognized organization</span>
                            </span>
                        </label>
                        <label class="checkbox-group requirement-card">
                            <input type="checkbox" name="training_completed" value="1" required>
                            <span class="requirement-icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></span>
                            <span>
                                <strong class="block text-sm font-semibold text-gray-800">Training and orientation</strong>
                                <span class="text-xs text-gray-500">Completed required orientation or training</span>
                            </span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="qualification_evidence">Verification evidence or institutional reference</label>
                        <textarea id="qualification_evidence" name="qualification_evidence" class="form-control" maxlength="2000" required>{{ old('qualification_evidence', $helper->qualification_evidence) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="verification_expires_at">Verification expiry (if applicable)</label>
                        <input id="verification_expires_at" type="date" name="verification_expires_at" class="form-control" value="{{ old('verification_expires_at', $helper->verification_expires_at?->format('Y-m-d')) }}">
                    </div>
                    <div class="flex justify-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-user-check" aria-hidden="true"></i> {{ $verificationVerified ? 'Update verification' : 'Record verification' }}
                        </button>
                    </div>
                </form>
            </details>
        </div>

        <div class="card">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-4 mb-6 pb-4 border-b border-gray-200">
                <div class="w-16 h-16 rounded-full bg-green-500 flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
                    {{ substr($helper->first_name, 0, 1) }}{{ substr($helper->last_name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $helper->first_name }} {{ $helper->last_name }}</h1>
                    <p class="text-sm text-gray-500">
                        @if($helper->adviser)
                            Supervised by: {{ $helper->adviser->first_name }} {{ $helper->adviser->last_name }}
                        @else
                            No adviser assigned
                        @endif
                    </p>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <span class="competency-level {{ strtolower($competencyHistory->first()->competency_level ?? 'Beginner') }}">
                        {{ $competencyHistory->first()->competency_level ?? 'Beginner' }}
                    </span>
                    <span class="text-sm text-gray-500">Score: {{ $competencyHistory->first()->overall_score ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ $helper->sessions->count() }}</p>
                    <p class="text-xs text-gray-400">Active Sessions</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ $competencyHistory->count() }}</p>
                    <p class="text-xs text-gray-400">Evaluations</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">
                        <x-ui-icon :value="$helper->isOnline() ? 'fa-circle-check' : 'fa-circle-xmark'" />
                    </p>
                    <p class="text-xs text-gray-400">Available</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">
                        <x-ui-icon :value="$helper->latestReadiness?->assessment_result === 'ready' ? 'fa-circle' : 'fa-circle'" />
                    </p>
                    <p class="text-xs text-gray-400">Readiness</p>
                </div>
            </div>

            <!-- Supervision -->
            <h3 class="font-semibold text-gray-700 mb-4">Supervision</h3>
            <div class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl mb-6">
                <span class="text-sm text-gray-500">Assigned adviser:</span>
                <span class="text-sm font-semibold text-gray-700">{{ $helper->adviser?->first_name }} {{ $helper->adviser?->last_name }}</span>
                <span class="text-xs text-gray-400">Active referrals follow the helper when supervision is transferred.</span>
            </div>

            <details class="mb-6 border border-gray-200 rounded-xl p-4">
                <summary class="font-semibold text-sm text-gray-700 cursor-pointer">Transfer supervision</summary>
                <p class="text-sm text-gray-500 my-3">This transfers the helper and their active referrals to another adviser. Completed referral records stay with their original reviewer.</p>
                @if($errors->any())<p role="alert" class="text-sm text-red-600 mb-3">{{ $errors->first() }}</p>@endif
                <form method="POST" action="{{ route('adviser.helpers.reassign') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="helper_ids[]" value="{{ $helper->id }}">
                    <label class="block text-sm font-medium">Receiving adviser
                        <select name="adviser_id" required class="block w-full rounded-lg border-gray-300 mt-1 p-2 border">
                            <option value="">Select adviser</option>
                            @foreach($transferAdvisers as $adviser)<option value="{{ $adviser->id }}">{{ $adviser->full_name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium">Reason
                        <textarea name="reason" required maxlength="1000" rows="2" class="block w-full rounded-lg border border-gray-300 mt-1 p-2">{{ old('reason') }}</textarea>
                    </label>
                    <button type="submit" class="rounded-lg bg-green-700 text-white px-4 py-2 font-semibold text-sm">Transfer supervision</button>
                </form>
            </details>
            <h3 class="font-semibold text-gray-700 mb-3">Supervision history</h3>
            @forelse($assignmentHistory as $assignment)
                <div class="rounded-xl border border-gray-200 p-3 mb-3 text-sm">
                    <p class="font-semibold">Adviser #{{ $assignment->adviser_id }} ? {{ $assignment->ended_at ? 'Previous' : 'Current' }}</p>
                    <p class="text-gray-500">{{ $assignment->started_at ?? 'Original start date unknown' }} ? {{ $assignment->ended_at ?? 'Present' }}</p>
                    <p class="text-gray-600">{{ $assignment->reason }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 mb-4">Current supervision is recorded on the Helper profile; historical assignment dates are unavailable.</p>
            @endforelse
            <!-- Competency History -->
            <h3 class="font-semibold text-gray-700 mb-4">Competency History</h3>
            @if($competencyHistory->isNotEmpty())
                <div class="space-y-2">
                    @foreach($competencyHistory as $evaluation)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-medium text-gray-800">{{ $evaluation->evaluation_period ?? $evaluation->created_at->format('M Y') }}</p>
                                <p class="text-sm text-gray-500">Adviser: {{ $evaluation->adviser->first_name ?? 'Unknown' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">{{ round($evaluation->overall_score, 2) }} / 5.00</p>
                                <span class="competency-level {{ strtolower($evaluation->competency_level ?? 'Beginner') }}">
                                    {{ $evaluation->competency_level ?? 'Beginner' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">No competency evaluations recorded yet.</p>
            @endif
        </div>
</div>
@endsection
