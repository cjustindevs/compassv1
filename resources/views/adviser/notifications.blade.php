@extends('layouts.app')

@section('title', 'COMPASS – Notifications')

@push('styles')
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
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
        }

        body { background: #F8FBF9; }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
        }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-label { font-size: 13px; color: var(--gray-500); }

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
            gap: 12px;
            flex-wrap: wrap;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .notif-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread { background: var(--green-50); border-radius: 14px; padding: 14px; margin-bottom: 2px; }
        .notif-item .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .notif-item.unread .icon { background: white; }
        .notif-item .content { flex: 1; min-width: 0; }
        .notif-item .content .title-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        .notif-item .content .title { font-weight: 600; font-size: 14px; color: var(--gray-800); }
        .notif-item .content .title .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green-500);
            margin-left: 6px;
        }
        .notif-item .content .time { font-size: 12px; color: var(--gray-400); }
        .notif-item .content .message { font-size: 13px; color: var(--gray-500); margin-top: 4px; }
        .notif-item .content .actions { display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap; }

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
            gap: 6px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 6px 14px;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        .empty-state { text-align: center; padding: 48px 16px; color: var(--gray-400); }
        .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; opacity: 0.5; }
        @media (max-width: 768px) {
            .stat-number { font-size: 22px; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Notifications</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Emergencies, referrals, and evaluation updates
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Notifications</span>
                    <span class="text-2xl">🔔</span>
                </div>
                <div class="stat-number">{{ $notifications->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Unread</span>
                    <span class="text-2xl">📬</span>
                </div>
                <div class="stat-number">{{ $unreadCount }}</div>
                <span class="text-xs text-gray-400">Needs your attention</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Read</span>
                    <span class="text-2xl">✅</span>
                </div>
                <div class="stat-number">{{ $notifications->count() - $unreadCount }}</div>
            </div>
        </div>

        <!-- Type Filter -->
        <div class="flex items-center gap-2 mb-6 flex-wrap">
            <a href="{{ route('adviser.notifications') }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold border {{ !$typeFilter ? 'bg-[#EAF8F0] border-[#04A052] text-[#027039]' : 'border-gray-200 text-gray-500 hover:border-[#04A052]' }} transition">
                All
            </a>
            @foreach($types as $type)
                <a href="{{ route('adviser.notifications', ['type' => $type]) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold border capitalize {{ $typeFilter === $type ? 'bg-[#EAF8F0] border-[#04A052] text-[#027039]' : 'border-gray-200 text-gray-500 hover:border-[#04A052]' }} transition">
                    {{ $type }}
                </a>
            @endforeach
        </div>

        <!-- Notification List -->
        <div class="card">
            <div class="card-header">
                <h3>All Notifications</h3>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('adviser.notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn-outline"><i class="fas fa-check-double"></i> Mark all read</button>
                    </form>
                @endif
            </div>

            @if($notifications->isNotEmpty())
                <div style="display:flex;flex-direction:column;">
                    @foreach($notifications as $item)
                        @php $isUnread = $item->status === 'unread'; @endphp
                        <div class="notif-item {{ $isUnread ? 'unread' : '' }}">
                            <div class="icon">{{ $item->type_icon ?: '🔔' }}</div>
                            <div class="content">
                                <div class="title-row">
                                    <span class="title">
                                        {{ $item->title }}
                                        @if($isUnread)
                                            <span class="dot"></span>
                                        @endif
                                    </span>
                                    <span class="time">{{ $item->created_at?->diffForHumans() }}</span>
                                </div>
                                <div class="message">{{ $item->message }}</div>
                                <div class="actions">
                                    @if($item->link)
                                        <a href="{{ $item->link }}" class="btn-outline"><i class="fas fa-external-link-alt"></i> View</a>
                                    @endif
                                    @if($isUnread)
                                        <form method="POST" action="{{ route('adviser.notifications.read', ['id' => $item->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn-outline"><i class="fas fa-check"></i> Mark read</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('adviser.notifications.destroy', ['id' => $item->id]) }}"
                                          data-confirm="Delete notification?"
                                          data-confirm-message="This notification will be permanently removed."
                                          data-confirm-text="Delete"
                                          data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-outline" style="color:var(--red-500);border-color:#FECACA;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p class="text-lg font-medium text-gray-600">No notifications</p>
                    <p>Emergency flags and referral updates will appear here.</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>
</div>
    <!-- Bottom Navigation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
});
    </script>
@endsection
