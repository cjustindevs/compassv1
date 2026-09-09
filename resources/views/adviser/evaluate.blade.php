@extends('layouts.app')

@section('title', 'COMPASS – Evaluate Helper')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .form-card {
            background: white;
            border-radius: 24px;
            padding: 32px 36px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 14px;
            border: 1.5px solid #e5e7eb;
            outline: none;
            transition: all 0.2s ease;
            background: white;
            font-size: 14px;
        }
        .form-input:focus { border-color: #04A052; box-shadow: 0 0 0 3px rgba(4,160,82,0.08); }

        .rating-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rating-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            font-size: 16px;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rating-btn:hover { border-color: #04A052; background: #EAF8F0; }
        .rating-btn.active { border-color: #04A052; background: #EAF8F0; color: #04A052; }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
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
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(4,160,82,0.3); }

        .btn-outline {
            background: transparent;
            color: #6b7280;
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 50px;
            border: 1.5px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-outline:hover { background: #f3f4f6; }

        .score-display {
            background: #f8fbf9;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }

        .competency-level {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }
        .competency-level.outstanding { background: #dcfce7; color: #166534; }
        .competency-level.very-good { background: #dbeafe; color: #1e40af; }
        .competency-level.satisfactory { background: #fef3c7; color: #92400e; }
        .competency-level.needs-improvement { background: #fee2e2; color: #991b1b; }
        .competency-level.unsatisfactory { background: #fee2e2; color: #991b1b; }

        .risk-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .risk-badge.low { background: #dcfce7; color: #166534; }
        .risk-badge.moderate { background: #fef3c7; color: #92400e; }
        .risk-badge.high { background: #fee2e2; color: #991b1b; }
        .risk-badge.emergency { background: #fee2e2; color: #991b1b; }

        @media (max-width: 768px) {
            .form-card { padding: 20px 16px; border-radius: 16px; }
            .btn-primary, .btn-outline { padding: 12px 24px; font-size: 14px; width: 100%; justify-content: center; }
            .rating-btn { width: 38px; height: 38px; font-size: 14px; }
        }
        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        @media (max-width: 480px) {
            .form-card { padding: 16px 12px; }
            .rating-btn { width: 34px; height: 34px; font-size: 13px; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center gap-4 mb-6">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Evaluate Helper</h1>
                <p class="text-sm text-gray-500 hidden sm:block">
                    Competency evaluation form
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
        @endif

        <!-- Back Link -->
        <a href="{{ route('adviser.evaluations') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Evaluations
        </a>

        <div class="form-card">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Competency Evaluation</h1>
                    <p class="text-sm text-gray-500">Evaluate helper performance using the competency rubric</p>
                </div>
                <div>
                    <span class="risk-badge {{ $report->session->risk_level ?? 'low' }}">
                        {{ ucfirst($report->session->risk_level ?? 'Low') }} Risk
                    </span>
                </div>
            </div>

            <!-- Session Info -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-xl mb-6 text-sm">
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Seeker</span>
                    <p class="font-medium text-gray-800">{{ $report->session->seeker->generated_alias ?? 'Anonymous' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Helper</span>
                    <p class="font-medium text-gray-800">{{ $report->session->helper->first_name ?? 'Unknown' }} {{ $report->session->helper->last_name ?? '' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Date</span>
                    <p class="font-medium text-gray-800">{{ $report->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Concern</span>
                    <p class="font-medium text-gray-800">{{ $report->session->concern->concern_name ?? 'General' }}</p>
                </div>
            </div>

            <!-- Session Summary -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                <h4 class="font-semibold text-gray-700 text-sm mb-2">Session Summary</h4>
                <p class="text-gray-600 text-sm">{{ $report->session_summary ?? 'No summary available' }}</p>
            </div>

            <!-- Evaluation Form -->
            <form class="form-maximized" method="POST" action="{{ route('adviser.evaluate.store', $report->id) }}">
                @csrf

                <div class="space-y-6">

                    <!-- Active Listening (25%) -->
                    <div>
                        <label class="form-label">Active Listening <span class="text-gray-400 font-normal">(25%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Uses minimal encouragers; avoids interrupting; accurately reflects the help seeker's statements</p>
                        <div class="rating-group" data-target="active_listening">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'active_listening')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="active_listening" id="active_listening" required>
                    </div>

                    <!-- Empathy (25%) -->
                    <div>
                        <label class="form-label">Empathy <span class="text-gray-400 font-normal">(25%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Uses emotional validation phrases; matches the help seeker's tone appropriately; avoids dismissive language</p>
                        <div class="rating-group" data-target="empathy">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'empathy')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="empathy" id="empathy" required>
                    </div>

                    <!-- Respect & Professionalism (20%) -->
                    <div>
                        <label class="form-label">Respect & Professionalism <span class="text-gray-400 font-normal">(20%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Maintains professional boundaries; uses polite and culturally appropriate language</p>
                        <div class="rating-group" data-target="respect_professionalism">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'respect_professionalism')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="respect_professionalism" id="respect_professionalism" required>
                    </div>

                    <!-- Ethical Practices (20%) -->
                    <div>
                        <label class="form-label">Ethical Practices <span class="text-gray-400 font-normal">(20%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Protects confidentiality, respects autonomy, remains within scope, and applies ethical judgment</p>
                        <div class="rating-group" data-target="ethical_practices">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'ethical_practices')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="ethical_practices" id="ethical_practices" required>
                    </div>

                    <!-- Referral Accuracy (10%) -->
                    <div>
                        <label class="form-label">Referral Accuracy <span class="text-gray-400 font-normal">(10%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Correctly tags cases matching adviser-approved referral guidelines</p>
                        <div class="rating-group" data-target="referral_accuracy">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'referral_accuracy')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="referral_accuracy" id="referral_accuracy" required>
                    </div>

                    <!-- Score Display -->
                    <div class="score-display">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="text-sm text-gray-500">Overall Competency Score</span>
                                <div class="text-3xl font-bold text-gray-800" id="overallScore">—</div>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Competency Level</span>
                                <div id="competencyLevelDisplay">—</div>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400">
                            <span class="font-medium">Formula:</span> (AL × 0.25) + (Emp × 0.25) + (RP × 0.20) + (EP × 0.20) + (RA × 0.10)
                        </div>
                    </div>

                    <!-- Feedback Section -->
                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <h4 class="font-semibold text-gray-700 mb-4">Adviser Feedback</h4>

                        <div class="mb-4">
                            <label class="form-label">Strengths Demonstrated</label>
                            <textarea name="strengths" class="form-input" rows="3" placeholder="What did the helper do well?"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Areas for Improvement</label>
                            <textarea name="improvement_areas" class="form-input" rows="3" placeholder="What could be improved?"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Developmental Recommendations</label>
                            <textarea name="recommendations" class="form-input" rows="3" placeholder="Training or development suggestions"></textarea>
                        </div>

                        <div>
                            <label class="form-label">Recommended Action <span class="text-red-500">*</span></label>
                            <select name="recommended_action" class="form-input" required>
                                <option value="">Select action...</option>
                                <option value="continue">Continue regular peer-support duties</option>
                                <option value="mentoring">Provide support or mentoring</option>
                                <option value="supervised">Require additional supervised sessions</option>
                                <option value="training_listening">Recommend active-listening training</option>
                                <option value="training_empathy">Recommend empathy and communication training</option>
                                <option value="training_ethics">Recommend ethics and confidentiality training</option>
                                <option value="training_referral">Recommend referral and emergency-response training</option>
                                <option value="restrict_high_risk">Temporarily restrict assignment to high-risk cases</option>
                                <option value="suspend">Temporarily suspend new session assignments pending improvement</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('adviser.evaluations') }}" class="btn-outline">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-check mr-2"></i> Submit Evaluation
                        </button>
                    </div>

                </div>
            </form>

        </div>
</div>
    <script>
        let ratings = {};

        function selectRating(btn, field) {
            const group = btn.closest('.rating-group');
            const value = btn.dataset.value;

            // Update UI
            group.querySelectorAll('.rating-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Store value
            ratings[field] = parseInt(value);
            document.getElementById(field).value = value;

            // Update score
            updateScore();
        }

        function updateScore() {
            const al = ratings.active_listening || 0;
            const emp = ratings.empathy || 0;
            const rp = ratings.respect_professionalism || 0;
            const ep = ratings.ethical_practices || 0;
            const ra = ratings.referral_accuracy || 0;

            const total = (al * 0.25) + (emp * 0.25) + (rp * 0.20) + (ep * 0.20) + (ra * 0.10);

            if (al > 0 && emp > 0 && rp > 0 && ep > 0 && ra > 0) {
                document.getElementById('overallScore').textContent = total.toFixed(2) + ' / 5.00';
                document.getElementById('competencyLevelDisplay').innerHTML = getLevelDisplay(total);
            } else {
                document.getElementById('overallScore').textContent = '—';
                document.getElementById('competencyLevelDisplay').textContent = '—';
            }
        }

        function getLevelDisplay(score) {
            let level, className;
            if (score >= 4.5) { level = 'Outstanding'; className = 'competency-level outstanding'; }
            else if (score >= 3.5) { level = 'Very Good'; className = 'competency-level very-good'; }
            else if (score >= 2.5) { level = 'Satisfactory'; className = 'competency-level satisfactory'; }
            else if (score >= 1.5) { level = 'Needs Improvement'; className = 'competency-level needs-improvement'; }
            else { level = 'Unsatisfactory'; className = 'competency-level unsatisfactory'; }
            return `<span class="${className}">${level}</span>`;
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
});
    </script>
@endsection
