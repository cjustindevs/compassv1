<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Voice Session</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #F0F8F2; --green-100: #DCF5E0; --green-200: #A8E0B0; --green-300: #6DCB80;
            --green-400: #30B650; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
            --green-800: #01562B; --green-900: #003D1E;
            --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB; --gray-300: #D1D5DB;
            --gray-400: #9CA3AF; --gray-500: #6B7280; --gray-600: #4B5563; --gray-700: #374151;
            --gray-800: #1F2937; --gray-900: #111827;
        }

        body { background: #F5F8F6; font-family: 'Inter', sans-serif; }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .call-container {
            background: linear-gradient(160deg, #F0F8F2 0%, #FFFFFF 60%);
            border-radius: 24px;
            border: 1px solid rgba(4, 160, 82, 0.08);
            padding: 48px 32px;
            text-align: center;
            max-width: 560px;
            margin: 0 auto;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.04);
        }
        .call-avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green-400), var(--green-600));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 36px;
            margin: 0 auto 16px;
            box-shadow: 0 8px 32px rgba(4, 160, 82, 0.3);
            animation: breathing-ring 3s ease-in-out infinite;
        }
        @keyframes breathing-ring {
            0%, 100% { box-shadow: 0 0 0 0 rgba(4, 160, 82, 0.35), 0 8px 32px rgba(4, 160, 82, 0.3); }
            50% { box-shadow: 0 0 0 14px rgba(4, 160, 82, 0), 0 8px 32px rgba(4, 160, 82, 0.3); }
        }
        .call-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--green-600);
            background: var(--green-50);
            padding: 5px 14px;
            border-radius: 20px;
        }
        .call-status .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green-500); animation: pulse 1.4s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

        .timer {
            font-size: 40px;
            font-weight: 800;
            color: var(--gray-800);
            font-variant-numeric: tabular-nums;
            letter-spacing: 1px;
        }

        .call-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.2s ease;
        }
        .call-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 32px rgba(0, 0, 0, 0.12); }
        .call-btn.muted { background: #FEF3C7; color: #D97706; }
        .call-btn.active { background: white; color: var(--gray-600); border: 1px solid var(--gray-200); box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .call-btn.end {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35);
        }
        .call-btn.end:hover { box-shadow: 0 10px 36px rgba(220, 38, 38, 0.45); }

        .consent-banner {
            background: white;
            border: 1px solid var(--gray-200);
            border-left: 4px solid var(--green-500);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .call-container { padding: 36px 20px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        <!-- Top Bar (Mobile) -->
        <div class="flex items-center justify-between py-2 mb-4 md:hidden">
            <button class="hamburger" id="hamburgerBtn">
                <i class="fas fa-bars"></i>
            </button>
            <span class="text-sm font-semibold text-gray-700">Voice Session</span>
            <span class="w-8"></span>
        </div>

        <!-- Consent Banner -->
        @if(($consentGiven ?? true))
            <div class="consent-banner">
                <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-shield-alt text-[#04A052]"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">Recording consent on</p>
                    <p class="text-xs text-gray-500">You consented to recording. You can stop at any time; your alias stays protected.</p>
                </div>
            </div>
        @endif

        <!-- Call Card -->
        <div class="call-container">
            <div class="call-avatar">{{ Illuminate\Support\Str::substr($helperName, 0, 1) }}</div>

            <div class="call-status">
                <span class="dot"></span> Connected
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mt-3">{{ $helperName }}</h1>
            <p class="text-sm text-gray-500">Peer Helper · Voice Session</p>

            <div class="timer mt-6" id="callTimer">00:00</div>
            <p class="text-xs text-gray-400 mt-1">Session in progress</p>

            <div class="flex items-center justify-center gap-5 mt-8">
                <button class="call-btn active" onclick="toggleMute(this)" title="Mute">
                    <i class="fas fa-microphone"></i>
                </button>
                <button class="call-btn end" onclick="document.getElementById('endSessionForm').submit()" title="End session">
                    <i class="fas fa-phone-slash"></i>
                </button>
                <button class="call-btn active" onclick="toggleSpeaker(this)" title="Speaker">
                    <i class="fas fa-volume-up"></i>
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-4">Tip: find a quiet, private place when you can.</p>
        </div>

        <form id="endSessionForm" method="POST" action="{{ route('session.end') }}" class="hidden">
            @csrf
        </form>

    </main>

    <input type="hidden" id="sessionId" value="{{ $session->id }}">

    @include('partials.sidebar', [
        'active' => ['session.voice'],
        'role'   => 'Help Seeker',
    ])

    @vite(['resources/js/app.js'])

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ── Session timer ──
            let seconds = 0;
            const timerEl = document.getElementById('callTimer');
            if (timerEl) {
                setInterval(function () {
                    seconds++;
                    const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
                    const secs = String(seconds % 60).padStart(2, '0');
                    timerEl.textContent = mins + ':' + secs;
                }, 1000);
            }

            // ── Session ended by the helper → go to evaluation ──
            const sessionId = document.getElementById('sessionId')?.value;
            if (sessionId && window.Echo) {
                window.Echo.private('session.' + sessionId)
                    .listen('SessionEnded', function (event) {
                        if (event.ended_by === 'helper') {
                            window.location.href = event.seeker_redirect || '/session/evaluation';
                        }
                    });
            }
        });

        function toggleMute(btn) {
            btn.classList.toggle('active');
            btn.classList.toggle('muted');
            const icon = btn.querySelector('i');
            icon.className = btn.classList.contains('muted') ? 'fas fa-microphone-slash' : 'fas fa-microphone';
        }

        function toggleSpeaker(btn) {
            btn.classList.toggle('active');
            btn.classList.toggle('muted');
            const icon = btn.querySelector('i');
            icon.className = btn.classList.contains('muted') ? 'fas fa-volume-mute' : 'fas fa-volume-up';
        }
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>