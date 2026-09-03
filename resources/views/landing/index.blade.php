<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="COMPASS – A calm place to talk. A safe place to heal. Peer support for students." />
    <meta name="theme-color" content="#16A34A" />
    <title>COMPASS – Peer Support, Student Wellness</title>

    <!-- Google Fonts: Figtree -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* ──────────────── BASE ──────────────── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Figtree', sans-serif;
            background: #FAFAFA;
            color: #1F2937;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ──────────────── COLORS ──────────────── */
        :root {
            --green-primary: #16A34A;
            --green-deep: #14532D;
            --green-light: #DCFCE7;
            --green-mint: #ECFDF5;
            --green-gradient: linear-gradient(135deg, #16A34A, #22C55E);
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
            --shadow-lg: 0 10px 25px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
            --shadow-xl: 0 20px 50px -8px rgba(0,0,0,0.12);
            --shadow-2xl: 0 25px 60px -12px rgba(0,0,0,0.15);
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
        }

        /* ──────────────── UTILITIES ──────────────── */
        .gradient-text {
            background: var(--green-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .gradient-bg {
            background: var(--green-gradient);
        }

        .glass {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .glass-dark {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .card-hover {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }

        .btn-primary {
            background: var(--green-gradient);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(22, 163, 74, 0.35);
        }

        .btn-primary:hover {
            transform: scale(1.04);
            box-shadow: 0 8px 40px rgba(22, 163, 74, 0.45);
        }

        .btn-outline {
            border: 2px solid var(--green-primary);
            color: var(--green-primary);
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: var(--green-primary);
            color: white;
            transform: scale(1.04);
            box-shadow: 0 8px 30px rgba(22, 163, 74, 0.25);
        }

        /* ──────────────── SCROLLBAR ──────────────── */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--green-primary);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--green-deep);
        }

        /* ──────────────── ANIMATIONS ──────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-16px); }
        }
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-12px) rotate(2deg); }
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        @keyframes countUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-up {
            animation: fadeUp 0.8s ease-out forwards;
            opacity: 0;
        }
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
            opacity: 0;
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        .animate-float-slow {
            animation: floatSlow 8s ease-in-out infinite;
        }
        .animate-pulse-glow {
            animation: pulseGlow 4s ease-in-out infinite;
        }
        .animate-count-up {
            animation: countUp 0.6s ease-out forwards;
        }

        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }
        .delay-600 { animation-delay: 0.6s; }
        .delay-700 { animation-delay: 0.7s; }
        .delay-800 { animation-delay: 0.8s; }

        /* ──────────────── NAV ──────────────── */
        .nav-scrolled {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            box-shadow: var(--shadow-sm);
        }

        /* ──────────────── STAT COUNTER ──────────────── */
        .stat-number {
            font-size: 2.75rem;
            font-weight: 800;
            line-height: 1.1;
            background: var(--green-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ──────────────── TESTIMONIAL QUOTE ──────────────── */
        .quote-mark {
            font-size: 3rem;
            line-height: 1;
            color: var(--green-primary);
            opacity: 0.3;
            font-family: Georgia, serif;
        }

        /* ──────────────── RESPONSIVE ──────────────── */
        @media (max-width: 768px) {
            .stat-number {
                font-size: 2rem;
            }
        }

        /* ──────────────── MISC ──────────────── */
        .section-badge {
            display: inline-block;
            padding: 0.375rem 1rem;
            border-radius: 9999px;
            background: var(--green-mint);
            color: var(--green-primary);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        .section-title {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.2;
        }

        .section-title .highlight {
            background: var(--green-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-sub {
            font-size: 1.125rem;
            color: var(--gray-500);
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (min-width: 1024px) {
            .section-title {
                font-size: 2.75rem;
            }
        }
    </style>
</head>
<body>

    <!-- ══════════════════════════════════════════════ -->
    <!--  HEADER / NAVIGATION                         -->
    <!-- ══════════════════════════════════════════════ -->

    <header id="navbar" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">

                <!-- Logo -->
                <a href="/" class="inline-flex items-center group">
                    <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS"
                         class="h-7 md:h-9 w-auto transition-transform group-hover:scale-105">
                </a>

                <!-- Desktop Nav -->
                <nav class="hidden lg:flex items-center gap-8">
                    <a href="#home" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors">Home</a>
                    <a href="#features" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors">Features</a>
                    <a href="#how-it-works" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors">How It Works</a>
                    <a href="#about" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors">About</a>
                    <a href="#contact" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors">Contact</a>
                </nav>

                <!-- Right buttons -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="hidden sm:inline-block text-sm font-medium text-gray-600 hover:text-green-600 transition-colors px-3 py-2">
                        Log In
                    </a>
                    <a href="{{ route('register') }}" class="px-5 py-2.5 text-sm font-semibold rounded-xl btn-primary shadow-lg">
                        Get Started
                    </a>
                    <!-- Mobile toggle -->
                    <button id="mobileToggle" class="lg:hidden text-gray-600 hover:text-green-600 p-2 -mr-2">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>

            </div>

            <!-- Mobile menu -->
            <div id="mobileMenu" class="hidden lg:hidden pb-4 pt-2 border-t border-gray-200/50">
                <div class="flex flex-col gap-2">
                    <a href="#home" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors px-3 py-2 rounded-lg hover:bg-green-50">Home</a>
                    <a href="#features" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors px-3 py-2 rounded-lg hover:bg-green-50">Features</a>
                    <a href="#how-it-works" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors px-3 py-2 rounded-lg hover:bg-green-50">How It Works</a>
                    <a href="#about" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors px-3 py-2 rounded-lg hover:bg-green-50">About</a>
                    <a href="#contact" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors px-3 py-2 rounded-lg hover:bg-green-50">Contact</a>
                    <hr class="border-gray-200/50 my-1" />
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-green-600 transition-colors px-3 py-2">Log In</a>
                    <a href="{{ route('register') }}" class="px-4 py-2.5 text-sm font-semibold rounded-xl btn-primary text-center">Get Started</a>
                </div>
            </div>
        </div>
    </header>

    <!-- ══════════════════════════════════════════════ -->
    <!--  HERO SECTION                                -->
    <!-- ══════════════════════════════════════════════ -->

    <section id="home" class="relative pt-28 md:pt-36 pb-16 md:pb-24 overflow-hidden bg-gradient-to-b from-green-50/60 via-white to-white">
        <!-- Background blur shapes -->
        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-green-200/30 blur-3xl animate-float-slow"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-emerald-200/25 blur-3xl animate-float-slow" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-green-100/20 blur-3xl"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 xl:gap-16 items-center">

                <!-- ─── LEFT ─── -->
                <div class="animate-fade-up">
                    <span class="section-badge mb-6 inline-flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                        Project Dial-A-Friend
                    </span>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-extrabold leading-[1.08] tracking-tight text-gray-900">
                        A Calm Place to <span class="gradient-text">Talk</span>.<br />
                        A Safe Place to <span class="gradient-text">Heal</span>.
                    </h1>

                    <p class="mt-6 text-lg text-gray-500 leading-relaxed max-w-lg">
                        COMPASS connects students with trained peer supporters through confidential chat and voice sessions, providing a safe space for emotional support and early intervention.
                    </p>

                    <!-- CTA buttons -->
                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="{{ route('register') }}" class="px-8 py-3.5 rounded-xl btn-primary font-semibold text-base shadow-lg shadow-green-500/25">
                            <i class="fas fa-comment-dots mr-2"></i>Get Started
                        </a>
                        <a href="#features" class="px-8 py-3.5 rounded-xl btn-outline font-semibold text-base">
                            Learn More
                        </a>
                    </div>

                    <!-- Trust badges -->
                    <div class="mt-10 flex flex-wrap gap-6 text-sm text-gray-500">
                        <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500 text-base"></i> Confidential</span>
                        <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500 text-base"></i> Anonymous</span>
                        <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500 text-base"></i> Student Peer Support</span>
                        <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500 text-base"></i> Free</span>
                    </div>
                </div>

                <!-- ─── RIGHT ─── -->
                <div class="relative flex justify-center lg:justify-end animate-fade-up delay-200">
                    <!-- Floating green circles -->
                    <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full bg-green-300/20 blur-2xl animate-float-slow"></div>
                    <div class="absolute -bottom-6 -left-6 w-24 h-24 rounded-full bg-emerald-300/20 blur-2xl animate-float-slow" style="animation-delay: 3s;"></div>
                    <div class="absolute top-1/2 right-0 w-20 h-20 rounded-full bg-green-200/20 blur-xl animate-pulse-glow"></div>

                    <!-- Dashboard Mockup -->
                    <div class="relative w-full max-w-lg glass-dark rounded-2xl p-6 shadow-2xl border border-white/50 backdrop-blur-xl">
                        <!-- Top bar -->
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></span>
                                <span class="text-sm font-semibold text-gray-800">Live Support Session</span>
                            </div>
                            <span class="text-xs font-medium text-green-600 bg-green-50 px-3 py-1 rounded-full">Active</span>
                        </div>

                        <!-- Mood indicator -->
                        <div class="flex items-center gap-4 p-4 rounded-xl bg-green-50/70 border border-green-100/50">
                            <div class="text-3xl">😊</div>
                            <div>
                                <div class="text-sm font-semibold text-gray-800">Feeling Better</div>
                                <div class="text-xs text-gray-500">You're doing well today</div>
                            </div>
                            <div class="ml-auto flex items-center gap-1">
                                <i class="fas fa-arrow-up text-green-500 text-sm"></i>
                                <span class="text-sm font-semibold text-green-600">+18%</span>
                            </div>
                        </div>

                        <!-- Progress -->
                        <div class="mt-4 p-4 rounded-xl bg-white/60 border border-gray-100/50">
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="text-gray-600 font-medium">Wellness Progress</span>
                                <span class="text-gray-800 font-semibold">72%</span>
                            </div>
                            <div class="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full w-[72%] bg-gradient-to-r from-green-400 to-emerald-500 rounded-full transition-all duration-1000"></div>
                            </div>
                        </div>

                        <!-- Weekly Chart mini -->
                        <div class="mt-4 p-4 rounded-xl bg-white/60 border border-gray-100/50">
                            <div class="flex items-center justify-between text-sm mb-3">
                                <span class="text-gray-600 font-medium">Weekly Wellness</span>
                                <span class="text-xs text-gray-400">Last 7 days</span>
                            </div>
                            <div class="flex items-end gap-2 h-10">
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="w-full h-6 bg-green-200 rounded-sm"></div>
                                    <span class="text-[10px] text-gray-400">M</span>
                                </div>
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="w-full h-8 bg-green-300 rounded-sm"></div>
                                    <span class="text-[10px] text-gray-400">T</span>
                                </div>
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="w-full h-4 bg-green-200 rounded-sm"></div>
                                    <span class="text-[10px] text-gray-400">W</span>
                                </div>
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="w-full h-10 bg-green-400 rounded-sm"></div>
                                    <span class="text-[10px] text-gray-400">T</span>
                                </div>
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="w-full h-7 bg-green-300 rounded-sm"></div>
                                    <span class="text-[10px] text-gray-400">F</span>
                                </div>
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="w-full h-9 bg-green-300 rounded-sm"></div>
                                    <span class="text-[10px] text-gray-400">S</span>
                                </div>
                                <div class="flex-1 flex flex-col items-center gap-1">
                                    <div class="w-full h-5 bg-green-200 rounded-sm"></div>
                                    <span class="text-[10px] text-gray-400">S</span>
                                </div>
                            </div>
                        </div>

                        <!-- Badge -->
                        <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                            <span><i class="fas fa-shield-alt text-green-500 mr-1"></i> Peer Support Active</span>
                            <span><i class="fas fa-lock text-green-500 mr-1"></i> End-to-End Encrypted</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════ -->
    <!--  SECTION 2 – FEATURES                        -->
    <!-- ══════════════════════════════════════════════ -->

    <section id="features" class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="section-badge mb-4">Trusted Features</span>
                <h2 class="section-title">
                    Designed for <span class="highlight">Student Wellness</span>
                </h2>
                <p class="section-sub mt-3">
                    Everything you need for safe, confidential, and effective peer support.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">

                <!-- Card 1 -->
                <div class="card-hover p-6 md:p-8 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 group animate-fade-up delay-100">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="fas fa-user-secret text-xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1.5">Anonymous Conversations</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Your identity remains protected. Share openly without fear.</p>
                </div>

                <!-- Card 2 -->
                <div class="card-hover p-6 md:p-8 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 group animate-fade-up delay-200">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="fas fa-brain text-xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1.5">AI-Assisted Risk Detection</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Early emotional risk identification for timely support.</p>
                </div>

                <!-- Card 3 -->
                <div class="card-hover p-6 md:p-8 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 group animate-fade-up delay-300">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="fas fa-headset text-xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1.5">Voice &amp; Chat Support</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Choose how you feel comfortable communicating.</p>
                </div>

                <!-- Card 4 -->
                <div class="card-hover p-6 md:p-8 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 group animate-fade-up delay-400">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="fas fa-user-graduate text-xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1.5">Trained Student Helpers</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Verified peer supporters supervised by faculty advisers.</p>
                </div>

                <!-- Card 5 -->
                <div class="card-hover p-6 md:p-8 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 group animate-fade-up delay-500">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="fas fa-arrow-right text-xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1.5">Referral Support</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Escalation for serious concerns to licensed professionals.</p>
                </div>

                <!-- Card 6 -->
                <div class="card-hover p-6 md:p-8 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 group animate-fade-up delay-600">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="fas fa-shield-alt text-xl text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1.5">Secure &amp; Confidential</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Encrypted communication and privacy protection.</p>
                </div>

            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════ -->
    <!--  SECTION 3 – HOW IT WORKS                    -->
    <!-- ══════════════════════════════════════════════ -->

    <section id="how-it-works" class="py-20 md:py-28 bg-gray-50/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="section-badge mb-4">How It Works</span>
                <h2 class="section-title">
                    Your Path to <span class="highlight">Support</span>
                </h2>
                <p class="section-sub mt-3">
                    Five simple steps to connect with a trained peer helper.
                </p>
            </div>

            <div class="relative grid md:grid-cols-5 gap-6 md:gap-8">

                <!-- Step 1 -->
                <div class="text-center animate-fade-up delay-100">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-green-100 to-green-50 border border-green-200/50 flex items-center justify-center text-2xl font-extrabold text-green-600 shadow-sm mb-4">1</div>
                    <h4 class="text-base font-bold text-gray-800 mb-1.5">Choose Concern</h4>
                    <p class="text-sm text-gray-500 leading-relaxed">Select what you'd like to talk about.</p>
                </div>

                <!-- Arrow -->
                <div class="hidden md:flex items-center justify-center text-gray-300 text-2xl">
                    <i class="fas fa-arrow-right"></i>
                </div>

                <!-- Step 2 -->
                <div class="text-center animate-fade-up delay-200">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-green-100 to-green-50 border border-green-200/50 flex items-center justify-center text-2xl font-extrabold text-green-600 shadow-sm mb-4">2</div>
                    <h4 class="text-base font-bold text-gray-800 mb-1.5">Get Matched</h4>
                    <p class="text-sm text-gray-500 leading-relaxed">Connect with a trained helper.</p>
                </div>

                <!-- Arrow -->
                <div class="hidden md:flex items-center justify-center text-gray-300 text-2xl">
                    <i class="fas fa-arrow-right"></i>
                </div>

                <!-- Step 3 -->
                <div class="text-center animate-fade-up delay-300">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-green-100 to-green-50 border border-green-200/50 flex items-center justify-center text-2xl font-extrabold text-green-600 shadow-sm mb-4">3</div>
                    <h4 class="text-base font-bold text-gray-800 mb-1.5">Chat or Voice</h4>
                    <p class="text-sm text-gray-500 leading-relaxed">Connect in a way that feels right.</p>
                </div>

                <!-- Arrow -->
                <div class="hidden md:flex items-center justify-center text-gray-300 text-2xl">
                    <i class="fas fa-arrow-right"></i>
                </div>

                <!-- Step 4 -->
                <div class="text-center animate-fade-up delay-400">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-green-100 to-green-50 border border-green-200/50 flex items-center justify-center text-2xl font-extrabold text-green-600 shadow-sm mb-4">4</div>
                    <h4 class="text-base font-bold text-gray-800 mb-1.5">Receive Guidance</h4>
                    <p class="text-sm text-gray-500 leading-relaxed">Support and coping strategies.</p>
                </div>

                <!-- Arrow -->
                <div class="hidden md:flex items-center justify-center text-gray-300 text-2xl">
                    <i class="fas fa-arrow-right"></i>
                </div>

                <!-- Step 5 -->
                <div class="text-center animate-fade-up delay-500">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-green-100 to-green-50 border border-green-200/50 flex items-center justify-center text-2xl font-extrabold text-green-600 shadow-sm mb-4">5</div>
                    <h4 class="text-base font-bold text-gray-800 mb-1.5">Feedback &amp; Resources</h4>
                    <p class="text-sm text-gray-500 leading-relaxed">Wellness tools and follow-up.</p>
                </div>

            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════ -->
    <!--  SECTION 4 – STATISTICS                      -->
    <!-- ══════════════════════════════════════════════ -->

    <section id="about" class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="section-badge mb-4">Trusted by Students</span>
                <h2 class="section-title">
                    Why Students Trust <span class="highlight">COMPASS</span>
                </h2>
                <p class="section-sub mt-3">
                    Real results from real students.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 text-center">

                <div class="animate-fade-up delay-100">
                    <div class="stat-number">98%</div>
                    <div class="text-sm text-gray-500 mt-1">Sessions Completed</div>
                </div>

                <div class="animate-fade-up delay-200">
                    <div class="stat-number">95%</div>
                    <div class="text-sm text-gray-500 mt-1">Users Felt Supported</div>
                </div>

                <div class="animate-fade-up delay-300">
                    <div class="stat-number">100%</div>
                    <div class="text-sm text-gray-500 mt-1">Confidential</div>
                </div>

                <div class="animate-fade-up delay-400">
                    <div class="stat-number">24/7</div>
                    <div class="text-sm text-gray-500 mt-1">Self-Help Resources</div>
                </div>

            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════ -->
    <!--  SECTION 5 – TESTIMONIALS                    -->
    <!-- ══════════════════════════════════════════════ -->

    <section class="py-20 md:py-28 bg-gray-50/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="section-badge mb-4">Testimonials</span>
                <h2 class="section-title">
                    Real Stories, <span class="highlight">Real Impact</span>
                </h2>
                <p class="section-sub mt-3">
                    Hear from students who found support through COMPASS.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 md:gap-8">

                <!-- Testimonial 1 -->
                <div class="glass p-6 md:p-8 rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 animate-fade-up delay-100">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-200 to-green-300 flex items-center justify-center text-lg font-bold text-green-800">JD</div>
                        <div>
                            <div class="font-semibold text-gray-800">Jamie Dela Cruz</div>
                            <div class="text-xs text-gray-400">BS Psychology, 3rd Year</div>
                        </div>
                    </div>
                    <div class="flex text-green-400 text-sm mb-3">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        "COMPASS gave me a safe space to open up when I needed it most. My helper listened without judgment."
                    </p>
                </div>

                <!-- Testimonial 2 -->
                <div class="glass p-6 md:p-8 rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 animate-fade-up delay-200">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-200 to-green-300 flex items-center justify-center text-lg font-bold text-green-800">MR</div>
                        <div>
                            <div class="font-semibold text-gray-800">Marcus Rivera</div>
                            <div class="text-xs text-gray-400">BS IT, 2nd Year</div>
                        </div>
                    </div>
                    <div class="flex text-green-400 text-sm mb-3">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        "I was hesitant at first, but the anonymous chat made it so easy. I felt heard and supported."
                    </p>
                </div>

                <!-- Testimonial 3 -->
                <div class="glass p-6 md:p-8 rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 animate-fade-up delay-300">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-200 to-green-300 flex items-center justify-center text-lg font-bold text-green-800">LT</div>
                        <div>
                            <div class="font-semibold text-gray-800">Lara Tan</div>
                            <div class="text-xs text-gray-400">BA Communication, 4th Year</div>
                        </div>
                    </div>
                    <div class="flex text-green-400 text-sm mb-3">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        "The breathing exercises and resources helped me manage my anxiety. COMPASS is a lifeline."
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════ -->
    <!--  SECTION 6 – SELF-HELP RESOURCES             -->
    <!-- ══════════════════════════════════════════════ -->

    <section id="resources" class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="section-badge mb-4">Self-Help Resources</span>
                <h2 class="section-title">
                    Tools for Your <span class="highlight">Wellness Journey</span>
                </h2>
                <p class="section-sub mt-3">
                    Access resources anytime, anywhere.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 gap-6 md:gap-8">

                <!-- Resource 1 -->
                <a href="#" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50/50 border border-green-100/50 p-6 md:p-8 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 animate-fade-up delay-100">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">🧘</div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-800">Breathing Exercises</h4>
                            <p class="text-sm text-gray-500 mt-1">Guided breathing techniques to reduce stress and anxiety.</p>
                            <span class="inline-block mt-3 text-sm font-medium text-green-600 group-hover:underline">Explore →</span>
                        </div>
                    </div>
                </a>

                <!-- Resource 2 -->
                <a href="#" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50/50 border border-green-100/50 p-6 md:p-8 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 animate-fade-up delay-200">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">🌿</div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-800">Grounding Techniques</h4>
                            <p class="text-sm text-gray-500 mt-1">Simple exercises to stay present and calm.</p>
                            <span class="inline-block mt-3 text-sm font-medium text-green-600 group-hover:underline">Explore →</span>
                        </div>
                    </div>
                </a>

                <!-- Resource 3 -->
                <a href="#" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50/50 border border-green-100/50 p-6 md:p-8 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 animate-fade-up delay-300">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">📓</div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-800">Mood Journal</h4>
                            <p class="text-sm text-gray-500 mt-1">Track your emotions and reflect on your day.</p>
                            <span class="inline-block mt-3 text-sm font-medium text-green-600 group-hover:underline">Explore →</span>
                        </div>
                    </div>
                </a>

                <!-- Resource 4 -->
                <a href="#" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50/50 border border-green-100/50 p-6 md:p-8 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 animate-fade-up delay-400">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">🆘</div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-800">Emergency Contacts</h4>
                            <p class="text-sm text-gray-500 mt-1">Immediate help when you need it most.</p>
                            <span class="inline-block mt-3 text-sm font-medium text-green-600 group-hover:underline">Explore →</span>
                        </div>
                    </div>
                </a>

            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════ -->
    <!--  SECTION 7 – FINAL CTA                       -->
    <!-- ══════════════════════════════════════════════ -->

    <section class="relative py-20 md:py-28 overflow-hidden">
        <!-- Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-green-900 via-green-800 to-emerald-900"></div>
        <div class="absolute -top-20 -right-20 w-80 h-80 rounded-full bg-green-500/20 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-20 w-80 h-80 rounded-full bg-emerald-500/20 blur-3xl"></div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <h2 class="text-3xl md:text-5xl font-extrabold text-white leading-tight animate-fade-up">
                You don't have to face<br />
                life's challenges <span class="text-green-300">alone</span>.
            </h2>
            <p class="mt-4 text-lg text-green-200/80 max-w-lg mx-auto animate-fade-up delay-200">
                Reach out today and connect with someone who cares.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4 animate-fade-up delay-300">
                <a href="{{ route('register') }}" class="px-10 py-4 rounded-xl bg-white text-green-800 font-semibold text-base shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105">
                    Start Your Journey
                </a>
                <a href="#features" class="px-10 py-4 rounded-xl border-2 border-white/30 text-white font-semibold text-base hover:bg-white/10 transition-all duration-300 hover:scale-105">
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════ -->
    <!--  FOOTER                                      -->
    <!-- ══════════════════════════════════════════════ -->

    <footer id="contact" class="bg-gray-900 border-t border-gray-800 py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">

                <!-- Brand -->
                <div>
                    <a href="/" class="inline-flex mb-4">
                        <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS" class="h-7 md:h-8 w-auto">
                    </a>
                    <p class="text-sm text-gray-400 leading-relaxed max-w-xs">
                        A calm place to talk. A safe place to heal.
                    </p>
                </div>

                <!-- Platform -->
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">Platform</h4>
                    <ul class="space-y-2.5 text-sm text-gray-400">
                        <li><a href="#home" class="hover:text-green-400 transition-colors">Home</a></li>
                        <li><a href="#features" class="hover:text-green-400 transition-colors">Features</a></li>
                        <li><a href="#resources" class="hover:text-green-400 transition-colors">Resources</a></li>
                        <li><a href="#about" class="hover:text-green-400 transition-colors">About</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">Support</h4>
                    <ul class="space-y-2.5 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-green-400 transition-colors">Help Center</a></li>
                        <li><a href="#" class="hover:text-green-400 transition-colors">FAQs</a></li>
                        <li><a href="#contact" class="hover:text-green-400 transition-colors">Contact</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">Legal</h4>
                    <ul class="space-y-2.5 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-green-400 transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-green-400 transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-green-400 transition-colors">Cookie Policy</a></li>
                    </ul>
                </div>

            </div>

            <!-- Bottom bar -->
            <div class="border-t border-gray-800 mt-10 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm text-gray-500">
                    &copy; 2026 <span class="text-green-400 font-medium">COMPASS</span> · Project Dial-A-Friend.
                </p>
                <div class="flex items-center gap-4 text-gray-500">
                    <a href="#" class="hover:text-green-400 transition-colors"><i class="fab fa-facebook text-lg"></i></a>
                    <a href="#" class="hover:text-green-400 transition-colors"><i class="fab fa-twitter text-lg"></i></a>
                    <a href="#" class="hover:text-green-400 transition-colors"><i class="fab fa-instagram text-lg"></i></a>
                    <a href="#" class="hover:text-green-400 transition-colors"><i class="fab fa-youtube text-lg"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ══════════════════════════════════════════════ -->
    <!--  SCRIPTS                                     -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        (function() {
            'use strict';

            // ── MOBILE MENU ──
            const toggle = document.getElementById('mobileToggle');
            const menu = document.getElementById('mobileMenu');

            if (toggle && menu) {
                toggle.addEventListener('click', function() {
                    menu.classList.toggle('hidden');
                });

                // Close on link click
                menu.querySelectorAll('a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        menu.classList.add('hidden');
                    });
                });
            }

            // ── NAVBAR SCROLL EFFECT ──
            const navbar = document.getElementById('navbar');

            window.addEventListener('scroll', function() {
                if (window.scrollY > 20) {
                    navbar.classList.add('nav-scrolled');
                    navbar.classList.remove('bg-transparent');
                } else {
                    navbar.classList.remove('nav-scrolled');
                    navbar.classList.add('bg-transparent');
                }
            });

            // ── SMOOTH SCROLL ──
            document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return;
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });

            // ── ANIMATED COUNTERS (simple) ──
            const counters = document.querySelectorAll('.stat-number');

            function animateCounter(el) {
                const text = el.textContent;
                const isPercent = text.includes('%');
                const isSlash = text.includes('/');
                const num = parseFloat(text);

                if (isNaN(num)) return;

                let current = 0;
                const target = num;
                const duration = 1500;
                const step = Math.max(1, Math.floor(target / 60));

                const update = () => {
                    current += step;
                    if (current >= target) {
                        el.textContent = text;
                        return;
                    }
                    if (isPercent) {
                        el.textContent = Math.floor(current) + '%';
                    } else if (isSlash) {
                        el.textContent = Math.floor(current) + '/' + text.split('/')[1];
                    } else {
                        el.textContent = Math.floor(current);
                    }
                    requestAnimationFrame(update);
                };

                // Start when visible
                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            update();
                            observer.unobserve(el);
                        }
                    });
                }, { threshold: 0.3 });

                observer.observe(el);
            }

            counters.forEach(animateCounter);

            // ── SCROLL REVEAL (Intersection Observer) ──
            const hiddenElements = document.querySelectorAll('.animate-fade-up, .animate-fade-in');

            const revealObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                    }
                });
            }, {
                threshold: 0.15,
                rootMargin: '0px 0px -50px 0px'
            });

            hiddenElements.forEach(function(el) {
                // Set initial opacity to 0 so we can fade in
                if (!el.classList.contains('counter')) {
                    el.style.opacity = '0';
                    revealObserver.observe(el);
                }
            });

            // ── BUTTON RIPPLE EFFECT ──
            document.querySelectorAll('.btn-primary, .btn-outline').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        border-radius: 50%;
                        background: rgba(255,255,255,0.3);
                        transform: scale(0);
                        animation: rippleAnim 0.6s ease-out forwards;
                        pointer-events: none;
                    `;

                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);

                    setTimeout(function() {
                        ripple.remove();
                    }, 700);
                });
            });

            // ── INJECT RIPPLE KEYFRAMES ──
            const styleSheet = document.createElement('style');
            styleSheet.textContent = `
                @keyframes rippleAnim {
                    to { transform: scale(4); opacity: 0; }
                }
            `;
            document.head.appendChild(styleSheet);

        })();
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
