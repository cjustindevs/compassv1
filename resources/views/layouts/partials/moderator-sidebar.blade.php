@php
    $moderatorBadges = [
        'queueBadge' => \App\Models\QueueRequest::where('request_status', 'waiting')->count(),
        'sessionBadge' => \App\Models\Session::where('session_status', 'active')->count(),
        'emergencyBadge' => \App\Models\IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])->count(),
        'notifBadge' => optional(auth()->user())->unreadNotifications()->count() ?? 0,
        'liveCount' => \App\Models\Session::where('session_status', 'active')->count(),
        'queueCount' => \App\Models\QueueRequest::where('request_status', 'waiting')->count(),
        'emergencyCount' => \App\Models\IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])->count(),
    ];
@endphp

<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 260px;
        height: 100vh;
        background: rgba(255,255,255,0.92);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-right: 1px solid rgba(4,160,82,0.06);
        box-shadow: 4px 0 40px rgba(0,0,0,0.02);
        z-index: 100;
        transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
        display: flex;
        flex-direction: column;
        padding: 24px 16px 20px;
        overflow: hidden;
    }
    .sidebar.closed { transform: translateX(-100%); }

    .sidebar .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 24px;
        border-bottom: 1px solid rgba(4,160,82,0.06);
        margin-bottom: 20px;
        flex-shrink: 0;
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
        color: #027039;
        letter-spacing: -0.5px;
    }

    .sidebar .nav { flex: 1; overflow-y: auto; overflow-x: hidden; }
    .sidebar .nav .nav-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9CA3AF;
        padding: 12px 14px 6px;
        margin: 0;
    }
    .sidebar .nav .nav-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px 14px;
        border-radius: 12px;
        color: #6B7280;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        margin-bottom: 2px;
        position: relative;
        white-space: nowrap;
    }
    .sidebar .nav .nav-item i {
        width: 20px;
        text-align: center;
        font-size: 16px;
        color: #9CA3AF;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .sidebar .nav .nav-item:hover {
        background: #EAF8F0;
        color: #1F2937;
    }
    .sidebar .nav .nav-item:hover i {
        color: #04A052;
    }
    .sidebar .nav .nav-item.active {
        background: #EAF8F0;
        color: #027039;
        font-weight: 600;
    }
    .sidebar .nav .nav-item.active i {
        color: #04A052;
    }
    .sidebar .nav .nav-item .badge {
        margin-left: auto;
        background: #04A052;
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        flex-shrink: 0;
        min-width: 20px;
        text-align: center;
    }
    .sidebar .nav .nav-item .badge.badge-danger {
        background: #EF4444;
    }

    .sidebar .user-section {
        border-top: 1px solid rgba(4,160,82,0.06);
        padding-top: 16px;
        margin-top: auto;
        flex-shrink: 0;
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
    .sidebar .user-section .user-card .info { min-width: 0; }
    .sidebar .user-section .user-card .info .name {
        font-weight: 600;
        font-size: 14px;
        color: #1F2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar .user-section .user-card .info .role {
        font-size: 12px;
        color: #9CA3AF;
    }
    .sidebar .user-section .user-stats {
        display: flex;
        justify-content: space-around;
        padding: 12px 0;
        border-bottom: 1px solid rgba(4,160,82,0.06);
        margin-bottom: 12px;
    }
    .sidebar .user-section .user-stats .stat { text-align: center; }
    .sidebar .user-section .user-stats .stat .value {
        display: block;
        font-weight: 700;
        font-size: 14px;
        color: #1F2937;
    }
    .sidebar .user-section .user-stats .stat .label {
        font-size: 10px;
        color: #9CA3AF;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .sidebar .user-section .logout-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 12px;
        padding: 8px 12px;
        border-radius: 10px;
        color: #6B7280;
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
    .sidebar .user-section .logout-btn i { width: 20px; text-align: center; }

    .hamburger {
        display: none;
        background: none;
        border: none;
        font-size: 24px;
        color: #374151;
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

    @media (max-width: 768px) {
        .sidebar { width: 280px; padding: 16px; }
        .hamburger { display: block; }
    }
    @media (min-width: 769px) {
        .sidebar-overlay { display: none !important; }
    }
</style>

<aside class="sidebar" id="sidebar">
    <div class="logo">
        <div class="icon">C</div>
        <span>COMPASS</span>
    </div>

    <nav class="nav">
        <p class="nav-label">Operations</p>
        <a href="{{ route('moderator.dashboard') }}" class="nav-item {{ request()->routeIs('moderator.dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item {{ request()->routeIs('moderator.queue*') ? 'active' : '' }}">
            <i class="fas fa-hourglass-half"></i> Incoming Queue
            @if($moderatorBadges['queueBadge'] > 0)
                <span class="badge" id="queueBadge">{{ $moderatorBadges['queueBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item {{ request()->routeIs('moderator.sessions*') ? 'active' : '' }}">
            <i class="fas fa-comments"></i> Active Sessions
            @if($moderatorBadges['sessionBadge'] > 0)
                <span class="badge" id="sessionBadge">{{ $moderatorBadges['sessionBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.manage') }}" class="nav-item {{ request()->routeIs('moderator.manage*') ? 'active' : '' }}">
            <i class="fas fa-users-cog"></i> Manage
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item {{ request()->routeIs('moderator.emergency*') ? 'active' : '' }}">
            <i class="fas fa-exclamation-triangle"></i> Emergency Alerts
            @if($moderatorBadges['emergencyBadge'] > 0)
                <span class="badge badge-danger" id="emergencyBadge">{{ $moderatorBadges['emergencyBadge'] }}</span>
            @endif
        </a>

        <p class="nav-label">Analytics</p>
        <a href="{{ route('moderator.analytics') }}" class="nav-item {{ request()->routeIs('moderator.analytics*') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i> Analytics
        </a>
        <a href="{{ route('moderator.reports') }}" class="nav-item {{ request()->routeIs('moderator.reports*') ? 'active' : '' }}">
            <i class="fas fa-file-alt"></i> Reports
        </a>

        <p class="nav-label">Account</p>
        <a href="{{ route('moderator.notifications') }}" class="nav-item {{ request()->routeIs('moderator.notifications*') ? 'active' : '' }}">
            <i class="fas fa-bell"></i> Notifications
            @if($moderatorBadges['notifBadge'] > 0)
                <span class="badge" id="notifBadge">{{ $moderatorBadges['notifBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.settings') }}" class="nav-item {{ request()->routeIs('moderator.settings*') ? 'active' : '' }}">
            <i class="fas fa-cog"></i> Settings
        </a>
    </nav>

    <div class="user-section">
        <div class="user-card">
            <div class="avatar">{{ optional(auth()->user()->moderator)->first_name ? substr(auth()->user()->moderator->first_name, 0, 1) . substr(auth()->user()->moderator->last_name, 0, 1) : substr(auth()->user()->name, 0, 2) }}</div>
            <div class="info">
                <div class="name">{{ optional(auth()->user()->moderator)->full_name ?? auth()->user()->name }}</div>
                <div class="role">Moderator</div>
            </div>
        </div>
        <div class="user-stats">
            <div class="stat">
                <span class="value" id="liveCount">{{ $moderatorBadges['liveCount'] }}</span>
                <span class="label">Live</span>
            </div>
            <div class="stat">
                <span class="value" id="queueCount">{{ $moderatorBadges['queueCount'] }}</span>
                <span class="label">Queue</span>
            </div>
            <div class="stat">
                <span class="value" id="emergencyCount">{{ $moderatorBadges['emergencyCount'] }}</span>
                <span class="label">Emergency</span>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</aside>
