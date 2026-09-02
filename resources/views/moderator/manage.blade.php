<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Manage Team</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary, #F8FBF9); }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .card {
            background: var(--bg-card, white);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border-color, #E5E7EB);
            box-shadow: var(--card-shadow, 0 4px 20px rgba(0,0,0,0.01));
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--text-primary, #1F2937); }

        .competency-pill { padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .competency-pill.expert { background: #DCFCE7; color: #166534; }
        .competency-pill.advanced { background: #DBEAFE; color: #1E40AF; }
        .competency-pill.proficient { background: #FEF3C7; color: #92400E; }
        .competency-pill.developing { background: #FEE2E2; color: #991B1B; }
        .competency-pill.trainee { background: #E5E7EB; color: var(--text-secondary, #6B7280); }

        .status-pill { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
        .status-pill.available { background: #DCFCE7; color: #166534; }
        .status-pill.busy { background: #FEF3C7; color: #92400E; }
        .status-pill.offline { background: var(--border-light, #F3F4F6); color: var(--text-secondary, #6B7280); }

        .helper-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light, #F3F4F6);
        }
        .helper-row:last-child { border-bottom: none; }

        .adviser-card {
            border: 1.5px solid var(--border-color, #E5E7EB);
            border-radius: 16px;
            padding: 16px;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }
        .adviser-card:hover { border-color: #04A052; box-shadow: 0 4px 16px rgba(4,160,82,0.08); }
        .adviser-card.selected { border-color: #04A052; background: #F0FDF4; }

        .slot-bar { display: flex; gap: 4px; margin-top: 10px; }
        .slot { flex: 1; height: 6px; border-radius: 3px; background: #E5E7EB; }
        .slot.filled { background: #04A052; }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: #027039; }
        .btn-ghost {
            background: var(--bg-card, white);
            color: var(--text-secondary, #6B7280);
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: 1.5px solid var(--border-color, #E5E7EB);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-ghost:hover { border-color: #DC2626; color: #DC2626; }

        .search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border-radius: 14px;
            border: 1.5px solid var(--border-color, #E5E7EB);
            outline: none;
            font-size: 14px;
            background: var(--bg-card, white);
        }
        .search-input:focus { border-color: #04A052; box-shadow: 0 0 0 3px rgba(4,160,82,0.08); }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--text-primary, #374151); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--navbar-bg, rgba(255,255,255,0.94));
            backdrop-filter: blur(16px);
            border-top: 1px solid #E5E7EB;
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-muted, #9CA3AF);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Manage Team</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Helpers and adviser assignment workspace</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                Live counts
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card py-4">
                <p class="text-xs text-gray-400">Total Helpers</p>
                <p class="text-2xl font-extrabold text-gray-800" id="statHelpers">{{ $helpers->count() }}</p>
            </div>
            <div class="card py-4">
                <p class="text-xs text-gray-400">Unassigned Pool</p>
                <p class="text-2xl font-extrabold text-amber-600" id="statUnassigned">{{ $helpers->whereNull('adviser_id')->count() }}</p>
            </div>
            <div class="card py-4">
                <p class="text-xs text-gray-400">Advisers</p>
                <p class="text-2xl font-extrabold text-blue-600" id="statAdvisers">{{ $advisers->count() }}</p>
            </div>
            <div class="card py-4">
                <p class="text-xs text-gray-400">Slots Used</p>
                <p class="text-2xl font-extrabold text-emerald-600" id="statSlots">{{ $advisers->sum('assigned_helpers') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Advisers -->
            <div class="card">
                <div class="card-header">
                    <h3>Advisers</h3>
                    <span class="text-xs text-gray-400">Capacity: 5 each</span>
                </div>
                <div class="space-y-3">
                    @foreach($advisers as $adviser)
                        <a href="{{ route('moderator.manage', array_merge(request()->except('workspace'), ['workspace' => $adviser->id])) }}"
                           class="adviser-card {{ $selectedAdviser == $adviser->id ? 'selected' : '' }}">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-sm">
                                    {{ substr($adviser->first_name, 0, 1) }}{{ substr($adviser->last_name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 text-sm truncate">{{ $adviser->full_name }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $adviser->assigned_helpers }} assigned · {{ $adviser->remaining_slots }} slots left
                                    </p>
                                </div>
                                <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                            </div>
                            <div class="slot-bar">
                                @for($i = 0; $i < $adviser->capacity; $i++)
                                    <div class="slot {{ $i < $adviser->assigned_helpers ? 'filled' : '' }}"></div>
                                @endfor
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Assignment Workspace -->
            <div class="card lg:col-span-2">
                @php
                    $workspaceAdviser = $selectedAdviser
                        ? $advisers->firstWhere('id', (int) $selectedAdviser)
                        : $advisers->first();
                @endphp
                <div class="card-header">
                    <h3>
                        Assignment Workspace
                        @if($workspaceAdviser)
                            <span class="text-sm font-semibold text-[#04A052] ml-1">— {{ $workspaceAdviser->full_name }}</span>
                        @endif
                    </h3>
                    @if($workspaceAdviser)
                        <span class="text-xs text-gray-400">{{ $workspaceAdviser->remaining_slots }} of {{ $workspaceAdviser->capacity }} slots remaining</span>
                    @endif
                </div>

                @if($workspaceAdviser)
                    <p class="text-sm text-gray-500 mb-4">
                        Helpers under <strong class="text-gray-700">{{ $workspaceAdviser->full_name }}</strong>
                    </p>
                    <div class="space-y-1 mb-6">
                        @php $workspaceHelpers = $helpers->where('adviser_id', $workspaceAdviser->id); @endphp
                        @forelse($workspaceHelpers as $helper)
                            <div class="helper-row">
                                <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-semibold text-xs flex-shrink-0">
                                    {{ substr($helper->first_name, 0, 1) }}{{ substr($helper->last_name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 text-sm">{{ $helper->full_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $helper->email }}</p>
                                </div>
                                <span class="competency-pill {{ $helper->latestCompetency?->competency_level ?? 'trainee' }}">
                                    {{ ucfirst($helper->latestCompetency?->competency_level ?? 'trainee') }}
                                </span>
                                <form method="POST" action="{{ route('moderator.manage.unassign') }}">
                                    @csrf
                                    <input type="hidden" name="helper_id" value="{{ $helper->id }}">
                                    <button type="submit" class="btn-ghost" title="Move to unassigned pool">
                                        <i class="fas fa-user-minus"></i> Unassign
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="text-center py-6 text-gray-400 text-sm">
                                No helpers assigned to this adviser yet.
                            </div>
                        @endforelse
                    </div>

                    <p class="text-sm text-gray-500 mb-3">Add helpers from the unassigned pool</p>
                    <div class="space-y-1">
                        @forelse($helpers->whereNull('adviser_id') as $helper)
                            <div class="helper-row">
                                <div class="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 font-semibold text-xs flex-shrink-0">
                                    {{ substr($helper->first_name, 0, 1) }}{{ substr($helper->last_name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 text-sm">{{ $helper->full_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $helper->email }}</p>
                                </div>
                                <span class="competency-pill {{ $helper->latestCompetency?->competency_level ?? 'trainee' }}">
                                    {{ ucfirst($helper->latestCompetency?->competency_level ?? 'trainee') }}
                                </span>
                                <form method="POST" action="{{ route('moderator.manage.assign') }}">
                                    @csrf
                                    <input type="hidden" name="helper_id" value="{{ $helper->id }}">
                                    <input type="hidden" name="adviser_id" value="{{ $workspaceAdviser->id }}">
                                    <button type="submit" class="btn-primary" {{ $workspaceAdviser->remaining_slots <= 0 ? 'disabled style=opacity:.4;cursor:not-allowed' : '' }}>
                                        <i class="fas fa-user-plus"></i> Assign
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="text-center py-6 text-gray-400 text-sm">
                                No helpers in the unassigned pool.
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="text-center py-10 text-gray-400">
                        <p class="text-3xl mb-2">👈</p>
                        <p>Select an adviser to open the assignment workspace</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- All Helpers -->
        <div class="card mt-6">
            <div class="card-header">
                <h3>All Helpers</h3>
                <div class="flex items-center gap-3">
                    <a href="{{ route('moderator.manage', request()->except('adviser')) }}" class="text-xs text-gray-400 hover:text-gray-600">All</a>
                    <a href="{{ route('moderator.manage', array_merge(request()->except('adviser'), ['adviser' => 'unassigned'])) }}" class="text-xs text-gray-400 hover:text-gray-600">Unassigned</a>
                    <form method="GET" action="{{ route('moderator.manage') }}" class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search name or ID..."
                               class="search-input !pl-9 !py-2 !text-sm" style="width: 220px;">
                        @if($adviserFilter) <input type="hidden" name="adviser" value="{{ $adviserFilter }}"> @endif
                        @if($selectedAdviser) <input type="hidden" name="workspace" value="{{ $selectedAdviser }}"> @endif
                    </form>
                </div>
            </div>
            <div>
                @forelse($helpers as $helper)
                    <div class="helper-row">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-semibold text-xs flex-shrink-0">
                            {{ substr($helper->first_name, 0, 1) }}{{ substr($helper->last_name, 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 text-sm">
                                {{ $helper->full_name }}
                                @if($helper->adviser)
                                    <span class="text-xs font-normal text-gray-400">· {{ $helper->adviser->full_name }}</span>
                                @else
                                    <span class="text-xs font-normal text-amber-500">· Unassigned</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-400">ID: H-{{ str_pad($helper->id, 3, '0', STR_PAD_LEFT) }} · {{ $helper->email }}</p>
                        </div>
                        <span class="competency-pill {{ $helper->latestCompetency?->competency_level ?? 'trainee' }}">
                            {{ ucfirst($helper->latestCompetency?->competency_level ?? 'trainee') }}
                        </span>
                        <span class="text-xs text-gray-500 hidden md:inline">{{ $helper->score }}% score</span>
                        <span class="text-xs text-gray-500">{{ $helper->active_cases }} active · {{ $helper->total_cases }} total</span>
                        <span class="status-pill {{ $helper->status }}">
                            {{ ucfirst($helper->status) }}
                        </span>
                        @if(! $helper->adviser)
                            <form method="POST" action="{{ route('moderator.manage.assign') }}" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="helper_id" value="{{ $helper->id }}">
                                <select name="adviser_id" class="text-xs border border-gray-200 rounded-xl px-2 py-1.5 outline-none focus:border-[#04A052]" required>
                                    <option value="">Assign to...</option>
                                    @foreach($advisers as $adviser)
                                        <option value="{{ $adviser->id }}" {{ $adviser->remaining_slots <= 0 ? 'disabled' : '' }}>
                                            {{ $adviser->full_name }}{{ $adviser->remaining_slots <= 0 ? ' (full)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-primary"><i class="fas fa-user-plus"></i></button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-10 text-gray-400">
                        <p class="text-3xl mb-2">👥</p>
                        <p>No helpers match your search</p>
                    </div>
                @endforelse
            </div>
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('moderator.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item">
            <i class="fas fa-hourglass-half"></i><span>Queue</span>
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item">
            <i class="fas fa-comments"></i><span>Sessions</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item">
            <i class="fas fa-exclamation-triangle"></i><span>Emergency</span>
        </a>
        <a href="{{ route('moderator.analytics') }}" class="nav-item">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) closeSidebar();
            });

            setInterval(function () {
                fetch('/moderator/manage/stats', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        document.getElementById('statHelpers').textContent = data.total_helpers;
                        document.getElementById('statUnassigned').textContent = data.unassigned_helpers;
                        document.getElementById('statAdvisers').textContent = data.total_advisers;
                        document.getElementById('statSlots').textContent = data.slots_taken;
                    })
                    .catch(() => {});
            }, 30000);
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
