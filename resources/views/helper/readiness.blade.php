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
            background: transparent;
            color: var(--gray-600);
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 50px;
            border: 1.5px solid var(--gray-200);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-badge.ready {
            background: var(--green-100);
            color: var(--green-700);
        }

        .status-badge.not_ready {
            background: #FEE2E2;
            color: #DC2626;
        }

        .status-badge.available {
            background: #DBEAFE;
            color: #1D4ED8;
        }

        .radio-option { min-width: 0; }

        @media (max-width: 1024px) {
            .form-card { padding: 24px 20px; }
        }

        @media (max-width: 768px) {
            .form-card { padding: 20px 16px; border-radius: 16px; }
            .radio-option { min-width: 100%; }
            .btn-primary,
            .btn-outline { padding: 12px 24px; font-size: 14px; width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            .form-card { padding: 16px 12px; }
        }
    </style>
@endsection

@section('content')

    <!-- Latest Status -->
    @if(isset($latestCheck))
        <div class="mb-6 p-4 bg-gray-50 rounded-xl flex items-center justify-between flex-wrap gap-3">
            <div>
                <span class="text-sm font-medium text-gray-600">Current Status:</span>
                <span class="status-badge {{ $latestCheck->assessment_result }}">
                    {{ ucfirst(str_replace('_', ' ', $latestCheck->assessment_result)) }}
                </span>
            </div>
            <span class="text-xs text-gray-400">
                Last checked: {{ $latestCheck->created_at->diffForHumans() }}
            </span>
        </div>
    @endif

    <!-- ─── FORM CARD ─── -->
    <div class="form-card">

        <form id="readinessForm" method="POST" action="{{ route('helper.readiness.store') }}">
            @csrf

            <!-- ============================================ -->
            <!-- SECTION 1: EMOTIONAL READINESS              -->
            <!-- ============================================ -->
            <div class="mb-6">
                <label class="form-label">I feel emotionally ready to listen right now. <span class="text-red-500">*</span></label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="emotionally_ready_yes" name="emotionally_ready" value="1" {{ old('emotionally_ready') == '1' ? 'checked' : '' }}>
                        <label for="emotionally_ready_yes">Yes</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="emotionally_ready_no" name="emotionally_ready" value="0" {{ old('emotionally_ready') == '0' ? 'checked' : '' }}>
                        <label for="emotionally_ready_no">No</label>
                    </div>
                </div>
                @error('emotionally_ready')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- ============================================ -->
            <!-- SECTION 2: WILLINGNESS TO LISTEN            -->
            <!-- ============================================ -->
            <div class="mb-6">
                <label class="form-label">I am willing to listen without judgment. <span class="text-red-500">*</span></label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="willing_yes" name="willing_to_listen" value="1" {{ old('willing_to_listen') == '1' ? 'checked' : '' }}>
                        <label for="willing_yes">Yes</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="willing_no" name="willing_to_listen" value="0" {{ old('willing_to_listen') == '0' ? 'checked' : '' }}>
                        <label for="willing_no">No</label>
                    </div>
                </div>
                @error('willing_to_listen')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- ============================================ -->
            <!-- SECTION 3: STRESS LEVEL                     -->
            <!-- ============================================ -->
            <div class="mb-6">
                <label class="form-label">My current stress level is: <span class="text-red-500">*</span></label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="stress_low" name="stress_level" value="low" {{ old('stress_level') == 'low' ? 'checked' : '' }}>
                        <label for="stress_low">
                            Low
                            <span class="sub-text">Feeling calm and focused</span>
                        </label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="stress_moderate" name="stress_level" value="moderate" {{ old('stress_level') == 'moderate' ? 'checked' : '' }}>
                        <label for="stress_moderate">
                            Moderate
                            <span class="sub-text">Somewhat stressed but manageable</span>
                        </label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="stress_high" name="stress_level" value="high" {{ old('stress_level') == 'high' ? 'checked' : '' }}>
                        <label for="stress_high">
                            High
                            <span class="sub-text">Feeling overwhelmed</span>
                        </label>
                    </div>
                </div>
                @error('stress_level')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- ============================================ -->
            <!-- SECTION 4: AVAILABILITY STATUS              -->
            <!-- ============================================ -->
            <div class="mb-6">
                <label class="form-label">Availability Status <span class="text-red-500">*</span></label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="avail_available" name="availability_status" value="available" {{ old('availability_status') == 'available' ? 'checked' : '' }}>
                        <label for="avail_available">
                            🟢 Available
                            <span class="sub-text">Ready to accept sessions</span>
                        </label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="avail_not_ready" name="availability_status" value="not_ready" {{ old('availability_status') == 'not_ready' ? 'checked' : '' }}>
                        <label for="avail_not_ready">
                            🔴 Not Ready
                            <span class="sub-text">Not accepting sessions right now</span>
                        </label>
                    </div>
                </div>
                @error('availability_status')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- ============================================ -->
            <!-- SECTION 5: BREATHING EXERCISE               -->
            <!-- ============================================ -->
            <div class="mb-6">
                <label class="form-label">Breathing Exercise <span class="text-red-500">*</span></label>
                <div class="step-card mb-3">
                    <ol>
                        <li>Find a quiet spot and sit comfortably.</li>
                        <li>Click "Start Breathing Exercise" below.</li>
                        <li>Follow the circle: inhale 4s, hold 4s, exhale 4s — 3 rounds.</li>
                        <li>Press "Continue" once finished (or skip if you prefer).</li>
                    </ol>
                </div>
                <button type="button" class="btn btn-primary" id="openExerciseBtn" style="width:100%;">
                    <i class="fas fa-wind"></i> Start Breathing Exercise
                </button>
                <input type="hidden" name="exercise_completed" id="exercise_completed" value="{{ old('exercise_completed', '') }}">
                @error('exercise_completed')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- ============================================ -->
            <!-- HELPER MESSAGE                              -->
            <!-- ============================================ -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-green-500 text-lg mt-0.5"></i>
                    <div>
                        <p class="text-sm text-gray-700 leading-relaxed">
                            <span class="font-semibold">💡 You cannot power from an empty cup.</span><br>
                            Take care of yourself first so you can care for others. If you're not feeling ready, it's okay to take a break.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- FORM ACTIONS                                -->
            <!-- ============================================ -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200">
                <a href="{{ route('helper.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition font-medium text-sm">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <button type="submit" class="btn-primary w-full sm:w-auto">
                        <i class="fas fa-check mr-2"></i> Submit Readiness
                    </button>
                </div>
            </div>

        </form>
    </div>

    <!-- Footer -->
    <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
        <i class="fas fa-heart text-[#04A052] mr-1"></i>
        Your well-being matters. Take care of yourself first.
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- BREATHING EXERCISE MODAL                      -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="modal-overlay" id="exercise-modal">
        <div class="modal-box" style="position:relative;">
            <button type="button" class="modal-close" id="closeExerciseBtn" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-body" style="text-align:center;">
                <h3 style="font-size:18px;font-weight:800;color:var(--gray-800);margin-bottom:4px;">Breathing Exercise</h3>
                <p style="font-size:13px;color:var(--gray-500);margin-bottom:20px;">3 rounds of inhale – hold – exhale</p>

                <div class="breath-circle" id="breath-circle">
                    <i class="fas fa-wind"></i>
                </div>
                <div id="breath-text" style="font-size:16px;font-weight:700;color:var(--gray-600);margin:18px 0 4px;">Ready?</div>
                <p style="font-size:12px;color:var(--gray-400);margin-bottom:20px;">Round <span id="round-count">1</span> of 3</p>

                <div class="flex flex-col gap-3">
                    <button type="button" class="btn btn-primary" id="begin-lesson-btn" style="width:100%;">
                        <i class="fas fa-play"></i> Begin Lesson
                    </button>
                    <button type="button" class="btn btn-primary" id="continue-btn" style="width:100%;display:none;">
                        <i class="fas fa-check"></i> Continue
                    </button>
                    <button type="button" class="btn-outline" id="skipExerciseBtn" style="width:100%;justify-content:center;">
                        Skip for now
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const modal = document.getElementById('exercise-modal');
            const openBtn = document.getElementById('openExerciseBtn');
            const closeBtn = document.getElementById('closeExerciseBtn');
            const skipBtn = document.getElementById('skipExerciseBtn');
            const beginBtn = document.getElementById('begin-lesson-btn');
            const continueBtn = document.getElementById('continue-btn');
            const circle = document.getElementById('breath-circle');
            const breathText = document.getElementById('breath-text');
            const roundCount = document.getElementById('round-count');
            const hiddenInput = document.getElementById('exercise_completed');
            const form = document.getElementById('readinessForm');

            function openModal() {
                modal.classList.add('active');
                resetExercise();
            }

            function closeModal() {
                modal.classList.remove('active');
                clearTimeout(window.__breathTimer);
                circle.className = 'breath-circle';
                breathText.textContent = 'Ready?';
                beginBtn.style.display = '';
                continueBtn.style.display = 'none';
            }

            function resetExercise() {
                clearTimeout(window.__breathTimer);
                circle.className = 'breath-circle';
                breathText.textContent = 'Ready?';
                roundCount.textContent = '1';
                beginBtn.style.display = '';
                continueBtn.style.display = 'none';
            }

            function runBreathing() {
                beginBtn.style.display = 'none';
                let round = 1;
                const rounds = 3;

                const phase = (label, className, ms, next) => {
                    breathText.textContent = label;
                    circle.className = 'breath-circle ' + className;
                    window.__breathTimer = setTimeout(next, ms);
                };

                const cycle = () => {
                    if (round > rounds) {
                        breathText.textContent = 'Great job! 🎉';
                        circle.className = 'breath-circle';
                        continueBtn.style.display = '';
                        continueBtn.focus();
                        return;
                    }
                    roundCount.textContent = String(round);
                    phase('Inhale…', 'inhale', 4000, () => {
                        phase('Hold…', 'hold', 4000, () => {
                            phase('Exhale…', 'exhale', 4000, () => {
                                round += 1;
                                cycle();
                            });
                        });
                    });
                };

                cycle();
            }

            openBtn.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
            skipBtn.addEventListener('click', function () {
                hiddenInput.value = 'skipped';
                closeModal();
            });
            beginBtn.addEventListener('click', runBreathing);
            continueBtn.addEventListener('click', function () {
                hiddenInput.value = 'completed';
                closeModal();
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });

            form.addEventListener('submit', function (e) {
                const emotionallyReady = document.querySelector('input[name="emotionally_ready"]:checked');
                const willingListen = document.querySelector('input[name="willing_to_listen"]:checked');
                const stressLevel = document.querySelector('input[name="stress_level"]:checked');
                const availability = document.querySelector('input[name="availability_status"]:checked');

                if (!emotionallyReady || !willingListen || !stressLevel || !availability) {
                    e.preventDefault();
                    alert('Please answer all questions before submitting.');
                    return false;
                }

                if (!hiddenInput.value) {
                    e.preventDefault();
                    openModal();
                    alert('Please complete (or skip) the breathing exercise before submitting.');
                    return false;
                }

                // If high stress or not emotionally ready, show warning
                if (stressLevel.value === 'high' || emotionallyReady.value === '0') {
                    if (!confirm('You indicated high stress or not being emotionally ready. Are you sure you want to submit this?')) {
                        e.preventDefault();
                        return false;
                    }
                }

                return true;
            });

        });
    </script>
@endsection