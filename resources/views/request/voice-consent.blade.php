<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Voice Session Consent</title>

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

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .consent-card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            padding: 32px;
            max-width: 640px;
            margin: 0 auto;
        }

        .consent-point {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .consent-point:last-of-type { border-bottom: none; }
        .consent-point .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--green-50);
            color: var(--green-500);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .btn-primary-consent {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 14px;
            border: none;
            box-shadow: 0 4px 20px rgba(4, 160, 82, 0.25);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
        }
        .btn-primary-consent:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(4, 160, 82, 0.35); }
        .btn-chat-fallback {
            background: white;
            color: var(--gray-700);
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-300);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
        }
        .btn-chat-fallback:hover { background: var(--gray-50); border-color: var(--gray-400); }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .consent-card { padding: 24px 18px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Voice Session Consent</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Your privacy matters. Confirm how your session will be handled.</p>
                </div>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="flex items-center justify-center gap-2 mb-6">
            <div class="w-8 h-8 rounded-full bg-[#04A052] text-white text-sm font-bold flex items-center justify-center">✓</div>
            <div class="w-10 h-0.5 bg-[#04A052]"></div>
            <div class="w-8 h-8 rounded-full bg-[#04A052] text-white text-sm font-bold flex items-center justify-center">✓</div>
            <div class="w-10 h-0.5 bg-[#04A052]"></div>
            <div class="w-8 h-8 rounded-full bg-[#04A052] text-white text-sm font-bold flex items-center justify-center">✓</div>
            <div class="w-10 h-0.5 bg-gray-200"></div>
            <div class="w-8 h-8 rounded-full bg-[#04A052] text-white text-sm font-bold flex items-center justify-center">4</div>
        </div>

        <div class="consent-card">
            <div class="text-center mb-6">
                <div class="text-5xl mb-3">🎙️</div>
                <h2 class="text-2xl font-bold text-gray-800">Before we start your voice session</h2>
                <p class="text-sm text-gray-500 mt-2">
                    You chose a voice session with your peer helper. Here's what that means:
                </p>
            </div>

            <div class="consent-point">
                <div class="icon"><i class="fas fa-user-shield"></i></div>
                <div>
                    <p class="font-semibold text-sm text-gray-800">You stay anonymous</p>
                    <p class="text-xs text-gray-500 mt-0.5">Your alias is used throughout the session. Your identity is never shared with the helper.</p>
                </div>
            </div>
            <div class="consent-point">
                <div class="icon"><i class="fas fa-record-vinyl"></i></div>
                <div>
                    <p class="font-semibold text-sm text-gray-800">Recording is optional</p>
                    <p class="text-xs text-gray-500 mt-0.5">Recordings are only kept if the helper needs to write a quality report — and only with your permission.</p>
                </div>
            </div>
            <div class="consent-point">
                <div class="icon"><i class="fas fa-hand-paper"></i></div>
                <div>
                    <p class="font-semibold text-sm text-gray-800">You can stop anytime</p>
                    <p class="text-xs text-gray-500 mt-0.5">You may decline or end the session at any moment — no questions asked.</p>
                </div>
            </div>
            <div class="consent-point">
                <div class="icon"><i class="fas fa-lock"></i></div>
                <div>
                    <p class="font-semibold text-sm text-gray-800">Protected &amp; confidential</p>
                    <p class="text-xs text-gray-500 mt-0.5">Sessions are encrypted and governed by the COMPASS privacy policy.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 mt-6">
                <form method="POST" action="{{ route('request.voice-consent.process') }}">
                    @csrf
                    <button type="submit" class="btn-primary-consent">
                        <i class="fas fa-microphone mr-2"></i> I Consent — Start Voice Session
                    </button>
                </form>
                <form method="POST" action="{{ route('request.voice-consent.decline') }}">
                    @csrf
                    <button type="submit" class="btn-chat-fallback">
                        <i class="fas fa-comment-dots mr-2"></i> Prefer Chat Instead
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 mt-5">
                <i class="fas fa-info-circle mr-1"></i>
                You can change your choice later from Settings.
            </p>
        </div>

    </main>

    @include('partials.sidebar', [
        'active' => ['request.voice-consent*'],
        'role'   => 'Help Seeker',
    ])

</body>
</html>