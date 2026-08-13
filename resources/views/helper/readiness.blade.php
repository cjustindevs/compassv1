@extends('layouts.helper')

@section('title', 'Readiness Check')
@section('heading', 'Readiness Check')
@section('subheading', 'Check in before accepting new sessions.')

@section('styles')
    <style>
        /* ─────────── Modal (breathing exercise) ─────────── */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 999;
            display: none; align-items: center; justify-content: center;
            background: rgba(15, 40, 30, 0.45);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white; border-radius: 24px; width: 100%; max-width: 480px;
            max-height: 92vh; overflow-y: auto;
            display: flex; flex-direction: column;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.25);
            animation: modalSlide 0.25s ease-out;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-body { padding: 24px; }
        .modal-close {
            position: absolute; top: 14px; right: 14px;
            width: 32px; height: 32px; border-radius: 50%;
            border: none; background: var(--gray-100); color: var(--gray-500);
            font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        .modal-close:hover { background: var(--gray-200); color: var(--gray-800); }
        .modal-close:focus-visible, .btn:focus-visible { outline: 3px solid rgba(4, 160, 82, 0.4); outline-offset: 2px; }

        /* ─────────── Breathing circle ─────────── */
        .breath-circle {
            width: 150px; height: 150px; border-radius: 50%;
            background: linear-gradient(135deg, #EAF8F0, #D0F0D8);
            margin: 0 auto; display: flex; align-items: center; justify-content: center;
            font-size: 40px;
            transition: transform 1s ease, background 0.5s ease, border-color 0.5s ease;
            border: 3px solid #04A052;
        }
        .breath-circle.inhale {
            transform: scale(1.3);
            background: linear-gradient(135deg, #D0F0D8, #A8E0B0);
            border-color: #16A34A;
        }
        .breath-circle.hold {
            transform: scale(1.3);
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border-color: #F59E0B;
        }
        .breath-circle.exhale {
            transform: scale(0.8);
            background: linear-gradient(135deg, #DBEAFE, #93C5FD);
            border-color: #3B82F6;
        }
        #breath-text {
            transition: color 0.4s ease;
            min-height: 34px;
        }
        #breath-circle.inhale ~ * #breath-text { color: #16A34A; }

        .step-card {
            background: linear-gradient(135deg, #F0FDF4, #EAF8F0);
            border: 1px solid #BBF7D0; border-radius: 16px; padding: 14px 16px;
        }
        .step-card ol { list-style: none; padding: 0; margin: 0; counter-reset: bs; }
        .step-card ol li {
            counter-increment: bs; display: flex; align-items: center; gap: 10px;
            font-size: 13px; color: var(--gray-700); padding: 4px 0;
        }
        .step-card ol li::before {
            content: counter(bs); width: 22px; height: 22px; border-radius: 50%;
            background: var(--green-500); color: white; font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }

        .btn-outline {
            background: white; color: var(--gray-600);
            border: 1px solid var(--gray-300);
        }
        .btn-outline:hover { background: var(--gray-50); color: var(--gray-800); }

        #breath-exercise-ui { display: none; }
    </style>
@endsection

@section('content')

    @if(isset($latestCheck) && $latestCheck)
        <div class="card mb-6">
            <div class="card-header">
                <h3>Latest Check-in</h3>
                <span class="pill {{ $latestCheck->assessment_result === 'ready' ? '' : 'badge danger' }}" style="{{ $latestCheck->assessment_result === 'ready' ? '' : 'background:#FEE2E2;color:var(--red-600);' }}">
                    {{ $latestCheck->result_label }}
                </span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-xs text-gray-400 mb-1">Checked on</div>
                    <div class="font-semibold text-gray-800">{{ $latestCheck->assessment_date?->format('M d, Y h:i A') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 mb-1">Availability</div>
                    <div class="font-semibold text-gray-800">{{ ucfirst($latestCheck->availability_status ?? 'available') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 mb-1">Stress level</div>
                    <div class="font-semibold text-gray-800">{{ ucfirst($latestCheck->stress_level ?? 'low') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 mb-1">Emotionally ready</div>
                    <div class="font-semibold text-gray-800">{{ $latestCheck->emotionally_ready ? 'Yes' : 'No' }}</div>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('helper.readiness.history') }}" class="btn btn-secondary btn-sm">View history</a>
            </div>
        </div>
    @endif

    {{-- Breathing exercise banner --}}
    <div class="card mb-6" style="background: linear-gradient(135deg, #F0FDF4, #EAF8F0); border-color: #BBF7D0;">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h4 class="font-semibold text-gray-800" style="font-size:16px;">🧘 Center Yourself</h4>
                <p class="text-sm text-gray-500" style="max-width:480px;">Complete a short guided breathing exercise before you begin — it only takes about 30 seconds and helps you check in with how you are feeling.</p>
            </div>
            <button type="button" id="start-exercise-btn" class="btn btn-primary">
                <i class="fas fa-play mr-2"></i> Start Breathing Exercise
            </button>
        </div>
        <div id="exercise-status" class="text-sm mt-2 text-gray-400" aria-live="polite">Not started yet</div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Check-in</h3>
            <span class="text-xs text-gray-400">Be honest — this keeps everyone safe.</span>
        </div>
        <form method="POST" action="{{ route('helper.readiness.store') }}" id="readinessForm">
            @csrf

            <input type="hidden" name="exercise_completed" id="exercise-completed" value="">

            <div class="form-group">
                <label class="form-label">Emotionally ready to take sessions right now?</label>
                <div class="border border-gray-200 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-sm text-gray-700">I feel emotionally stable and present.</span>
                    <div class="flex gap-3">
                        <label class="checkbox-group" style="padding:0;"><input type="radio" name="emotionally_ready" value="1" {{ old('emotionally_ready') === '1' ? 'checked' : '' }}> Yes</label>
                        <label class="checkbox-group" style="padding:0;"><input type="radio" name="emotionally_ready" value="0" {{ old('emotionally_ready') === '0' ? 'checked' : '' }}> No</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Am I willing to actively listen to a peer today?</label>
                <div class="border border-gray-200 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-sm text-gray-700">I can give my full attention to a listener.</span>
                    <div class="flex gap-3">
                        <label class="checkbox-group" style="padding:0;"><input type="radio" name="willing_to_listen" value="1" {{ old('willing_to_listen') === '1' ? 'checked' : '' }}> Yes</label>
                        <label class="checkbox-group" style="padding:0;"><input type="radio" name="willing_to_listen" value="0" {{ old('willing_to_listen') === '0' ? 'checked' : '' }}> No</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">What is your current stress level?</label>
                <select name="stress_level" class="form-control" required>
                    <option value="">Select stress level...</option>
                    <option value="low" {{ old('stress_level') === 'low' ? 'selected' : '' }}>Low — feeling calm</option>
                    <option value="moderate" {{ old('stress_level') === 'moderate' ? 'selected' : '' }}>Moderate — manageable</option>
                    <option value="high" {{ old('stress_level') === 'high' ? 'selected' : '' }}>High — I need a break</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Availability for new sessions</label>
                <select name="availability_status" class="form-control" required>
                    <option value="available" {{ old('availability_status') === 'available' ? 'selected' : '' }}>Available</option>
                    <option value="not_ready" {{ old('availability_status') === 'not_ready' ? 'selected' : '' }}>Not ready</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" disabled>
                <i class="fas fa-check"></i> Submit Check-in
            </button>
            <p class="text-xs text-gray-400 mt-2">
                <i class="fas fa-lock mr-1"></i>
                Complete the breathing exercise above to unlock the Submit button.
            </p>
        </form>
    </div>

    {{-- Breathing Exercise Modal --}}
    <div id="exercise-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="breath-modal-title" aria-hidden="true">
        <div class="modal-box" style="position:relative;">
            <button type="button" id="close-modal-btn" class="modal-close" aria-label="Close breathing exercise"><i class="fas fa-times"></i></button>
            <div class="modal-body">
                <div class="text-center">
                    <div class="text-5xl mb-3">🌿</div>
                    <h3 id="breath-modal-title" class="text-xl font-bold text-gray-800">Center Yourself</h3>
                    <p class="text-sm text-gray-500" id="exercise-modal-status">Take a moment before you begin.</p>

                    {{-- Intro screen --}}
                    <div id="breath-intro">
                        <div class="step-card mt-6 text-left">
                            <p class="text-sm font-semibold text-gray-700 mb-2">You cannot pour from an empty cup. Take care of yourself first so you can care for others.</p>
                            <ol>
                                <li>Inhale through your nose</li>
                                <li>Hold your breath</li>
                                <li>Exhale slowly through your mouth</li>
                            </ol>
                        </div>
                        <div class="mt-5 flex justify-center gap-3 flex-wrap">
                            <button type="button" id="begin-lesson-btn" class="btn btn-primary">Begin Exercise</button>
                        </div>
                    </div>

                    {{-- Exercise screen --}}
                    <div id="breath-exercise-ui">
                        <h4 class="text-lg font-bold text-gray-700 mt-2">🧘 Breathe with me</h4>

                        <div class="mt-6">
                            <div id="breath-circle" class="breath-circle">
                                <span id="breath-icon" aria-hidden="true">⬆️</span>
                            </div>
                            <h4 id="breath-text" class="text-2xl font-bold text-gray-800 mt-4">Inhale...</h4>
                            <p id="breath-instruction" class="text-sm text-gray-500">Inhale through your nose</p>
                            <p id="breath-timer" class="text-xs text-gray-400">4s</p>
                        </div>

                        <div class="mt-4">
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden" role="progressbar" aria-label="Breathing exercise progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div id="breath-progress" class="h-full bg-green-500 transition-all duration-500" style="width: 0%;"></div>
                            </div>
                            <p id="step-indicator" class="text-xs text-gray-400 mt-2">Step 1 of 3 · Cycle 1 of 3</p>
                        </div>
                    </div>

                    {{-- Footer actions (visible on both screens) --}}
                    <div class="mt-5 flex justify-center gap-3 flex-wrap">
                        <button type="button" id="skip-btn" class="btn btn-outline text-sm py-2 px-4">Skip Exercise</button>
                        <button type="button" id="continue-btn" class="btn btn-primary text-sm py-2 px-4" disabled>Continue to Form</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    @vite('resources/js/breathing-exercise.js')
@endsection
