<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS &middot; Referral Assignment</title>

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

        .priority-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .priority-pill.low { background: #F3F4F6; color: #4B5563; }
        .priority-pill.moderate { background: #FEF3C7; color: #92400E; }
        .priority-pill.high { background: #FFEDD5; color: #9A3412; }
        .priority-pill.emergency { background: #FEE2E2; color: #991B1B; }

        select, .btn-primary {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            border-radius: 12px;
        }
        select {
            padding: 8px 12px;
            border: 1px solid var(--border-color, #E5E7EB);
            background: var(--bg-card, white);
            color: var(--text-primary, #1F2937);
        }
        .btn-primary {
            padding: 8px 16px;
            background: #04A052;
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary:hover { background: #038845; }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-card, white);
            border-top: 1px solid var(--border-color, #E5E7EB);
            display: flex;
            justify-content: space-around;
            padding: 8px 0;
            z-index: 50;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-muted, #9CA3AF);
            text-decoration: none;
            font-size: 11px;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h3>Referrals Awaiting Adviser Assignment</h3>
                <a href="{{ route('moderator.dashboard') }}" class="text-sm text-[#04A052] hover:underline">Back to Dashboard</a>
            </div>

            <p class="text-sm text-gray-500 mb-4">
                A Helper raised a referral recommendation but has no assigned Adviser, so nobody can review it.
                Assign an active Adviser to hand the recommendation over. The recommendation itself is preserved.
            </p>

            @if (session('success'))
                <div class="mb-4 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if ($referrals->isEmpty())
                <p class="text-sm text-gray-500 py-6 text-center">
                    No referrals are waiting for an Adviser assignment.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-4">#</th>
                                <th class="py-2 pr-4">Raised</th>
                                <th class="py-2 pr-4">Helper</th>
                                <th class="py-2 pr-4">Priority</th>
                                <th class="py-2 pr-4">Reason</th>
                                <th class="py-2">Assign Adviser</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($referrals as $referral)
                                <tr class="border-b border-gray-100 align-top">
                                    <td class="py-3 pr-4 font-semibold">{{ $referral->id }}</td>
                                    <td class="py-3 pr-4 text-gray-600 whitespace-nowrap">{{ optional($referral->created_at)->diffForHumans() }}</td>
                                    <td class="py-3 pr-4 text-gray-700">
                                        {{ $referral->helper?->user?->name ?? 'Unknown Helper' }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="priority-pill {{ $referral->priority_level }}">{{ $referral->priority_level }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-600 max-w-md">{{ \Illuminate\Support\Str::limit($referral->referral_reason, 140) }}</td>
                                    <td class="py-3">
                                        <form method="POST" action="{{ route('moderator.referrals.assign-adviser', $referral->id) }}"
                                              class="flex items-center gap-2"
                                              data-confirm="Assign this referral to the selected Adviser?">
                                            @csrf
                                            <select name="adviser_id" required aria-label="Adviser for referral {{ $referral->id }}">
                                                <option value="">Select an Adviser&hellip;</option>
                                                @foreach ($advisers as $adviser)
                                                    <option value="{{ $adviser->id }}">
                                                        {{ $adviser->user?->name ?? 'Adviser #'.$adviser->id }}
                                                        &mdash; {{ $adviser->supervised_helpers_count }} {{ \Illuminate\Support\Str::plural('helper', $adviser->supervised_helpers_count) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn-primary">Assign</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

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
        <a href="{{ route('moderator.referrals.unassigned') }}" class="nav-item active">
            <i class="fas fa-user-plus"></i><span>Referrals</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item">
            <i class="fas fa-exclamation-triangle"></i><span>Emergency</span>
        </a>
    </nav>
</body>
</html>
