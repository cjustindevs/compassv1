<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>@yield('title', 'COMPASS') – Helper</title>

    @vite(['resources/js/app.js', 'resources/js/helper-notifications.js'])

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
            --green-200: #A8E4BC;
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
            --red-600: #DC2626;
            --yellow-500: #F59E0B;
            --blue-50: #EFF6FF;
            --blue-500: #3B82F6;
            --blue-600: #1D4ED8;
        }

        body { background: #F8FBF9; }

        a { text-decoration: none; }

        /* ─────────── Sidebar ─────────── */
        .sidebar {
            position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4, 160, 82, 0.06);
            box-shadow: 4px 0 40px rgba(0, 0, 0, 0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .sidebar .logo { display: flex; align-items: center; gap: 12px; padding-bottom: 24px; border-bottom: 1px solid rgba(4, 160, 82, 0.06); margin-bottom: 20px; }
        .sidebar .logo .icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 20px;
            box-shadow: 0 4px 16px rgba(4, 160, 82, 0.2);
        }
        .sidebar .logo span { font-weight: 700; font-size: 20px; color: var(--green-700); }

        .sidebar .nav { flex: 1; overflow-y: auto; }
        .sidebar .nav .nav-label {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--gray-400); padding: 12px 14px 6px;
        }
        .sidebar .nav .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 10px 14px;
            border-radius: 12px; color: var(--gray-500); font-size: 14px; font-weight: 500;
            transition: all 0.2s ease; cursor: pointer; text-decoration: none; margin-bottom: 2px;
        }
        .sidebar .nav .nav-item i { width: 20px; text-align: center; font-size: 16px; color: var(--gray-400); }
        .sidebar .nav .nav-item:hover { background: var(--green-50); color: var(--gray-800); }
        .sidebar .nav .nav-item:hover i { color: var(--green-500); }
        .sidebar .nav .nav-item.active { background: var(--green-50); color: var(--green-700); font-weight: 600; }
        .sidebar .nav .nav-item.active i { color: var(--green-500); }
        .sidebar .nav .nav-item .badge { margin-left: auto; background: var(--green-500); color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
        .sidebar .nav .nav-item .badge.danger { background: var(--red-600); }

        .sidebar .user-section { border-top: 1px solid rgba(4, 160, 82, 0.06); padding-top: 16px; margin-top: auto; }
        .sidebar .user-section .user-card { display: flex; align-items: center; gap: 12px; }
        .sidebar .user-section .user-card .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 16px; text-transform: uppercase; flex-shrink: 0;
        }
        .sidebar .user-section .user-card .info { min-width: 0; }
        .sidebar .user-section .user-card .info .name { font-weight: 600; font-size: 14px; color: var(--gray-800); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar .user-section .user-card .info .role { font-size: 12px; color: var(--gray-400); }

        .sidebar .user-section .user-stats { display: flex; justify-content: space-around; padding: 12px 0; border-bottom: 1px solid rgba(4, 160, 82, 0.06); margin-bottom: 12px; }
        .sidebar .user-section .user-stats .stat { text-align: center; }
        .sidebar .user-section .user-stats .stat .value { display: block; font-weight: 700; font-size: 14px; color: var(--gray-800); }
        .sidebar .user-section .user-stats .stat .label { font-size: 10px; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.03em; }

        .sidebar .user-section .logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px; color: var(--red-600);
            font-size: 13px; font-weight: 600; transition: all 0.2s ease;
            cursor: pointer; border: 1px solid #FECACA; background: #FEF2F2; width: 100%;
        }
        .sidebar .user-section .logout-btn:hover { background: var(--red-600); color: white; border-color: var(--red-600); }

        /* ─────────── Main content ─────────── */
        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; gap: 16px; }
        .top-bar .greeting h1 { font-size: 24px; font-weight: 700; color: var(--gray-800); }
        .top-bar .greeting p { font-size: 14px; color: var(--gray-500); }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--gray-700); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        /* ─────────── Cards & stats ─────────── */
        .stat-card { background: white; border-radius: 16px; padding: 20px 24px; border: 1px solid var(--gray-200); transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04); }
        .stat-card .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-card .stat-label { font-size: 13px; color: var(--gray-500); }
        .stat-card .stat-icon { font-size: 24px; opacity: 0.7; }

        .card { background: white; border-radius: 20px; padding: 24px; border: 1px solid var(--gray-200); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.01); }
        .card .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
        .card .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }
        .card .card-header a.link { font-size: 13px; color: var(--green-500); }
        .card .card-header a.link:hover { text-decoration: underline; }

        /* ─────────── Tables ─────────── */
        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-container table { width: 100%; min-width: 600px; }
        .table-container th { font-size: 11px; font-weight: 600; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.05em; padding: 10px 12px; text-align: left; }
        .table-container td { padding: 10px 12px; font-size: 14px; color: var(--gray-700); border-top: 1px solid var(--gray-100); }
        .table-container tr:hover td { background: var(--gray-50); }

        /* ─────────── Badges ─────────── */
        .risk-badge { padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .risk-badge.low { background: var(--green-100); color: var(--green-700); }
        .risk-badge.moderate { background: #FEF3C7; color: #D97706; }
        .risk-badge.high { background: #FEE2E2; color: var(--red-600); }
        .risk-badge.emergency { background: #FEE2E2; color: var(--red-600); animation: pulse-risk 1.5s ease-in-out infinite; }

        @keyframes pulse-risk { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-badge.active { background: #DBEAFE; color: var(--blue-600); }
        .status-badge.completed { background: var(--green-100); color: var(--green-700); }
        .status-badge.pending, .status-badge.helper-assigned, .status-badge.screening-completed, .status-badge.preferences-set { background: #FEF3C7; color: #B45309; }
        .status-badge.waiting, .status-badge.scheduled { background: var(--gray-100); color: var(--gray-600); }
        .status-badge.cancelled, .status-badge.no-show { background: #FEE2E2; color: var(--red-600); }

        /* ─────────── Buttons ─────────── */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s ease; text-decoration: none; }
        .btn-primary { background: var(--green-500); color: white; }
        .btn-primary:hover { background: var(--green-600); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(4, 160, 82, 0.25); }
        .btn-secondary { background: white; color: var(--gray-600); border: 1px solid var(--gray-200); }
        .btn-secondary:hover { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }
        .btn-danger { background: var(--red-600); color: white; }
        .btn-danger:hover { background: #B91C1C; }
        .btn-outline-danger { background: white; color: var(--red-600); border: 1px solid #FECACA; }
        .btn-outline-danger:hover { background: #FEF2F2; }
        .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 10px; }
        .btn-block { width: 100%; }

        /* ─────────── Forms ─────────── */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--gray-700); margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 12px; font-size: 14px; color: var(--gray-800); background: white; transition: all 0.2s ease; outline: none; }
        .form-control:focus { border-color: var(--green-500); box-shadow: 0 0 0 3px rgba(4, 160, 82, 0.12); }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--green-500); }

        /* ─────────── Alerts ─────────── */
        .alert { padding: 12px 16px; border-radius: 12px; font-size: 14px; font-weight: 500; margin-bottom: 16px; border: 1px solid; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: var(--green-50); border-color: var(--green-200); color: var(--green-700); }
        .alert-error { background: #FEF2F2; border-color: #FECACA; color: var(--red-600); }
        .alert-info { background: var(--blue-50); border-color: #BFDBFE; color: var(--blue-600); }
        .alert-warning { background: #FFFBEB; border-color: #FDE68A; color: #B45309; }

        /* ─────────── Emergency alert ─────────── */
        .emergency-alert { background: #FEF2F2; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .emergency-alert .alert-text { color: var(--red-600); font-weight: 500; font-size: 14px; }
        .emergency-alert .btn-escalate { background: var(--red-600); color: white; padding: 6px 16px; border-radius: 20px; font-weight: 600; font-size: 12px; border: none; cursor: pointer; }

        /* ─────────── Quick actions ─────────── */
        .quick-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .quick-actions .btn-action { padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; border: 1px solid var(--gray-200); background: white; color: var(--gray-600); cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .quick-actions .btn-action:hover { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        /* ─────────── Activity feed ─────────── */
        .activity-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--gray-100); }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .activity-item .icon.emergency { background: #FEE2E2; color: var(--red-600); }
        .activity-item .icon.assignment { background: #DBEAFE; color: var(--blue-600); }
        .activity-item .icon.feedback { background: var(--green-100); color: var(--green-700); }
        .activity-item .icon.reminder { background: #FEF3C7; color: #D97706; }
        .activity-item .content .message { font-weight: 500; font-size: 14px; color: var(--gray-800); }
        .activity-item .content .detail { font-size: 13px; color: var(--gray-500); }
        .activity-item .content .time { font-size: 11px; color: var(--gray-400); }

        /* ─────────── Active session card ─────────── */
        .active-session-card { background: var(--green-50); border: 1px solid var(--green-200); border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .active-session-card .session-info .alias { font-weight: 700; font-size: 16px; color: var(--gray-800); }
        .active-session-card .session-info .mode { font-size: 13px; color: var(--gray-500); }
        .active-session-card .session-info .time { font-size: 13px; color: var(--green-600); font-weight: 600; }
        .active-session-card .btn-resume { background: var(--green-500); color: white; padding: 8px 20px; border-radius: 20px; font-weight: 600; font-size: 13px; border: none; cursor: pointer; transition: all 0.3s ease; text-decoration: none; }
        .active-session-card .btn-resume:hover { background: var(--green-600); transform: scale(1.02); }

        /* ─────────── Chat ─────────── */
        .chat-window { display: flex; flex-direction: column; height: calc(100vh - 260px); min-height: 420px; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .chat-bubble { max-width: 72%; padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.5; word-break: break-word; }
        .chat-bubble .meta { display: block; font-size: 11px; opacity: 0.7; margin-top: 4px; }
        .chat-bubble.helper { align-self: flex-end; background: var(--green-500); color: white; border-bottom-right-radius: 4px; }
        .chat-bubble.seeker { align-self: flex-start; background: var(--gray-100); color: var(--gray-800); border-bottom-left-radius: 4px; }
        .chat-input-row { display: flex; gap: 10px; padding: 16px; border-top: 1px solid var(--gray-200); background: white; }
        .chat-input-row input { flex: 1; }

        /* ─────────── Voice / Call ─────────── */
        .voice-avatar { width: 96px; height: 96px; border-radius: 50%; background: linear-gradient(135deg, #38C172, #038A45); display: flex; align-items: center; justify-content: center; color: white; font-size: 40px; font-weight: 700; margin: 0 auto; box-shadow: 0 8px 30px rgba(4, 160, 82, 0.3); }
        .call-timer { font-size: 34px; font-weight: 800; color: var(--gray-800); }
        .call-actions { display: flex; gap: 16px; justify-content: center; margin-top: 24px; }
        .call-btn { width: 60px; height: 60px; border-radius: 50%; border: none; font-size: 22px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .call-btn.end { background: var(--red-600); color: white; }
        .call-btn.end:hover { background: #B91C1C; transform: scale(1.05); }
        .call-btn.mute { background: var(--gray-200); color: var(--gray-600); }
        .call-btn.mute.active { background: var(--green-500); color: white; }
        .call-btn.danger-flash { animation: pulse-risk 0.8s ease-in-out infinite; }

        /* ─────────── Calendar ─────────── */
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .calendar-cell { min-height: 96px; background: white; border: 1px solid var(--gray-200); border-radius: 12px; padding: 8px; transition: all 0.2s ease; }
        .calendar-cell .day-num { font-size: 13px; font-weight: 600; color: var(--gray-400); }
        .calendar-cell.outside { opacity: 0.4; background: var(--gray-50); }
        .calendar-cell.today { border-color: var(--green-500); box-shadow: 0 0 0 2px rgba(4, 160, 82, 0.15); }
        .calendar-cell.today .day-num { color: var(--green-500); }
        .calendar-event { margin-top: 6px; padding: 3px 6px; border-radius: 6px; font-size: 10px; font-weight: 600; color: var(--green-700); background: var(--green-50); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; display: block; }
        .calendar-event .dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--green-500); margin-right: 4px; }
        .calendar-event.completed { background: var(--gray-100); color: var(--gray-500); }
        .calendar-event.helper-assigned { background: #FEF3C7; color: #B45309; }
        .calendar-head .day-name { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-400); text-align: center; padding: 8px 0; }

        /* ─────────── Misc ─────────── */
        .empty-state { text-align: center; padding: 48px 16px; color: var(--gray-400); }
        .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; opacity: 0.5; }
        .avatar-sm { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #38C172, #038A45); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; text-transform: uppercase; flex-shrink: 0; }
        .avatar-lg { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #38C172, #038A45); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 26px; text-transform: uppercase; flex-shrink: 0; }
        .divider { border: none; border-top: 1px solid var(--gray-100); margin: 16px 0; }
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: var(--green-50); color: var(--green-700); }
        .progress-bar { height: 8px; border-radius: 20px; background: var(--gray-100); overflow: hidden; }
        .progress-bar > span { display: block; height: 100%; border-radius: 20px; background: linear-gradient(90deg, #38C172, var(--green-500)); }

        /* ─────────── Bottom nav (mobile) ─────────── */
        .bottom-nav { display: none; position: fixed; bottom: 0; left: 0; right: 0; background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-top: 1px solid var(--gray-200); padding: 6px 0 env(safe-area-inset-bottom, 6px); z-index: 200; justify-content: space-around; }
        .bottom-nav .nav-item { display: flex; flex-direction: column; align-items: center; color: var(--gray-400); text-decoration: none; font-size: 10px; font-weight: 500; padding: 4px 12px; transition: all 0.2s ease; }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .top-bar .greeting h1 { font-size: 20px; }
            .stat-card .stat-number { font-size: 22px; }
            .grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
            .active-session-card { flex-direction: column; text-align: center; }
            .emergency-alert { flex-direction: column; text-align: center; }
            .quick-actions { justify-content: center; }
            .chat-window { height: calc(100vh - 240px); }
            .calendar-cell { min-height: 72px; }
        }
        @media (max-width: 480px) {
            .grid-cols-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card .stat-number { font-size: 18px; }
            .stat-card { padding: 14px 16px; }
            .card { padding: 16px; }
            .table-container table { min-width: 500px; }
        }
    </style>

    @yield('styles')
</head>
<body>

    @include('layouts.partials.helper-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="greeting">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1>@yield('heading', 'Helper')</h1>
                        <p>@yield('subheading', '')</p>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:12px;color:var(--gray-400);">{{ now()->format('M d, Y') }}</span>
                <a href="{{ route('helper.notifications') }}" class="notif-bell" style="position:relative;width:36px;height:36px;border-radius:50%;background:white;border:1px solid var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-500);">
                    <i class="fas fa-bell"></i>
                    @if(($unreadBadge = optional(auth()->user())->unreadNotifications()->count()) > 0)
                        <span style="position:absolute;top:-4px;right:-4px;background:var(--red-600);color:white;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:10px;display:flex;align-items:center;justify-content:center;padding:0 4px;">{{ $unreadBadge }}</span>
                    @endif
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> {{ session('info') }}</div>
        @endif
        @if (session('readiness_status'))
            <div class="alert {{ session('readiness_status') === 'ready' ? 'alert-success' : 'alert-warning' }}">
                <i class="fas fa-heartbeat"></i>
                You are currently marked as <strong>{{ session('readiness_status') === 'ready' ? 'Ready' : 'Not Ready' }}</strong> for sessions.
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Bottom navigation (mobile) -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('helper.dashboard') }}" class="nav-item {{ request()->routeIs('helper.dashboard') ? 'active' : '' }}"><i class="fas fa-th-large"></i><span>Home</span></a>
        <a href="{{ route('helper.cases') }}" class="nav-item {{ request()->routeIs('helper.cases*') ? 'active' : '' }}"><i class="fas fa-folder-open"></i><span>Cases</span></a>
        <a href="{{ route('helper.chat') }}" class="nav-item {{ request()->routeIs('helper.session.chat*') ? 'active' : '' }}"><i class="fas fa-comment-dots"></i><span>Chat</span></a>
        <a href="{{ route('helper.notifications') }}" class="nav-item {{ request()->routeIs('helper.notifications*') ? 'active' : '' }}"><i class="fas fa-bell"></i><span>Alerts</span></a>
        <a href="{{ route('helper.profile') }}" class="nav-item {{ request()->routeIs('helper.profile*') ? 'active' : '' }}"><i class="fas fa-user-circle"></i><span>Profile</span></a>
    </nav>

    <script>
        @php $u = optional(auth()->user()); @endphp
        (function () {
            @if($u->dark_mode)
                document.body.style.filter = 'invert(0.92) hue-rotate(180deg)';
            @endif
            @if($u->high_contrast)
                document.body.classList.add('high-contrast');
            @endif
            @if($u->font_size === 'large')
                document.documentElement.style.fontSize = '112.5%';
            @elseif($u->font_size === 'small')
                document.documentElement.style.fontSize = '93%';
            @endif
        })();

        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburgerBtn');

            function closeSidebar() {
                if (sidebar) sidebar.classList.add('closed');
                if (overlay) overlay.classList.remove('active');
            }

            if (hamburger) {
                hamburger.addEventListener('click', function () {
                    const isOpen = sidebar && !sidebar.classList.contains('closed');
                    if (sidebar) sidebar.classList.toggle('closed', isOpen);
                    if (overlay) overlay.classList.toggle('active', isOpen);
                });
            }
            if (overlay) overlay.addEventListener('click', closeSidebar);
            window.addEventListener('resize', function () { if (window.innerWidth > 768) closeSidebar(); });

            // Close the sidebar automatically when a link inside it is clicked on mobile
            document.querySelectorAll('.sidebar .nav .nav-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    if (window.innerWidth <= 768) closeSidebar();
                });
            });
        });
    </script>

    @yield('scripts')
</body>
</html>
