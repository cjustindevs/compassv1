@extends('layouts.helper')

@section('title', 'Readiness Check')
@section('heading', 'Readiness Check')
@section('subheading', 'Check in with yourself before accepting new sessions.')

@section('styles')
    <style>
        /* ─────────── Page intro ─────────── */
        .readiness-hero {
            background: linear-gradient(135deg, #038A45, #04A052);
            border-radius: 20px;
            padding: 28px 32px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            box-shadow: 0 8px 30px rgba(4, 160, 82, 0.25);
        }
        .readiness-hero .hero-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: rgba(255, 255, 255, 0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; flex-shrink: 0;
        }
        .readiness-hero h2 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .readiness-hero p { font-size: 14px; opacity: 0.9; }

        /* ─────────── Status banner ─────────── */
        .status-banner {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
            padding: 18px 22px; border-radius: 18px; margin-bottom: 24px;
            border: 1.5px solid var(--gray-200); background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        }
        .status-banner.ready { border-color: #22C55E; background: #F0FDF4; }
        .status-banner.not_ready { border-color: #F87171; background: #FEF2F2; }
        .status-banner.not_assessed { border-color: #FBBF24; background: #FFFBEB; }
        .status-banner .status-icon {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .status-banner.ready .status-icon { color: #16A34A; }
        .status-banner.not_ready .status-icon { color: #DC2626; }
        .status-banner.not_assessed .status-icon { color: #D97706; }
        .status-banner .status-text h4 { font-size: 16px; font-weight: 700; color: var(--gray-800); }
        .status-banner .status-text p { font-size: 13px; color: var(--gray-500); }

        /* ─────────── Form card ─────────── */
        .form-card {
            background: white;
            border-radius: 24px;
            padding: 32px 36px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.01);
        }
        .form-section { margin-bottom: 28px; }
        .form-section:last-of-type { margin-bottom: 0; }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-700);
            margin-bottom: 10px;
        }

        .radio-group { display: flex; flex-wrap: wrap; gap: 12px; }
        .radio-option { flex: 1; min-width: 120px; }
        .radio-option input[type="radio"] { display: none; }
        .radio-option label {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 6px;
            padding: 16px 18px;
            border-radius: 16px;
            border: 2px solid var(--gray-200);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-600);
            background: white;
            height: 100%;
        }
        .radio-option label .opt-icon { font-size: 22px; }
        .radio-option input[type="radio"]:checked + label {
            border-color: var(--green-500);
            background: var(--green-50);
            color: var(--green-700);
            box-shadow: 0 0 0 3px rgba(4, 160, 82, 0.12);
        }
        .radio-option.negative input[type="radio"]:checked + label {
            border-color: var(--red-500);
            background: #FEF2F2;
            color: var(--red-600);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
        }
        .radio-option.warn input[type="radio"]:checked + label {
            border-color: #F59E0B;
            background: #FFFBEB;
            color: #B45309;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
        }
        .radio-option label:hover { border-color: var(--green-300); background: var(--gray-50); }
        .radio-option .sub-text {
            font-size: 12px;
            font-weight: 400;
            color: var(--gray-400);
        }
        .error-text { color: var(--red-600); font-size: 13px; margin-top: 6px; }

        /* ─────────── Breathing step card ─────────── */
        .step-card {
            background: linear-gradient(135deg, #F0FDF4, #EAF8F0);
            border: 1px solid #BBF7D0; border-radius: 16px; padding: 16px 18px;
        }
        .step-card ol { list-style: none; padding: 0; margin: 0; counter-reset: bs; }
        .step-card ol li {
            counter-increment: bs; display: flex; align-items: center; gap: 12px;
            font-size: 13px; color: var(--gray-700); padding: 5px 0;
        }
        .step-card ol li::before {
            content: counter(bs); width: 24px; height: 24px; border-radius: 50%;
            background: var(--green-500); color: white; font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .breathing-done {
            background: var(--green-500) !important;
            color: white !important;
            border-color: var(--green-600) !important;
        }

        /* ─────────── Self-help strip ─────────── */
        .selfhelp-strip {
            margin-top: 28px; padding: 20px 22px; border-radius: 18px;
            background: #F0FDF4; border: 1.5px solid #BBF7D0;
        }
        .selfhelp-strip h5 { font-size: 14px; font-weight: 700; color: var(--green-700); margin-bottom: 12px; }
        .selfhelp-strip .tools { display: flex; flex-wrap: wrap; gap: 10px; }
        .selfhelp-strip .tool-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 16px; border-radius: 12px;
            background: white; border: 1.5px solid var(--green-200);
            color: var(--green-700); font-weight: 600; font-size: 13px;
            text-decoration: none; transition: all 0.2s ease;
        }
        .selfhelp-strip .tool-btn:hover { border-color: var(--green-500); background: var(--green-50); }
        .selfhelp-strip .tool-btn.danger { border-color: #FECACA; color: var(--red-600); }
        .selfhelp-strip .tool-btn.danger:hover { background: #FEF2F2; border-color: var(--red-500); }

        /* ─────────── Modal ─────────── */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 999;
            display: none; align-items: center; justify-content: center;
            background: rgba(15, 40, 30, 0.45);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white; border-radius: 24px; width: 100%; max-width: 460px;
            max-height: 92vh; overflow-y: auto;
            position: relative; padding: 32px 28px 28px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.25);
            animation: modalSlide 0.25s ease-out;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-close {
            position: absolute; top: 14px; right: 14px;
            width: 32px; height: 32px; border-radius: 50%;
            border: none; background: var(--gray-100); color: var(--gray-500);
            font-size: 14px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        .modal-close:hover { background: var(--gray-200); color: var(--gray-800); }

        /* ─────────── Breathing circle ─────────── */
        .breath-circle {
            width: 160px; height: 160px; border-radius: 50%;
            background: linear-gradient(135deg, #EAF8F0, #D0F0D8);
            margin: 20px auto; display: flex; align-items: center; justify-content: center;
            font-size: 44px; color: var(--green-700);
            transition: transform 1s ease, background 0.5s ease, border-color 0.5s ease;
            border: 4px solid #04A052;
        }
        .breath-circle.inhale { transform: scale(1.3); background: linear-gradient(135deg,#D0F0D8,#A8E0B0); border-color:#16A34A; }
        .breath-circle.hold { transform: scale(1.3); background: linear-gradient(135deg,#FEF3C7,#FDE68A); border-color:#F59E0B; color:#B45309; }
        .breath-circle.exhale { transform: scale(0.8); background: linear-gradient(135deg,#DBEAFE,#93C5FD); border-color:#3B82F6; color:#1D4ED8; }
        #breath-text { min-height: 30px; font-weight: 700; font-size: 17px; color: var(--gray-700); }

        .footer-note {
            margin-top: 24px; text-align: center; font-size: 13px; color: var(--gray-400);
        }

        @media (max-width: 768px) {
            .form-card { padding: 20px 18px; }
            .readiness-hero { padding: 20px; }
            .radio-option { min-width: 100%; }
        }
    </style>
@endsection

@section('content')

    {{-- Intro hero --}}
    <div class="readiness-hero">
        <div class="hero-icon"><i class="fas fa-heartbeat"></i></div>
        <div>
            <h2>Before you help others, check in with yourself</h2>
            <p>Take a minute to make sure you're in the right space to listen. Your well-being matters.</p>
        </div>
    </div>

    {{-- Status banner --}}
    <div class="status-banner {{ $readinessStatus }}">
        <div class="flex items-center gap-3">
            <div class="status-icon">
                @if($readinessStatus === 'ready')
                    <i class="fas fa-check-circle"></i>
                @elseif($readinessStatus === 'not_ready')
                    <i class="fas fa-times-circle"></i>
                @else
                    <i class="fas fa-hourglass-half"></i>
                @endif
            </div>
            <div class="status-text">
                <h4>
                    @if($readinessStatus === 'ready')
                        Ready to Help
                    @elseif($readinessStatus === 'not_ready')
                        Not Ready
                    @else
                        Not Assessed Yet
                    @endif
                </h4>
                <p>
                    @if($readinessStatus === 'ready' && $lastReadiness)
                        Valid until {{ $lastReadiness->valid_until->format('M d, Y h:i A') }}
                        (assessed {{ $lastReadiness->assessment_date->diffForHumans() }})
                    @elseif(isset($latestCheck))
                        Last checked {{ $latestCheck->created_at->diffForHumans() }} — complete a new check to take sessions.
                    @else
                        Complete the short check below to start taking sessions.
                    @endif
                </p>
            </div>
        </div>
        @if($readinessStatus === 'ready')
            <a href="{{ route('helper.dashboard') }}" class="btn btn-primary">
                <i class="fas fa-arrow-right mr-1"></i> Go to Dashboard
            </a>
        @endif
    </div>

    {{-- When ready, show confirmation and stop --}}
    @if($readinessStatus === 'ready')
        <div class="form-card">
            <div class="text-center py-4">
                <div style="font-size:64px;color:#22C55E;"><i class="fas fa-check-circle"></i></div>
                <h3 class="mt-3 mb-2" style="font-size:20px;font-weight:800;color:var(--gray-800);">You're all set to help</h3>
                <p class="text-center" style="color:var(--gray-500);font-size:14px;">
                    Your readiness assessment is valid for the current duty period (4 hours).<br>
                    You can check in again anytime to refresh your status.
                </p>
                <div class="mt-4 flex justify-center gap-2 flex-wrap">
                    <a href="{{ route('helper.dashboard') }}" class="btn btn-primary">
                        <i class="fas fa-arrow-right mr-1"></i> Go to Dashboard
                    </a>
                    <a href="{{ route('helper.readiness') }}?refresh=1" class="btn btn-secondary">
                        <i class="fas fa-sync mr-1"></i> Check In Again
                    </a>
                </div>
            </div>
        </div>
    @else
        {{-- Readiness form --}}
        <div class="form-card">
            <form id="readinessForm" class="form-container" method="POST" action="{{ route('helper.readiness.store') }}">
                @csrf

                {{-- 1. Emotional readiness --}}
                <div class="form-section">
                    <label class="form-label"><i class="fas fa-heart text-red-500 mr-1"></i> I feel emotionally ready to listen right now <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="emotionally_ready_yes" name="emotionally_ready" value="1" {{ old('emotionally_ready') == '1' ? 'checked' : '' }}>
                            <label for="emotionally_ready_yes">
                                <span class="opt-icon"><i class="fas fa-heart" aria-hidden="true"></i></span>
                                <span>Yes, I'm ready</span>
                            </label>
                        </div>
                        <div class="radio-option negative">
                            <input type="radio" id="emotionally_ready_no" name="emotionally_ready" value="0" {{ old('emotionally_ready') == '0' ? 'checked' : '' }}>
                            <label for="emotionally_ready_no">
                                <span class="opt-icon"><i class="fas fa-hand" aria-hidden="true"></i></span>
                                <span>No, I need a moment</span>
                            </label>
                        </div>
                    </div>
                    @error('emotionally_ready')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                {{-- 2. Willingness --}}
                <div class="form-section">
                    <label class="form-label"><i class="fas fa-handshake text-green-600 mr-1"></i> I am willing to listen without judgment <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="willing_yes" name="willing_to_listen" value="1" {{ old('willing_to_listen') == '1' ? 'checked' : '' }}>
                            <label for="willing_yes">
                                <span class="opt-icon"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                                <span>Yes</span>
                            </label>
                        </div>
                        <div class="radio-option negative">
                            <input type="radio" id="willing_no" name="willing_to_listen" value="0" {{ old('willing_to_listen') == '0' ? 'checked' : '' }}>
                            <label for="willing_no">
                                <span class="opt-icon"><i class="fas fa-ban" aria-hidden="true"></i></span>
                                <span>No</span>
                            </label>
                        </div>
                    </div>
                    @error('willing_to_listen')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                {{-- 3. Stress level --}}
                <div class="form-section">
                    <label class="form-label"><i class="fas fa-water text-blue-500 mr-1"></i> My current stress level is <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="stress_low" name="stress_level" value="low" {{ old('stress_level') == 'low' ? 'checked' : '' }}>
                            <label for="stress_low">
                                <span class="opt-icon"><i class="fas fa-face-smile" aria-hidden="true"></i></span>
                                <span>Low</span>
                                <span class="sub-text">Calm and focused</span>
                            </label>
                        </div>
                        <div class="radio-option warn">
                            <input type="radio" id="stress_moderate" name="stress_level" value="moderate" {{ old('stress_level') == 'moderate' ? 'checked' : '' }}>
                            <label for="stress_moderate">
                                <span class="opt-icon"><i class="fas fa-face-meh" aria-hidden="true"></i></span>
                                <span>Moderate</span>
                                <span class="sub-text">Manageable</span>
                            </label>
                        </div>
                        <div class="radio-option negative">
                            <input type="radio" id="stress_high" name="stress_level" value="high" {{ old('stress_level') == 'high' ? 'checked' : '' }}>
                            <label for="stress_high">
                                <span class="opt-icon"><i class="fas fa-face-frown" aria-hidden="true"></i></span>
                                <span>High</span>
                                <span class="sub-text">Feeling overwhelmed</span>
                            </label>
                        </div>
                    </div>
                    @error('stress_level')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                {{-- 4. Availability --}}
                <div class="form-section">
                    <label class="form-label"><i class="fas fa-toggle-on text-green-600 mr-1"></i> Availability Status <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="avail_available" name="availability_status" value="available" {{ old('availability_status') == 'available' ? 'checked' : '' }}>
                            <label for="avail_available">
                                <span class="opt-icon"><i class="fas fa-circle" aria-hidden="true"></i></span>
                                <span>Available</span>
                                <span class="sub-text">Ready to accept sessions</span>
                            </label>
                        </div>
                        <div class="radio-option negative">
                            <input type="radio" id="avail_not_ready" name="availability_status" value="not_ready" {{ old('availability_status') == 'not_ready' ? 'checked' : '' }}>
                            <label for="avail_not_ready">
                                <span class="opt-icon"><i class="fas fa-circle" aria-hidden="true"></i></span>
                                <span>Not Ready</span>
                                <span class="sub-text">Not accepting right now</span>
                            </label>
                        </div>
                    </div>
                    @error('availability_status')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                {{-- 5. Breathing exercise --}}
                <div class="form-section">
                    <label class="form-label"><i class="fas fa-wind text-teal-600 mr-1"></i> Breathing Exercise <span class="text-red-500">*</span></label>
                    <div class="step-card mb-3">
                        <ol>
                            <li>Find a quiet spot and sit comfortably.</li>
                            <li>Click "Start Breathing Exercise" below.</li>
                            <li>Follow the circle: inhale 4s, hold 4s, exhale 4s — 3 rounds.</li>
                            <li>Press "Continue" once finished (or skip if you prefer).</li>
                        </ol>
                    </div>
                    <button type="button" class="btn btn-primary" id="openExerciseBtn" style="width:100%;">
                        <i class="fas fa-play mr-1"></i> Start Breathing Exercise
                    </button>
                    <input type="hidden" name="exercise_completed" id="exercise_completed" value="{{ old('exercise_completed', '') }}">
                    @error('exercise_completed')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                {{-- Helper message --}}
                <div class="mb-6 p-4 rounded-xl" style="background:var(--gray-50);border:1px solid var(--gray-200);">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-hand-holding-heart text-green-500 text-lg mt-0.5"></i>
                        <p class="text-sm text-gray-700 leading-relaxed mb-0">
                            <span class="font-semibold">You can't pour from an empty cup.</span><br>
                            If you're not feeling ready, that's okay — take a break and try the self-care tools below.
                        </p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="pt-4 border-t flex flex-col sm:flex-row items-center justify-between gap-3"
                     style="border-color:var(--gray-200)!important;">
                    <a href="{{ route('helper.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition font-medium text-sm no-underline">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary px-4" style="width:100%;max-width:220px;">
                        <i class="fas fa-check mr-2"></i> Submit Readiness
                    </button>
                </div>
            </form>

            {{-- Self-help tools for not-ready helpers --}}
            @if($readinessStatus !== 'ready')
                <div class="selfhelp-strip">
                    <h5><i class="fas fa-heart mr-1"></i> Need a moment? Try a self-care tool</h5>
                    <div class="tools">
                        <a href="{{ route('helper.self-help.breathing') }}" class="tool-btn"><i class="fas fa-wind"></i> Breathing</a>
                        <a href="{{ route('helper.self-help.grounding') }}" class="tool-btn"><i class="fas fa-tree"></i> Grounding</a>
                        <a href="{{ route('helper.self-help.journal') }}" class="tool-btn"><i class="fas fa-book-open"></i> Journal</a>
                        <a href="{{ route('helper.self-help.hotlines') }}" class="tool-btn danger"><i class="fas fa-phone-alt"></i> Hotlines</a>
                    </div>
                </div>
            @endif
        </div>

        <div class="footer-note">
            <i class="fas fa-heart mr-1" style="color:#04A052;"></i>
            Your well-being matters. Take care of yourself first.
        </div>
    @endif

    {{-- Breathing exercise modal --}}
    <div class="modal-overlay" id="exercise-modal">
        <div class="modal-box">
            <button type="button" class="modal-close" id="closeExerciseBtn" aria-label="Close"><i class="fas fa-times"></i></button>
            <div style="text-align:center;">
                <h3 style="font-size:18px;font-weight:800;color:var(--gray-800);margin-bottom:4px;">Breathing Exercise</h3>
                <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">3 rounds of inhale – hold – exhale</p>

                <div class="breath-circle" id="breath-circle"><i class="fas fa-wind"></i></div>
                <div id="breath-text" style="margin:0 0 4px;">Ready?</div>
                <p style="font-size:12px;color:var(--gray-400);margin-bottom:20px;">Round <span id="round-count">1</span> of 3</p>

                <div class="flex flex-col gap-2">
                    <button type="button" class="btn btn-primary" id="begin-lesson-btn" style="width:100%;">
                        <i class="fas fa-play mr-1"></i> Begin Lesson
                    </button>
                    <button type="button" class="btn btn-primary" id="continue-btn" style="width:100%;display:none;">
                        <i class="fas fa-check mr-1"></i> Continue
                    </button>
                    <button type="button" class="btn btn-secondary" id="skipExerciseBtn" style="width:100%;">
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

            function openModal() { modal.classList.add('active'); resetExercise(); }

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
                        breathText.textContent = 'Great job! ';
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

                // Reflect completion on the start button
                openBtn.innerHTML = '<i class="fas fa-check mr-1"></i> Breathing Done';
                openBtn.classList.add('breathing-done');
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

                if (stressLevel.value === 'high' || emotionallyReady.value === '0') {
                    if (!confirm('You indicated high stress or not being emotionally ready. You\'ll be directed to self-care tools. Continue?')) {
                        e.preventDefault();
                        return false;
                    }
                }

                return true;
            });
        });
    </script>
@endsection

