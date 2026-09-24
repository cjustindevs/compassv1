<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Referral Decisions</title>

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
            --red-600:   #DC2626;
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
        .page-button.danger { background: #fff; color: var(--red-600); border: 1px solid #FECACA; }

        .info-note {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            background: var(--green-50);
            border: 1px solid var(--green-100);
            border-radius: 14px;
            margin-bottom: 24px;
            color: var(--gray-700);
            font-size: 14px;
            line-height: 1.7;
        }
        .info-note i { color: var(--green-500); margin-top: 3px; }

        .referral-card {
            padding: 22px;
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            margin-bottom: 14px;
        }
        .referral-card h3 { font-size: 15px; font-weight: 600; color: var(--gray-800); margin-bottom: 6px; }
        .referral-card p { font-size: 14px; line-height: 1.7; color: var(--gray-500); margin-bottom: 16px; }

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
        .status-badge.muted { background: var(--gray-100); color: var(--gray-500); }

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
        }
    </style>
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['seeker.referrals'],
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Referral Decisions</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Review recommendations for professional support.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now('Asia/Manila')->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Referrals Card -->
        <div class="page-card">

            <div class="info-note">
                <i class="fas fa-share-nodes"></i>
                <p>A referral offers support from an authorized professional. Your adviser must approve it, and you decide whether to proceed. Contact details are collected separately only when needed for coordination.</p>
            </div>

            @if(session('success'))
                <div role="status" class="info-note mb-4">{{ session('success') }}</div>
            @endif

            @forelse($referrals as $referral)
                @php($statusLabel = ucwords(str_replace('_', ' ', $referral->status)))
                <article class="referral-card">
                    <div class="flex items-center justify-between flex-wrap gap-3 mb-2">
                        <h3>
                            <i class="fas fa-user-doctor text-green-500 mr-2"></i>Referral #{{ $referral->id }}
                        </h3>
                        <span class="status-badge {{ $referral->help_seeker_consent ? '' : 'muted' }}">{{ $statusLabel }}</span>
                    </div>

                    @if($referral->status === 'pending_consent')
                        <p>Your adviser recommends professional support beyond the scope of peer support. Agreeing allows the assigned professional to review the authorized case records.</p>
                        <form method="POST" action="{{ route('referrals.consent', $referral) }}" class="flex flex-wrap gap-3">
                            @csrf
                            <button class="page-button" type="submit" name="consent_given" value="1">Agree to referral</button>
                            <button class="page-button light" type="submit" name="consent_given" value="0">Decline</button>
                        </form>
                    @elseif($referral->help_seeker_consent)
                        <p>You agreed to this referral. Provide your contact details when coordination is needed, or withdraw consent to stop future professional access.</p>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('identity.form', $referral) }}" class="page-button light">
                                <i class="fas fa-address-card"></i> Provide contact details for coordination
                            </a>
                            <form method="POST" action="{{ route('seeker.privacy.decision') }}" data-confirm="Withdraw referral consent and stop future professional access?">
                                @csrf
                                <input type="hidden" name="purpose" value="referral">
                                <input type="hidden" name="decision" value="withdrawn">
                                <input type="hidden" name="referral_id" value="{{ $referral->id }}">
                                <button class="page-button danger" type="submit">Withdraw referral consent</button>
                            </form>
                        </div>
                    @else
                        <p>This referral is awaiting your adviser's review. There is nothing to do right now.</p>
                    @endif
                </article>
            @empty
                <div class="text-center py-12">
                    <h3 class="text-lg font-semibold text-gray-800">No referral decisions yet</h3>
                    <p class="text-gray-500 text-sm mt-1">Recommendations from your adviser will appear here.</p>
                </div>
            @endforelse

        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Every decision you make is respected.
        </div>

    </main>

    @include('layouts.partials.pwa-banner')

</body>
</html>