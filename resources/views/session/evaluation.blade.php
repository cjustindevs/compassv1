<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Session Evaluation</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
            --green-100: #D0F0D8;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #163B2D;
            --gray-900: #111827;
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
        }

        body { background: #F8FBF9; }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4,160,82,0.06);
            box-shadow: 4px 0 40px rgba(0,0,0,0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .sidebar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(4,160,82,0.06);
            margin-bottom: 20px;
        }
        .sidebar .logo .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 20px;
            box-shadow: 0 4px 16px rgba(4,160,82,0.2);
        }
        .sidebar .logo span {
            font-weight: 700;
            font-size: 20px;
            color: var(--green-700);
        }

        .sidebar .nav { flex: 1; overflow-y: auto; }
        .sidebar .nav .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 2px;
        }
        .sidebar .nav .nav-item i { width: 20px; text-align: center; font-size: 16px; color: var(--gray-400); }
        .sidebar .nav .nav-item:hover { background: var(--green-50); color: var(--gray-800); }
        .sidebar .nav .nav-item.active {
            background: var(--green-50);
            color: var(--green-700);
            font-weight: 600;
        }
        .sidebar .nav .nav-item.active i { color: var(--green-500); }

        .sidebar .user-section {
            border-top: 1px solid rgba(4,160,82,0.06);
            padding-top: 16px;
            margin-top: auto;
        }
        .sidebar .user-section .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .user-section .user-card .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        .sidebar .user-section .user-card .info .name {
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-800);
        }
        .sidebar .user-section .user-card .info .role {
            font-size: 12px;
            color: var(--gray-400);
        }
        .sidebar .user-section .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            padding: 8px 12px;
            border-radius: 10px;
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
        }
        .sidebar .user-section .logout-btn:hover {
            background: #FEE2E2;
            color: #DC2626;
        }

        .main-content {
            margin-left: 260px;
            padding: 24px 40px 80px;
            min-height: 100vh;
        }

        .evaluation-card {
            background: white;
            border-radius: 24px;
            padding: 32px 36px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
            max-width: 3xl;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 4px;
        }

        .radio-option {
            flex: 1;
            min-width: 80px;
        }
        .radio-option input[type="radio"] { display: none; }
        .radio-option label {
            display: block;
            padding: 12px 16px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-200);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
            color: var(--gray-600);
        }
        .radio-option input[type="radio"]:checked + label {
            border-color: var(--green-500);
            background: var(--green-50);
            color: var(--green-700);
        }
        .radio-option label:hover {
            border-color: var(--green-300);
            background: var(--gray-50);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 14px 36px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 20px rgba(4,160,82,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(4,160,82,0.3);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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

        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            gap: 8px;
            justify-content: center;
        }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 32px;
            color: var(--gray-300);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #F59E0B;
        }

        .highlight-btn {
            padding: 8px 16px;
            border-radius: 30px;
            border: 1.5px solid var(--gray-200);
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-600);
        }
        .highlight-btn:hover {
            border-color: var(--green-300);
            background: var(--green-50);
        }
        .highlight-btn.active {
            border-color: var(--green-500);
            background: var(--green-50);
            color: var(--green-700);
        }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
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
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }

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
            background: rgba(0,0,0,0.25);
            z-index: 99;
        }
        .sidebar-overlay.active { display: block; }

        @media (min-width: 769px) {
            .sidebar-overlay { display: none !important; }
        }
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 80px; }
            .evaluation-card { padding: 24px 20px; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .evaluation-card { padding: 20px 16px; border-radius: 16px; }
            .radio-option { min-width: 100%; }
            .btn-primary, .btn-outline { padding: 12px 24px; font-size: 14px; width: 100%; justify-content: center; }
            .star-rating label { font-size: 28px; }
        }
        @media (max-width: 480px) {
            .evaluation-card { padding: 16px 12px; }
            .star-rating label { font-size: 24px; }
            .highlight-btn { padding: 6px 12px; font-size: 12px; }
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['session.evaluation*'],
        'role'   => 'Help Seeker',
    ])

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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Post-Session Evaluation</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Help us improve by sharing your experience.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- ─── EVALUATION CARD ─── -->
        <div class="evaluation-card">

            <!-- Session Details -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Session</span>
                    <p class="font-semibold text-gray-800">{{ $sessionId ?? '—' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Helper</span>
                    <p class="font-semibold text-gray-800">{{ $helperName ?? 'Peer Helper' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Duration</span>
                    <p class="font-semibold text-gray-800">{{ $duration ?? '—' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Language</span>
                    <p class="font-semibold text-gray-800">{{ $language ?? 'English' }}</p>
                </div>
            </div>

            <form id="evaluationForm" method="POST" action="{{ route('session.evaluation.process') }}">
                @csrf

                <!-- ============================================ -->
                <!-- SECTION 1: HELPFULNESS                      -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label">How helpful was the listener during your session? <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="helpful_very" name="helpfulness" value="very_helpful">
                            <label for="helpful_very">Very Helpful</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="helpful_helpful" name="helpfulness" value="helpful">
                            <label for="helpful_helpful">Helpful</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="helpful_neutral" name="helpfulness" value="neutral">
                            <label for="helpful_neutral">Neutral</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="helpful_not" name="helpfulness" value="not_helpful">
                            <label for="helpful_not">Not Helpful</label>
                        </div>
                    </div>
                    @error('helpfulness')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SECTION 2: COMFORT                          -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label">How comfortable did you feel during the conversation? <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="comfort_very" name="comfort" value="very_comfortable">
                            <label for="comfort_very">Very Comfortable</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="comfort_comfortable" name="comfort" value="comfortable">
                            <label for="comfort_comfortable">Comfortable</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="comfort_slightly" name="comfort" value="slightly_comfortable">
                            <label for="comfort_slightly">Slightly Comfortable</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="comfort_not" name="comfort" value="not_comfortable">
                            <label for="comfort_not">Not Comfortable</label>
                        </div>
                    </div>
                    @error('comfort')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SECTION 3: FEELING AFTER                    -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label">How do you feel after the session? <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="feeling_better" name="feeling" value="better">
                            <label for="feeling_better">Better</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="feeling_slightly" name="feeling" value="slightly_better">
                            <label for="feeling_slightly">Slightly Better</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="feeling_same" name="feeling" value="same">
                            <label for="feeling_same">The Same</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="feeling_worse" name="feeling" value="worse">
                            <label for="feeling_worse">Worse</label>
                        </div>
                    </div>
                    @error('feeling')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SECTION 4: UNDERSTOOD                       -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label">Did you feel that the listener understood you? <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="understood_yes" name="understood" value="yes">
                            <label for="understood_yes">Yes</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="understood_no" name="understood" value="no">
                            <label for="understood_no">No</label>
                        </div>
                    </div>
                    @error('understood')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SECTION 5: REUSE                            -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label">Would you use this service again? <span class="text-red-500">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="reuse_yes" name="reuse" value="yes">
                            <label for="reuse_yes">Yes</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="reuse_no" name="reuse" value="no">
                            <label for="reuse_no">No</label>
                        </div>
                    </div>
                    @error('reuse')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SECTION 6: STAR RATING                      -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label text-center block">Overall, how would you rate this session? <span class="text-red-500">*</span></label>
                    <div class="star-rating" id="starRating">
                        <input type="radio" id="star5" name="rating" value="5">
                        <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star4" name="rating" value="4">
                        <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star3" name="rating" value="3">
                        <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star2" name="rating" value="2">
                        <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star1" name="rating" value="1">
                        <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                    </div>
                    @error('rating')
                        <p class="text-red-500 text-sm mt-1 text-center">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SECTION 7: HIGHLIGHTS                       -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label">What stood out? <span class="text-gray-400 text-sm font-normal">(optional)</span></label>
                    <p class="text-sm text-gray-500 mb-2">Pick any that apply.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="highlight-btn" data-value="response_time">⏱ Response time</button>
                        <button type="button" class="highlight-btn" data-value="energy">⚡ Energy</button>
                        <button type="button" class="highlight-btn" data-value="active_listening">👂 Active listening</button>
                        <button type="button" class="highlight-btn" data-value="advice_quality">💡 Advice quality</button>
                        <button type="button" class="highlight-btn" data-value="session_length">📏 Session length</button>
                        <button type="button" class="highlight-btn" data-value="privacy_safety">🔒 Privacy & safety</button>
                        <button type="button" class="highlight-btn" data-value="follow_up_resources">📚 Follow-up resources</button>
                    </div>
                    <input type="hidden" name="highlights" id="highlightsInput" value="">
                </div>

                <!-- ============================================ -->
                <!-- SECTION 8: COMMENTS                         -->
                <!-- ============================================ -->
                <div class="mb-6">
                    <label class="form-label" for="comments">Additional comments or suggestions <span class="text-gray-400 text-sm font-normal">(optional)</span></label>
                    <textarea id="comments" name="comments" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all" rows="4" maxlength="500" placeholder="Anything else you'd like to share with us?"></textarea>
                    <div class="text-right text-xs text-gray-400 mt-1" id="commentCount">0 / 500</div>
                    @error('comments')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SUBMIT                                      -->
                <!-- ============================================ -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('seeker.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition font-medium text-sm">
                        <i class="fas fa-arrow-left mr-2"></i> Skip & Go to Dashboard
                    </a>
                    <button type="submit" class="btn-primary w-full sm:w-auto">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Feedback
                    </button>
                </div>

            </form>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Thank you for helping us improve.
        </div>

    </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT                                   -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ── Star Rating ──
            const starInputs = document.querySelectorAll('.star-rating input');

            // ── Highlights ──
            const highlightBtns = document.querySelectorAll('.highlight-btn');
            const highlightsInput = document.getElementById('highlightsInput');
            let selectedHighlights = [];

            highlightBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.classList.toggle('active');
                    const value = this.dataset.value;
                    if (this.classList.contains('active')) {
                        if (!selectedHighlights.includes(value)) {
                            selectedHighlights.push(value);
                        }
                    } else {
                        selectedHighlights = selectedHighlights.filter(v => v !== value);
                    }
                    highlightsInput.value = selectedHighlights.join(',');
                });
            });

            // ── Comment Counter ──
            const comments = document.getElementById('comments');
            const commentCount = document.getElementById('commentCount');

            comments.addEventListener('input', function() {
                const length = this.value.length;
                commentCount.textContent = length + ' / 500';
            });

            // ── Form Validation ──
            const form = document.getElementById('evaluationForm');

            form.addEventListener('submit', function(e) {
                const helpfulness = document.querySelector('input[name="helpfulness"]:checked');
                const comfort = document.querySelector('input[name="comfort"]:checked');
                const feeling = document.querySelector('input[name="feeling"]:checked');
                const understood = document.querySelector('input[name="understood"]:checked');
                const reuse = document.querySelector('input[name="reuse"]:checked');
                const rating = document.querySelector('input[name="rating"]:checked');

                if (!helpfulness || !comfort || !feeling || !understood || !reuse || !rating) {
                    e.preventDefault();
                    alert('Please answer all required questions before submitting.');
                    return;
                }
            });

        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
