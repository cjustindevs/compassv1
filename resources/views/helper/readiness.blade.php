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

        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-700);
            cursor: pointer;
            padding: 4px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
            z-index: 99;
        }
        .sidebar-overlay.active {
            display: block;
        }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }

        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0px;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
            transition: all 0.2s ease;
        }

        .bottom-nav .nav-item i {
            font-size: 20px;
        }

        .bottom-nav .nav-item.active {
            color: var(--green-500);
        }
        .bottom-nav .nav-item.active i {
            color: var(--green-500);
        }

        @media (min-width: 769px) {
            .sidebar-overlay {
                display: none !important;
            }
        }

        @media (max-width: 1024px) {
            .main-content {
                padding: 20px 24px 80px;
            }
            .form-card {
                padding: 24px 20px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 280px;
                padding: 16px;
            }
            .main-content {
                margin-left: 0;
                padding: 16px 16px 100px;
            }
            .hamburger {
                display: block;
            }
            .bottom-nav {
                display: flex;
            }
            .form-card {
                padding: 20px 16px;
                border-radius: 16px;
            }
            .radio-option {
                min-width: 100%;
            }
            .btn-primary,
            .btn-outline {
                padding: 12px 24px;
                font-size: 14px;
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .form-card {
                padding: 16px 12px;
            }
        }
    </style>
</head>
<body>

    <!-- ══════════════════════════════════════════════ -->
    <!-- SIDEBAR                                      -->
    <!-- ══════════════════════════════════════════════ -->

    @include('layouts.partials.helper-sidebar')

    <!-- ══════════════════════════════════════════════ -->
    <!-- SIDEBAR OVERLAY                              -->
    <!-- ══════════════════════════════════════════════ -->

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- MAIN CONTENT                                 -->
    <!-- ══════════════════════════════════════════════ -->

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Listener Readiness Check</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Take a moment to check in with yourself before accepting sessions.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- ─── FORM CARD ─── -->
        <div class="form-card">

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

    </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- BOTTOM NAVIGATION                            -->
    <!-- ══════════════════════════════════════════════ -->

    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('helper.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="#" class="nav-item active">
            <i class="fas fa-heartbeat"></i>
            <span>Readiness</span>
        </a>
        <a href="{{ route('helper.cases') }}" class="nav-item">
            <i class="fas fa-folder-open"></i>
            <span>Cases</span>
        </a>
        <a href="{{ route('helper.notifications') }}" class="nav-item">
            <i class="fas fa-bell"></i>
            <span>Alerts</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT                                   -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ── Sidebar Toggle ──
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburgerBtn');

            function toggleSidebar() {
                sidebar.classList.toggle('closed');
                overlay.classList.toggle('active');
            }

            function closeSidebar() {
                sidebar.classList.add('closed');
                overlay.classList.remove('active');
            }

            hamburger.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) closeSidebar();
            });

            // ── Bottom Nav Active State ──
            document.querySelectorAll('.bottom-nav .nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.bottom-nav .nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // ── Sidebar Nav Active State ──
            document.querySelectorAll('.sidebar .nav .nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.sidebar .nav .nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    if (window.innerWidth <= 768) closeSidebar();
                });
            });

            // ── Form Auto-submit prevention ──
            const form = document.getElementById('readinessForm');

            form.addEventListener('submit', function(e) {
                const emotionallyReady = document.querySelector('input[name="emotionally_ready"]:checked');
                const willingListen = document.querySelector('input[name="willing_to_listen"]:checked');
                const stressLevel = document.querySelector('input[name="stress_level"]:checked');
                const availability = document.querySelector('input[name="availability_status"]:checked');

                if (!emotionallyReady || !willingListen || !stressLevel || !availability) {
                    e.preventDefault();
                    alert('Please answer all questions before submitting.');
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

</body>
</html>