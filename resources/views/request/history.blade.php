<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Request History</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --green-50:  #EAF8F0;
            --green-100: #D0F0D8;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --gray-50:   #F9FAFB;
            --gray-100:  #F3F4F6;
            --gray-200:  #E5E7EB;
            --gray-400:  #9CA3AF;
            --gray-500:  #6B7280;
            --gray-600:  #4B5563;
            --gray-700:  #374151;
            --gray-800:  #163B2D;
        }
        body { background: #F8FBF9; }

        .main-content { margin-left: 260px; padding: 24px 40px 80px; min-height: 100vh; }

        .page-card {
            background: white;
            border-radius: 24px;
            padding: 32px 36px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }

        .page-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 50px;
            background: var(--green-500);
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .page-button:hover { background: var(--green-600); }
        .page-button.light { background: #fff; color: var(--green-700); border: 1px solid var(--green-100); }

        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap table { min-width: 640px; width: 100%; }
        .table-wrap th {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            text-align: left;
        }
        .table-wrap td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--gray-700);
            border-top: 1px solid var(--gray-100);
            vertical-align: middle;
        }
        .table-wrap tr:hover td { background: var(--gray-50); }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: var(--green-50);
            color: var(--green-700);
        }
        .status-badge.closed { background: var(--gray-100); color: var(--gray-500); }

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

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 80px; }
            .page-card { padding: 24px 20px; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .page-card { padding: 20px 16px; border-radius: 16px; }
            .table-wrap table { min-width: 520px; }
        }
    </style>
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['seeker.requests'],
        'role'   => 'Help Seeker',
    ])

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Request History</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Track your support requests and their progress.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now('Asia/Manila')->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- History Card -->
        <div class="page-card">

            <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
                <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                    Your requests
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-50 text-green-700">{{ $requests->count() }}</span>
                </h2>
                <a href="{{ route('request.screening') }}" class="page-button">
                    <i class="fas fa-comment-dots"></i> Request support
                </a>
            </div>

            @if($requests->isNotEmpty())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $item)
                                @php
                                    $closed   = in_array($item->session_status, ['evaluated','cancelled','no_show'], true);
                                    $status   = ucwords(str_replace('_',' ',$item->workflow_state ?? $item->session_status));
                                    $dateText = $item->created_date?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A');
                                @endphp
                                <tr>
                                    <td class="font-semibold text-gray-800">#{{ $item->id }}</td>
                                    <td class="text-gray-600">{{ $dateText }}</td>
                                    <td><span class="status-badge {{ $closed ? 'closed' : '' }}">{{ $status }}</span></td>
                                    <td>
                                        @if($item->session_status === 'completed')
                                            <a href="{{ route('session.evaluation', ['session_id' => $item->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold bg-green-50 text-green-700 hover:bg-green-100 transition">
                                                Give feedback <i class="fas fa-arrow-right text-[10px]"></i>
                                            </a>
                                        @elseif($closed)
                                            <span class="text-gray-400 text-sm">Closed</span>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('request.matching') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold bg-white text-green-700 border border-green-100 hover:bg-green-50 transition">
                                                    View request <i class="fas fa-arrow-right text-[10px]"></i>
                                                </a>
                                                <form method="POST" action="{{ route('request.cancel', $item) }}" onsubmit="return confirm('Cancel this request? It will close and leave the queue. Its history is retained.')">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold bg-white text-red-500 border border-red-100 hover:bg-red-50 transition">
                                                        <i class="fas fa-ban"></i> Cancel
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <div class="text-5xl mb-4 text-green-500"><i class="fas fa-clipboard-list" aria-hidden="true"></i></div>
                    <h3 class="text-lg font-semibold text-gray-800">No requests yet</h3>
                    <p class="text-gray-500 text-sm mt-1">When you request support, its progress will appear here.</p>
                    <a href="{{ route('request.screening') }}" class="inline-block mt-4 px-6 py-2 bg-green-500 text-white text-sm font-semibold rounded-full hover:bg-green-600 transition">
                        Request Support
                    </a>
                </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Every request is a step toward healing.
        </div>

    </main>

    @include('layouts.partials.pwa-banner')

</body>
</html>