<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Emergency Support</title>

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

        body { background: #FBF5F5; font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: #DC2626; border-radius: 4px; }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .emergency-hero {
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 45%, #FFFFFF 100%);
            border-radius: 24px;
            padding: 40px 36px;
            border: 1px solid rgba(220, 38, 38, 0.12);
            position: relative;
            overflow: hidden;
        }
        .emergency-hero::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(220, 38, 38, 0.07) 0%, transparent 70%);
            border-radius: 50%;
        }
        .emergency-hero .hero-content { position: relative; z-index: 1; }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: #7F1D1D;
            line-height: 1.2;
        }
        .hero-sub { color: var(--gray-600); font-size: 1rem; max-width: 520px; line-height: 1.7; }

        .hotline-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        .hotline-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(220, 38, 38, 0.08); }

        .crisis-btn {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            font-weight: 700;
            padding: 14px 32px;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.3);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .crisis-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(220, 38, 38, 0.4); }

        .safety-step {
            background: white;
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .safety-step .num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #FEF2F2;
            color: #DC2626;
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .emergency-hero { padding: 28px 20px; }
            .hero-title { font-size: 1.7rem; }
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Emergency Support</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Immediate help is available, whenever you need it.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Hero -->
        <div class="emergency-hero mb-6">
            <div class="hero-content">
                <div class="inline-flex items-center gap-2 text-sm font-medium text-red-600 bg-white/70 px-4 py-1.5 rounded-full backdrop-blur-sm mb-4">
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                    Crisis &amp; Emergency Resources
                </div>
                <h1 class="hero-title">If you are in immediate danger,<br>please reach out <em>right now</em>.</h1>
                <p class="hero-sub mt-3">
                    You matter, and your safety comes first. Call your local emergency number,
                    a crisis hotline below, or talk to a trained professional immediately.
                </p>
                <div class="flex flex-wrap gap-3 mt-5">
                    <a href="tel:911" class="crisis-btn">
                        <i class="fas fa-phone-alt"></i> Call 911
                    </a>
                    <a href="{{ route('request.screening') }}" class="inline-flex items-center gap-2 font-semibold text-sm px-6 py-3.5 rounded-xl bg-white border-2 border-red-500 text-red-600 hover:bg-red-50 transition">
                        <i class="fas fa-comment-dots"></i> Request Support Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Hotlines -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="hotline-card">
                <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center mb-3">
                    <i class="fas fa-heartbeat text-xl text-red-600"></i>
                </div>
                <h3 class="font-bold text-gray-800">Crisis Hotline</h3>
                <p class="text-sm text-gray-500 mt-1">24/7 crisis counseling and emotional support.</p>
                <a href="tel:09171234567" class="inline-block mt-3 text-red-600 font-bold hover:underline">
                    <i class="fas fa-phone mr-1"></i> 0917-123-4567
                </a>
            </div>
            <div class="hotline-card">
                <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center mb-3">
                    <i class="fas fa-phone-volume text-xl text-amber-600"></i>
                </div>
                <h3 class="font-bold text-gray-800">Hopeline PH</h3>
                <p class="text-sm text-gray-500 mt-1">Philippines suicide prevention hotline.</p>
                <a href="tel:0288044673" class="inline-block mt-3 text-red-600 font-bold hover:underline">
                    <i class="fas fa-phone mr-1"></i> 02-8804-HOPE (4673)
                </a>
            </div>
            <div class="hotline-card">
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center mb-3">
                    <i class="fas fa-hands-helping text-xl text-blue-600"></i>
                </div>
                <h3 class="font-bold text-gray-800">National Emergency</h3>
                <p class="text-sm text-gray-500 mt-1">Philippine National Police / Fire / Rescue.</p>
                <a href="tel:911" class="inline-block mt-3 text-red-600 font-bold hover:underline">
                    <i class="fas fa-phone mr-1"></i> 911
                </a>
            </div>
        </div>

        <!-- Safety Plan -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-gray-800 text-lg">While you wait, try to stay safe</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="safety-step">
                    <div class="num">1</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Remove yourself from danger</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Move to a safe place away from anything harmful.</p>
                    </div>
                </div>
                <div class="safety-step">
                    <div class="num">2</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Stay with someone you trust</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Do not be alone. Ask a friend or family member to stay.</p>
                    </div>
                </div>
                <div class="safety-step">
                    <div class="num">3</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Call a hotline above</h4>
                        <p class="text-sm text-gray-500 mt-0.5">Trained counselors are available 24/7. It's okay to call.</p>
                    </div>
                </div>
                <div class="safety-step">
                    <div class="num">4</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Remove immediate means of harm</h4>
                        <p class="text-sm text-gray-500 mt-0.5">If it's safe to do so, move harmful items away or lock them.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-red-500 mr-1"></i>
            You are not alone. Please reach out — people care about you.
        </div>

    </main>

    @include('partials.sidebar', [
        'active' => ['emergency'],
        'role'   => 'Help Seeker',
    ])

</body>
</html>