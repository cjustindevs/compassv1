<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Case #{{ $case->id }}</title>

    @vite(['resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
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
        }

        body { background: #F8FBF9; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .priority-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .priority-badge.low { background: var(--gray-200); color: var(--gray-600); }
        .priority-badge.moderate { background: #FEF3C7; color: #D97706; }
        .priority-badge.high { background: #FEE2E2; color: #DC2626; }
        .priority-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.accepted { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.in_progress { background: #E0E7FF; color: #4338CA; }
        .status-badge.completed { background: var(--green-50); color: var(--green-700); }
        .status-badge.closed { background: var(--gray-200); color: var(--gray-600); }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { font-size: 12px; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.03em; }
        .info-row .value { font-size: 14px; font-weight: 500; color: var(--gray-700); text-align: right; }

        .timeline { position: relative; padding-left: 28px; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: var(--gray-100);
        }
        .timeline-item { position: relative; padding-bottom: 20px; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 6px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--green-500);
            border: 2px solid white;
            box-shadow: 0 0 0 2px var(--green-500);
        }
        .timeline-item .note-type {
            display: inline-block;
            background: var(--green-50);
            color: var(--green-700);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 6px;
        }
        .timeline-item .note-date { font-size: 12px; color: var(--gray-400); }
        .timeline-item .note-body { font-size: 14px; color: var(--gray-600); line-height: 1.6; margin-top: 6px; }
        .timeline-item .follow-up {
            margin-top: 8px;
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 12px;
            color: #92400E;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }

        .btn-danger {
            background: #EF4444;
            color: white;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-danger:hover { background: #DC2626; transform: scale(1.02); }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 92%;
            padding: 32px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.15);
            animation: modalSlide 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes modalSlide { from { transform: scale(0.95) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

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

        .flash-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: white;
            border-left: 4px solid var(--green-500);
            border-radius: 12px;
            padding: 14px 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-800);
            animation: flashIn 0.3s ease-out;
        }
        .flash-alert.info { border-left-color: #3B82F6; }
        .flash-alert.error { border-left-color: #EF4444; }
        @keyframes flashIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .info-row { flex-direction: column; gap: 2px; }
            .info-row .value { text-align: left; }
            .modal-box { padding: 24px 20px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.professional-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">
                        Case #{{ $case->id }}
                        <span class="priority-badge {{ $case->priority_level }} ml-2">{{ ucfirst($case->priority_level) }}</span>
                        <span class="status-badge {{ $case->status }} ml-1">{{ ucfirst(str_replace('_', ' ', $case->status)) }}</span>
                    </h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        {{ $case->session->seeker->generated_alias ?? 'Anonymous' }} · Since {{ $case->created_at->format('M d, Y') }}
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('professional.cases') }}" class="btn-outline">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                @if(in_array($case->status, \App\Models\Referral::ACTIVE_STATUSES, true))
                    <button class="btn-outline" onclick="openStatusModal()">
                        <i class="fas fa-exchange-alt mr-1"></i> Update Status
                    </button>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Case Details -->
            <div class="lg:col-span-1 space-y-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Case Details</h3>
                    </div>
                    <div class="info-row">
                        <span class="label">Seeker</span>
                        <span class="value">{{ $case->session->seeker->generated_alias ?? 'Anonymous' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Helper</span>
                        <span class="value">{{ optional($case->helper)->full_name ?? 'Unknown' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Adviser</span>
                        <span class="value">{{ optional($case->adviser)->full_name ?? 'Unassigned' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Area of Concern</span>
                        <span class="value">{{ optional($case->session->concern)->category_name ?? 'General' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Referral Date</span>
                        <span class="value">{{ $case->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Last Update</span>
                        <span class="value">{{ $case->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Next Follow-up</span>
                        <span class="value">{{ $nextFollowUp ? \Carbon\Carbon::parse($nextFollowUp)->format('M d, Y') : '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Interventions</span>
                        <span class="value">{{ $notes->count() }}</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Referral Reason</h3>
                    </div>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        {{ $case->referral_reason }}
                    </p>
                </div>
            </div>

            <!-- Intervention History -->
            <div class="lg:col-span-2 space-y-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Intervention History</h3>
                        <span class="text-sm text-gray-400">{{ $notes->count() }} note(s)</span>
                    </div>

                    @if($notes->isNotEmpty())
                        <div class="timeline">
                            @foreach($notes as $note)
                                <div class="timeline-item">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <span class="note-type">{{ $note->intervention_type_label }}</span>
                                        <span class="note-date">{{ $note->created_at->format('M d, Y g:i A') }}</span>
                                    </div>
                                    <div class="note-body">{{ $note->notes }}</div>
                                    @if($note->follow_up_plan || $note->follow_up_date)
                                        <div class="follow-up">
                                            <i class="fas fa-calendar-check mr-1"></i>
                                            <strong>Follow-up:</strong>
                                            @if($note->follow_up_date) {{ $note->follow_up_date->format('M d, Y') }} @endif
                                            @if($note->follow_up_plan) – {{ $note->follow_up_plan }} @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-10 text-gray-400">
                            <i class="fas fa-notes-medical text-5xl mb-3 block text-green-500"></i>
                            <p class="text-lg font-medium text-gray-600">No intervention notes yet</p>
                            <p class="text-sm">Document your first intervention below</p>
                        </div>
                    @endif
                </div>

                @if(in_array($case->status, \App\Models\Referral::ACTIVE_STATUSES, true))
                    <!-- Add Intervention Notes -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Add Intervention Note</h3>
                        </div>

                        <form method="POST" action="{{ route('professional.cases.notes', $case->id) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Intervention Type <span class="text-red-500">*</span></label>
                                <select name="intervention_type" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" required>
                                    <option value="">Select type...</option>
                                    <option value="initial_assessment">Initial Assessment</option>
                                    <option value="individual_therapy">Individual Therapy</option>
                                    <option value="group_therapy">Group Therapy</option>
                                    <option value="counseling_session">Counseling Session</option>
                                    <option value="crisis_intervention">Crisis Intervention</option>
                                    <option value="psychoeducation">Psychoeducation</option>
                                    <option value="family_therapy">Family Therapy</option>
                                    <option value="telehealth_session">Telehealth Session</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Session Summary <span class="text-red-500">*</span></label>
                                <textarea name="notes" rows="4" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" placeholder="Document the intervention, observations, and progress..." required></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Follow-up Plan</label>
                                <textarea name="follow_up_plan" rows="2" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" placeholder="Planned next steps for this case..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Follow-up Date</label>
                                <input type="date" name="follow_up_date" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none">
                            </div>

                            <button type="submit" class="btn-primary w-full justify-center">
                                <i class="fas fa-save mr-1"></i> Save Intervention Note
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Clinical documentation, kept confidential.
        </div>

    </main>

    <!-- Update Status Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-box">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-blue-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Update Case Status</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Change the status of case #{{ $case->id }}. The adviser will be notified when a case is completed or closed.</p>

            <form method="POST" action="{{ route('professional.cases.status', $case->id) }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Status <span class="text-red-500">*</span></label>
                    <select name="status" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" required>
                        <option value="{{ \App\Models\Referral::STATUS_IN_PROGRESS }}" {{ $case->status === \App\Models\Referral::STATUS_IN_PROGRESS ? 'selected' : '' }}>In Progress</option>
                        <option value="{{ \App\Models\Referral::STATUS_COMPLETED }}">Completed</option>
                        <option value="{{ \App\Models\Referral::STATUS_CLOSED }}">Closed</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="btn-outline flex-1" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn-primary flex-1">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            document.querySelectorAll('.flash-alert').forEach(alert => {
                setTimeout(() => alert.remove(), 5000);
            });
        });

        function openStatusModal() {
            document.getElementById('statusModal').classList.add('active');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>

    @if($errors->any())
        <div class="flash-alert error">
            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
            {{ $errors->first() }}
        </div>
    @endif
    @if(session('success'))
        <div class="flash-alert"><i class="fas fa-check-circle text-[#04A052] mr-2"></i>{{ session('success') }}</div>
    @endif

    @include('layouts.partials.pwa-banner')

</body>
</html>