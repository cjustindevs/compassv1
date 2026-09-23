@extends('layouts.helper')

@section('title', 'Readiness Check')
@section('heading', 'Readiness Check')
@section('subheading', 'Check in with yourself before accepting new sessions.')

@section('styles')
    <style>
        /* ─────────── Compact status bar ─────────── */
        .rc-status {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; border-radius: 14px; margin-bottom: 16px;
            border: 1px solid var(--gray-200); background: white;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            max-width: 720px;
        }
        .rc-status .dot {
            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        }
        .rc-status.ready { border-left: 3px solid #22C55E; }
        .rc-status.ready .dot { background: #22C55E; }
        .rc-status.not_ready { border-left: 3px solid #EF4444; }
        .rc-status.not_ready .dot { background: #EF4444; }
        .rc-status.not_assessed { border-left: 3px solid #F59E0B; }
        .rc-status.not_assessed .dot { background: #F59E0B; }
        .rc-status .label { font-size: 13px; font-weight: 600; color: var(--gray-800); }
        .rc-status .meta { font-size: 12px; color: var(--gray-500); }
        .rc-status .action { margin-left: auto; }

        /* ─────────── Form card ─────────── */
        .rc-card {
            background: white; border-radius: 18px; padding: 24px 28px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 12px rgba(0,0,0,0.03);
            max-width: 720px;
        }
        .rc-section { padding: 18px 0; border-bottom: 1px solid var(--gray-100); }
        .rc-section:first-of-type { padding-top: 0; }
        .rc-section:last-of-type { border-bottom: none; padding-bottom: 0; }
        .rc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .rc-box {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 14px 16px;
        }
        .rc-box .rc-label { margin-bottom: 8px; }
        .rc-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600; color: var(--gray-700);
            margin-bottom: 10px;
        }
        .rc-label .req { color: var(--red-500); }

        /* ─────────── Segmented radio control ─────────── */
        .seg { display: inline-flex; gap: 6px; flex-wrap: wrap; }
        .seg .seg-opt { display: inline-flex; }
        .seg input[type="radio"] { display: none; }
        .seg label {
            display: inline-flex; flex-direction: column; align-items: center;
            gap: 1px; padding: 7px 14px; white-space: nowrap;
            border-radius: 9px;
            border: 1.5px solid var(--gray-200);
            background: var(--gray-50);
            font-size: 13px; font-weight: 600; color: var(--gray-600);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .seg label:hover { border-color: var(--green-400); background: white; }
        .seg input[type="radio"]:checked + label {
            border-color: var(--green-500);
            background: var(--green-600);
            color: white;
            box-shadow: 0 2px 8px rgba(4, 160, 82, 0.25);
        }
        .seg input[type="radio"]:checked + label .hint { color: rgba(255,255,255,0.85); }
        .seg label .hint { font-size: 11px; font-weight: 400; color: var(--gray-400); }

        /* Skill chips */
        .skill-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-chips label {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 7px 14px; border-radius: 999px;
            border: 1.5px solid var(--gray-200); background: white;
            font-size: 13px; font-weight: 500; color: var(--gray-600);
            cursor: pointer; transition: all 0.15s ease;
        }
        .skill-chips input[type="checkbox"] { display: none; }
        .skill-chips label:hover { border-color: var(--green-400); }
        .skill-chips input[type="checkbox"]:checked + label {
            border-color: var(--green-500); background: var(--green-50); color: var(--green-700); font-weight: 600;
        }

        /* ─────────── Breathing section ─────────── */
        .breath-steps {
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
            font-size: 12px; color: var(--gray-500); margin-bottom: 10px;
        }
        .breath-steps .step {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px;
            background: var(--gray-50); border: 1px solid var(--gray-200); color: var(--gray-600);
        }
        .breath-steps .step i { color: var(--green-600); font-size: 10px; }
        .breath-cta { display: flex; align-items: center; gap: 12px; }
        .breath-cta .status-tag { font-size: 12px; font-weight: 600; color: var(--green-700); display: none; }
        .breathing-done { background: var(--green-500) !important; color: white !important; }

        /* ─────────── Self-care resources ─────────── */
        .rc-care {
            display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px;
            padding-top: 18px; border-top: 1px solid var(--gray-100);
        }
        .rc-care .care-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 999px;
            background: white; border: 1px solid var(--gray-200);
            color: var(--gray-600); font-size: 12px; font-weight: 600;
            text-decoration: none; transition: all 0.15s ease;
        }
        .rc-care .care-btn:hover { border-color: var(--green-500); color: var(--green-700); background: var(--green-50); }
        .rc-care .care-btn.danger { color: var(--red-600); }
        .rc-care .care-btn.danger:hover { border-color: var(--red-500); background: #FEF2F2; }

        /* ─────────── Footer actions ─────────── */
        .rc-actions {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--gray-200);
        }
        .rc-actions .back { color: var(--gray-500); font-size: 13px; font-weight: 500; text-decoration: none; }
        .rc-actions .back:hover { color: var(--gray-700); }

        /* ─────────── Ready state ─────────── */
        .rc-ready { text-align: center; padding: 28px 8px; }
        .rc-ready .check {
            width: 64px; height: 64px; margin: 0 auto 14px; border-radius: 50%;
            background: var(--green-50); color: var(--green-600); font-size: 28px;
            display: flex; align-items: center; justify-content: center;
        }
        .rc-ready h3 { font-size: 18px; font-weight: 800; color: var(--gray-800); margin-bottom: 4px; }
        .rc-ready p { font-size: 13px; color: var(--gray-500); margin-bottom: 20px; }

        .error-text { color: var(--red-600); font-size: 12px; margin-top: 6px; }

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
            background: white; border-radius: 20px; width: 100%; max-width: 400px;
            max-height: 92vh; overflow-y: auto;
            position: relative; padding: 26px 24px 24px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.25);
            animation: modalSlide 0.25s ease-out;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-close {
            position: absolute; top: 12px; right: 12px;
            width: 30px; height: 30px; border-radius: 50%;
            border: none; background: var(--gray-100); color: var(--gray-500);
            font-size: 13px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        .modal-close:hover { background: var(--gray-200); color: var(--gray-800); }

        .breath-circle {
            width: 130px; height: 130px; border-radius: 50%;
            background: linear-gradient(135deg, #EAF8F0, #D0F0D8);
            margin: 16px auto; display: flex; align-items: center; justify-content: center;
            font-size: 36px; color: var(--green-700);
            transition: transform 1s ease, background 0.5s ease, border-color 0.5s ease;
            border: 4px solid #04A052;
        }
        .breath-circle.inhale { transform: scale(1.3); background: linear-gradient(135deg,#D0F0D8,#A8E0B0); border-color:#16A34A; }
        .breath-circle.hold { transform: scale(1.3); background: linear-gradient(135deg,#FEF3C7,#FDE68A); border-color:#F59E0B; color:#B45309; }
        .breath-circle.exhale { transform: scale(0.8); background: linear-gradient(135deg,#DBEAFE,#93C5FD); border-color:#3B82F6; color:#1D4ED8; }
        #breath-text { min-height: 24px; font-weight: 700; font-size: 16px; color: var(--gray-700); }

        .rc-foot-note {
            margin-top: 16px; text-align: center; font-size: 12px; color: var(--gray-400);
            max-width: 720px;
        }

        @media (max-width: 768px) {
            .rc-card { padding: 18px 16px; }
            .rc-grid { grid-template-columns: 1fr; }
            .seg { width: 100%; }
            .seg .seg-opt { flex: 1 1 44%; }
            .rc-status { flex-wrap: wrap; }
            .rc-status .action { width: 100%; }
            .rc-actions { flex-direction: column-reverse; align-items: stretch; }
            .rc-actions .btn { width: 100%; }
        }
    </style>
@endsection

@section('content')

    {{-- Compact status bar --}}
    <div class="rc-status {{ $readinessStatus }}">
        <span class="dot"></span>
        <div>
            <div class="label">
                @if($readinessStatus === 'ready')
                    Ready to help
                @elseif($readinessStatus === 'not_ready')
                    Not ready
                @else
                    Not assessed yet
                @endif
            </div>
            <div class="meta">
                @if($readinessStatus === 'ready' && $lastReadiness)
                    Valid until {{ $lastReadiness->valid_until->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                    (assessed {{ $lastReadiness->assessment_date->diffForHumans() }})
                @elseif(isset($latestCheck))
                    Last checked {{ $latestCheck->created_at->diffForHumans() }}
                @else
                    Complete the short check below to start taking sessions.
                @endif
            </div>
        </div>
        @if($readinessStatus === 'ready')
            <a href="{{ route('helper.dashboard') }}" class="btn btn-primary btn-sm action">
                Dashboard <i class="fas fa-arrow-right ml-1"></i>
            </a>
        @endif
    </div>

    @if($readinessStatus === 'ready')
        <div class="rc-card">
            <div class="rc-ready">
                <div class="check"><i class="fas fa-check"></i></div>
                <h3>You're all set to help</h3>
                <p>
                    Your readiness assessment is valid for the current duty period (4 hours).<br>
                    You can check in again anytime to refresh your status.
                </p>
                <div class="flex justify-center gap-2 flex-wrap">
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
        <div class="rc-card">
            <form id="readinessForm" class="form-container" method="POST" action="{{ route('helper.readiness.store') }}">
                @csrf

                {{-- Skills --}}
                <div class="rc-box" style="margin-bottom:14px;">
                    <label class="rc-label"><i class="fas fa-circle-check text-green-600"></i> Listener skills <span class="req">*</span></label>
                    <div class="skill-chips">
                        @foreach(\App\Services\HelperReadinessService::SKILLS as $skill)
                            <input type="checkbox" id="skill_{{ $loop->index }}" name="skills_confirmed[]" value="{{ $skill }}" @checked(in_array($skill,old('skills_confirmed',[])))>
                            <label for="skill_{{ $loop->index }}">{{ ucwords(str_replace('_',' ',$skill)) }}</label>
                        @endforeach
                    </div>
                    @error('skills_confirmed')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                {{-- 1. Emotional readiness + 2. Willingness --}}
                <div class="rc-grid">
                    <div class="rc-box">
                        <label class="rc-label"><i class="fas fa-heart text-red-500"></i> Emotionally ready? <span class="req">*</span></label>
                        <div class="seg">
                            <div class="seg-opt">
                                <input type="radio" id="emotionally_ready_yes" name="emotionally_ready" value="1" {{ old('emotionally_ready') == '1' ? 'checked' : '' }}>
                                <label for="emotionally_ready_yes">Yes, I'm ready</label>
                            </div>
                            <div class="seg-opt">
                                <input type="radio" id="emotionally_ready_no" name="emotionally_ready" value="0" {{ old('emotionally_ready') == '0' ? 'checked' : '' }}>
                                <label for="emotionally_ready_no">No, I need a moment</label>
                            </div>
                        </div>
                        @error('emotionally_ready')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <div class="rc-box">
                        <label class="rc-label"><i class="fas fa-handshake text-green-600"></i> Willing to listen <span class="req">*</span></label>
                        <div class="seg">
                            <div class="seg-opt">
                                <input type="radio" id="willing_yes" name="willing_to_listen" value="1" {{ old('willing_to_listen') == '1' ? 'checked' : '' }}>
                                <label for="willing_yes">Yes</label>
                            </div>
                            <div class="seg-opt">
                                <input type="radio" id="willing_no" name="willing_to_listen" value="0" {{ old('willing_to_listen') == '0' ? 'checked' : '' }}>
                                <label for="willing_no">No</label>
                            </div>
                        </div>
                        @error('willing_to_listen')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- 3. Stress level --}}
                <div class="rc-section">
                    <label class="rc-label"><i class="fas fa-water text-blue-500"></i> My current stress level is <span class="req">*</span></label>
                    <div class="seg">
                        <div class="seg-opt">
                            <input type="radio" id="stress_low" name="stress_level" value="low" {{ old('stress_level') == 'low' ? 'checked' : '' }}>
                            <label for="stress_low">Low <span class="hint">Calm and focused</span></label>
                        </div>
                        <div class="seg-opt">
                            <input type="radio" id="stress_moderate" name="stress_level" value="moderate" {{ old('stress_level') == 'moderate' ? 'checked' : '' }}>
                            <label for="stress_moderate">Moderate <span class="hint">Manageable</span></label>
                        </div>
                        <div class="seg-opt">
                            <input type="radio" id="stress_high" name="stress_level" value="high" {{ old('stress_level') == 'high' ? 'checked' : '' }}>
                            <label for="stress_high">High <span class="hint">Feeling overwhelmed</span></label>
                        </div>
                    </div>
                    @error('stress_level')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                {{-- 4. Availability + 5. Breathing exercise --}}
                <div class="rc-grid">
                    <div class="rc-box">
                        <label class="rc-label"><i class="fas fa-toggle-on text-green-600"></i> Availability status <span class="req">*</span></label>
                        <div class="seg">
                            <div class="seg-opt">
                                <input type="radio" id="avail_available" name="availability_status" value="available" {{ old('availability_status') == 'available' ? 'checked' : '' }}>
                                <label for="avail_available">Available <span class="hint">Accepting sessions</span></label>
                            </div>
                            <div class="seg-opt">
                                <input type="radio" id="avail_not_ready" name="availability_status" value="not_ready" {{ old('availability_status') == 'not_ready' ? 'checked' : '' }}>
                                <label for="avail_not_ready">Not ready <span class="hint">Not accepting now</span></label>
                            </div>
                        </div>
                        @error('availability_status')<p class="error-text">{{ $message }}</p>@enderror
                    </div>

                    <div class="rc-box">
                        <label class="rc-label"><i class="fas fa-wind text-teal-600"></i> Breathing exercise <span class="req">*</span></label>
                        <div class="breath-steps">
                            <span class="step"><i class="fas fa-circle"></i> Inhale 4s</span>
                            <span class="step"><i class="fas fa-circle"></i> Hold 4s</span>
                            <span class="step"><i class="fas fa-circle"></i> Exhale 4s</span>
                            <span class="step"><i class="fas fa-circle"></i> 3 rounds</span>
                        </div>
                        <div class="breath-cta">
                            <button type="button" class="btn btn-secondary btn-sm" id="openExerciseBtn">
                                <i class="fas fa-play mr-1"></i> Start Breathing Exercise
                            </button>
                            <span class="status-tag" id="breathStatusTag"><i class="fas fa-check"></i> Completed</span>
                        </div>
                        <input type="hidden" name="exercise_completed" id="exercise_completed" value="{{ old('exercise_completed', '') }}">
                        @error('exercise_completed')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Helper message --}}
                <div class="rc-box">
                    <p class="text-sm text-gray-600 leading-relaxed mb-0" style="font-size:12px;">
                        <i class="fas fa-hand-holding-heart text-green-500 mr-1"></i>
                        <span class="font-semibold text-gray-700">You can't pour from an empty cup.</span>
                        If you're not feeling ready, that's okay — take a break and try a self-care tool below.
                    </p>
                </div>

                {{-- Actions --}}
                <div class="rc-actions">
                    <a href="{{ route('helper.dashboard') }}" class="back">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check mr-1"></i> Submit Readiness
                    </button>
                </div>
            </form>

            {{-- Self-care resources --}}
            <div class="rc-care">
                <a href="{{ route('helper.self-help.breathing') }}" class="care-btn"><i class="fas fa-wind"></i> Breathing</a>
                <a href="{{ route('helper.self-help.grounding') }}" class="care-btn"><i class="fas fa-tree"></i> Grounding</a>
                <a href="{{ route('helper.self-help.journal') }}" class="care-btn"><i class="fas fa-book-open"></i> Journal</a>
                <a href="{{ route('helper.self-help.hotlines') }}" class="care-btn danger"><i class="fas fa-phone-alt"></i> Hotlines</a>
            </div>
        </div>

        <div class="rc-foot-note">
            <i class="fas fa-heart mr-1" style="color:#04A052;"></i>
            Your well-being matters. Take care of yourself first.
        </div>
    @endif

    {{-- Breathing exercise modal --}}
    <div class="modal-overlay" id="exercise-modal">
        <div class="modal-box">
            <button type="button" class="modal-close" id="closeExerciseBtn" aria-label="Close"><i class="fas fa-times"></i></button>
            <div style="text-align:center;">
                <h3 style="font-size:17px;font-weight:800;color:var(--gray-800);margin-bottom:4px;">Breathing Exercise</h3>
                <p style="font-size:12px;color:var(--gray-500);margin-bottom:8px;">3 rounds of inhale – hold – exhale</p>

                <div class="breath-circle" id="breath-circle"><i class="fas fa-wind"></i></div>
                <div id="breath-text" style="margin:0 0 4px;">Ready?</div>
                <p style="font-size:12px;color:var(--gray-400);margin-bottom:16px;">Round <span id="round-count">1</span> of 3</p>

                <div class="flex items-center justify-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="begin-lesson-btn">
                        <i class="fas fa-play mr-1"></i> Begin Lesson
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="continue-btn" style="display:none;">
                        <i class="fas fa-check mr-1"></i> Continue
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="skipExerciseBtn">
                        Skip
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
            const statusTag = document.getElementById('breathStatusTag');

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
                openBtn.classList.remove('btn-secondary');
                openBtn.classList.add('breathing-done');
                if (statusTag) statusTag.style.display = 'inline-flex';
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });

            form.addEventListener('submit', function (e) {
                const emotionallyReady = document.querySelector('input[name="emotionally_ready"]:checked');
                const willingListen = document.querySelector('input[name="willing_to_listen"]:checked');
                const stressLevel = document.querySelector('input[name="stress_level"]:checked');
                const availability = document.querySelector('input[name="availability_status"]:checked');
                const skills = document.querySelectorAll('input[name="skills_confirmed[]"]:checked');

                if (skills.length < 5) {
                    e.preventDefault();
                    alert('Please confirm all five listener skills before submitting.');
                    return false;
                }

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