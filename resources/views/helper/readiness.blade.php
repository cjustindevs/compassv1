@extends('layouts.helper')

@section('title', 'Readiness Check')
@section('heading', 'Readiness Check')
@section('subheading', 'Check in with yourself before accepting new sessions.')
@section('body-class', 'helper-readiness-page')

@section('styles')
    <style>
        /* ─────────── Compact status bar ─────────── */
        .rc-status {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border-radius: 10px; margin-bottom: 12px;
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
            background: white; border-radius: 12px; padding: 18px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 12px rgba(0,0,0,0.03);
            max-width: 720px;
        }
        .rc-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600; color: var(--gray-700);
            margin-bottom: 10px;
        }
        .rc-label .req { color: var(--red-500); }
        .helper-readiness-page .main-content .rc-card,
        .helper-readiness-page .main-content .rc-status,
        .helper-readiness-page .main-content .rc-foot-note { max-width:1040px; width:100%; }
        .helper-readiness-page .top-bar { margin-bottom:16px; }
        .rc-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px 20px; }
        .rc-field { min-width:0; padding:0; margin:0; border:0; }
        .rc-field .rc-label { margin-bottom:8px; }
        .rc-skills { grid-column:1/-1; padding-bottom:12px; border-bottom:1px solid var(--gray-100); }
        .rc-skills-reminder { font-size:12px; color:var(--gray-500); line-height:1.5; margin:0 0 8px; }
        .rc-breathing { grid-column:1/-1; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; padding-top:12px; border-top:1px solid var(--gray-100); }
        .rc-breathing .rc-label { margin-bottom:6px; }
        .rc-breathing .breath-steps { margin-bottom:0; }
        .rc-self-care { font-size:12px; color:var(--gray-500); line-height:1.5; margin-top:12px; }

        /* ─────────── Segmented radio control ─────────── */
        .seg { display: inline-flex; gap: 6px; flex-wrap: wrap; }
        .seg .seg-opt { display: inline-flex; position:relative; }
        .seg input[type="radio"] { position:absolute; opacity:0; width:1px; height:1px; }
        .seg label {
            display: inline-flex; flex-direction: column; align-items: center;
            gap: 1px; padding: 7px 12px; white-space:normal; min-height:44px; justify-content:center; text-align:center;
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
        .seg input[type="radio"]:focus-visible + label { outline:2px solid var(--green-500); outline-offset:2px; }
        .seg label .hint { font-size: 11px; font-weight: 400; color: var(--gray-400); }


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
        .breath-steps .step :is(i, .compass-icon) { color: var(--green-600); font-size: 10px; }
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
            margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--gray-200);
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
            .rc-card { padding: 16px; }
            .rc-fields { grid-template-columns:minmax(0,1fr); gap:12px; }
            .rc-breathing { align-items:stretch; }
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
                Dashboard <x-ui-icon name="arrow-right" class="ml-1" />
            </a>
        @endif
    </div>

    @if($readinessStatus === 'ready')
        <div class="rc-card">
            <div class="rc-ready">

                <h3>You're all set to help</h3>
                <p>
                    Your readiness assessment is valid for the current duty period (4 hours).<br>
                    You can check in again anytime to refresh your status.
                </p>
                <div class="flex justify-center gap-2 flex-wrap">
                    <a href="{{ route('helper.dashboard') }}" class="btn btn-primary">
                        <x-ui-icon name="arrow-right" class="mr-1" /> Go to Dashboard
                    </a>
                    <a href="{{ route('helper.readiness') }}?refresh=1" class="btn btn-secondary">
                        <x-ui-icon name="refresh" class="mr-1" /> Check In Again
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="rc-card">
            <form id="readinessForm" class="form-container" method="POST" action="{{ route('helper.readiness.store') }}">
                @csrf

                <input type="hidden" name="readiness_form_version" value="{{ \App\Services\HelperReadinessService::GROUPED_VERSION }}">
                <div class="rc-fields">
                    <fieldset class="rc-field rc-skills" aria-describedby="listener-skills-reminder">
                        <legend class="rc-label"><x-ui-icon name="check-circle" class="text-green-600" /> Prepared to use these listening skills? <span class="req">*</span></legend>
                        <p id="listener-skills-reminder" class="rc-skills-reminder">{{ implode(', ', array_map(fn($skill) => ucwords(str_replace('_',' ',$skill)), \App\Services\HelperReadinessService::SKILLS)) }}. This is a self-check for your next session, not a competency rating.</p>
                        <div class="seg">
                            @foreach(['1'=>"Yes, I'm prepared",'0'=>'Not right now'] as $value=>$label)
                            <div class="seg-opt"><input type="radio" id="listener_skills_{{ $value }}" name="listener_skills_ready" value="{{ $value }}" required @checked((string) old('listener_skills_ready','') === (string) $value)><label for="listener_skills_{{ $value }}">{{ $label }}</label></div>
                            @endforeach
                        </div>
                        @error('listener_skills_ready')<p class="error-text">{{ $message }}</p>@enderror
                    </fieldset>
                    <fieldset class="rc-field">
                        <legend class="rc-label"><x-ui-icon name="heart" class="text-red-500" /> Emotionally ready? <span class="req">*</span></legend>
                        <div class="seg">
                            @foreach(['1'=>"Yes, I'm ready",'0'=>'No, I need a moment'] as $value=>$label)
                            <div class="seg-opt"><input type="radio" id="emotionally_ready_{{ $value }}" name="emotionally_ready" value="{{ $value }}" required @checked((string) old('emotionally_ready','') === (string) $value)><label for="emotionally_ready_{{ $value }}">{{ $label }}</label></div>
                            @endforeach
                        </div>
                        @error('emotionally_ready')<p class="error-text">{{ $message }}</p>@enderror
                    </fieldset>
                    <fieldset class="rc-field">
                        <legend class="rc-label"><x-ui-icon name="connection" class="text-green-600" /> Willing to listen? <span class="req">*</span></legend>
                        <div class="seg">
                            @foreach(['1'=>'Yes','0'=>'No'] as $value=>$label)
                            <div class="seg-opt"><input type="radio" id="willing_{{ $value }}" name="willing_to_listen" value="{{ $value }}" required @checked((string) old('willing_to_listen','') === (string) $value)><label for="willing_{{ $value }}">{{ $label }}</label></div>
                            @endforeach
                        </div>
                        @error('willing_to_listen')<p class="error-text">{{ $message }}</p>@enderror
                    </fieldset>
                    <fieldset class="rc-field">
                        <legend class="rc-label"><x-ui-icon name="water" class="text-blue-500" /> Current stress level <span class="req">*</span></legend>
                        <div class="seg">
                            @foreach(['low'=>['Low','Calm and focused'],'moderate'=>['Moderate','Manageable'],'high'=>['High','Feeling overwhelmed']] as $value=>$option)
                            <div class="seg-opt"><input type="radio" id="stress_{{ $value }}" name="stress_level" value="{{ $value }}" required @checked(old('stress_level') === $value)><label for="stress_{{ $value }}">{{ $option[0] }}<span class="hint">{{ $option[1] }}</span></label></div>
                            @endforeach
                        </div>
                        @error('stress_level')<p class="error-text">{{ $message }}</p>@enderror
                    </fieldset>
                    <fieldset class="rc-field">
                        <legend class="rc-label"><x-ui-icon name="toggle" class="text-green-600" /> Availability preference <span class="req">*</span></legend>
                        <div class="seg">
                            @foreach(['available'=>['Available','Accepting sessions'],'not_ready'=>['Not ready','Not accepting now']] as $value=>$option)
                            <div class="seg-opt"><input type="radio" id="avail_{{ $value }}" name="availability_status" value="{{ $value }}" required @checked(old('availability_status') === $value)><label for="avail_{{ $value }}">{{ $option[0] }}<span class="hint">{{ $option[1] }}</span></label></div>
                            @endforeach
                        </div>
                        @error('availability_status')<p class="error-text">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="rc-field rc-breathing">
                        <div><p class="rc-label"><x-ui-icon name="wind" class="text-teal-600" /> Breathing exercise <span class="req">*</span></p>
                            <div class="breath-steps"><span>Inhale 4s / Hold 4s / Exhale 4s / 3 rounds</span></div>
                        </div>
                        <div class="breath-cta"><button type="button" class="btn btn-secondary btn-sm" id="openExerciseBtn"><x-ui-icon name="play" class="mr-1" /> Start exercise</button><span class="status-tag" id="breathStatusTag">Completed</span></div>
                        <input type="hidden" name="exercise_completed" id="exercise_completed" value="{{ old('exercise_completed','') }}">
                        @error('exercise_completed')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="rc-self-care">Not feeling ready is okay. You can still submit this check; a failed readiness condition keeps you unavailable for new sessions.</p>

                {{-- Actions --}}
                <div class="rc-actions">
                    <a href="{{ route('helper.dashboard') }}" class="back">
                        <x-ui-icon name="arrow-left" class="mr-1" /> Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <x-ui-icon name="check" class="mr-1" /> Submit Readiness
                    </button>
                </div>
            </form>

            {{-- Self-care resources --}}
            <div class="rc-care">
                <a href="{{ route('helper.self-help.breathing') }}" class="care-btn"><x-ui-icon name="wind"  /> Breathing</a>
                <a href="{{ route('helper.self-help.grounding') }}" class="care-btn"><x-ui-icon name="leaf"  /> Grounding</a>
                <a href="{{ route('helper.self-help.journal') }}" class="care-btn"><x-ui-icon name="book-open"  /> Journal</a>
                <a href="{{ route('helper.self-help.hotlines') }}" class="care-btn danger"><x-ui-icon name="phone"  /> Hotlines</a>
            </div>
        </div>

        <div class="rc-foot-note">

            Your well-being matters. Take care of yourself first.
        </div>
    @endif

    {{-- Breathing exercise modal --}}
    <div class="modal-overlay" id="exercise-modal">
        <div class="modal-box">
            <button type="button" class="modal-close" id="closeExerciseBtn" aria-label="Close"><x-ui-icon name="close"  /></button>
            <div style="text-align:center;">
                <h3 style="font-size:17px;font-weight:800;color:var(--gray-800);margin-bottom:4px;">Breathing Exercise</h3>
                <p style="font-size:12px;color:var(--gray-500);margin-bottom:8px;">3 rounds of inhale – hold – exhale</p>

                <div class="breath-circle" id="breath-circle"></div>
                <div id="breath-text" style="margin:0 0 4px;">Ready?</div>
                <p style="font-size:12px;color:var(--gray-400);margin-bottom:16px;">Round <span id="round-count">1</span> of 3</p>

                <div class="flex items-center justify-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="begin-lesson-btn">
                        <x-ui-icon name="play" class="mr-1" /> Begin Lesson
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="continue-btn" style="display:none;">
                        <x-ui-icon name="check" class="mr-1" /> Continue
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
            if (!form || !openBtn || !hiddenInput) return;

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
                openBtn.innerHTML = '<x-ui-icon name="check" class="mr-1" /> Breathing Done';
                openBtn.classList.remove('btn-secondary');
                openBtn.classList.add('breathing-done');
                if (statusTag) statusTag.style.display = 'inline-flex';
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });

            form.addEventListener('submit', function (e) {
                if (!form.reportValidity()) {
                    e.preventDefault();
                    return;
                }
                if (!hiddenInput.value) {
                    e.preventDefault();
                    openModal();
                }
                // Negative answers are valid submissions; the server records Not ready.
            });
        });
    </script>
@endsection
