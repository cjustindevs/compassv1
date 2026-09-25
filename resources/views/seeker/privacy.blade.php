<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Privacy and Consent</title>

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

        .main-content { padding: 24px 40px 80px; min-height: 100vh; }

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

        .consent-record {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding: 20px 22px;
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            margin-bottom: 14px;
        }
        .consent-record h3 { font-size: 15px; font-weight: 600; color: var(--gray-800); margin-bottom: 4px; }
        .consent-record p { font-size: 13px; color: var(--gray-500); }

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

        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap table { min-width: 500px; width: 100%; }
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
        }
        .table-wrap tr:hover td { background: var(--gray-50); }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 80px; }
            .page-card { padding: 24px 20px; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 16px 16px 100px; }
            .page-card { padding: 20px 16px; border-radius: 16px; }
            .table-wrap table { min-width: 460px; }
        }
    </style>
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['seeker.privacy'],
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Privacy and Consent</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Review your choices and manage your participation.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now('Asia/Manila')->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Privacy Card -->
        <div class="page-card">

            <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
                <h2 class="text-base font-semibold text-gray-800">Your privacy choices</h2>
                <button type="button" class="page-button light" data-open-seeker-consent>
                    <i class="fas fa-file-shield"></i> Terms and Privacy
                </button>
            </div>

            <div class="info-note">
                <i class="fas fa-circle-info"></i>
                <p>You choose whether to participate. Withdrawing consent closes current support requests. Past records are retained for authorized safety and recordkeeping; withdrawal does not delete them.</p>
            </div>

            @if(session('success'))
                <div role="status" class="info-note mb-4">{{ session('success') }}</div>
            @endif

            @foreach(['privacy_policy' => 'Privacy Notice', 'informed_consent' => 'Informed Consent'] as $purpose => $label)
                @php
                $accepted = app(\App\Services\ConsentService::class)->valid(auth()->user()->helpSeeker, $purpose);
                @endphp
                <div class="consent-record">
                    <div>
                        <h3>{{ $label }}</h3>
                        <p>{{ $accepted ? 'You have accepted the current document.' : 'Review the current document to continue support.' }}</p>
                    </div>
                    @if($accepted)
                        <form method="POST" action="{{ route('seeker.privacy.decision') }}" data-confirm="Withdraw this consent and close your current support request?">
                            @csrf
                            <input type="hidden" name="purpose" value="{{ $purpose }}">
                            <input type="hidden" name="decision" value="withdrawn">
                            <button class="page-button danger" type="submit">Withdraw consent</button>
                        </form>
                    @else
                        <button class="page-button light" type="button" data-open-seeker-consent>Review and accept</button>
                    @endif
                </div>
            @endforeach

            <h2 class="text-base font-semibold text-gray-800 mt-8 mb-4">Decision history</h2>

            @if($records->isNotEmpty())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Decision</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $record)
                                @php
                                    $given   = $record->consent_given && !$record->withdrawn;
                                    $label   = ucfirst($record->decision ?? ($record->consent_given ? 'accepted' : 'declined'));
                                    $dateText = $record->consent_date?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A');
                                @endphp
                                <tr>
                                    <td class="font-medium text-gray-800">{{ ucwords(str_replace('_', ' ', $record->purpose ?? $record->document_type)) }}</td>
                                    <td><span class="status-badge {{ $given ? '' : 'muted' }}">{{ $label }}</span></td>
                                    <td class="text-gray-600">{{ $dateText }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-10">
                    <p class="text-gray-500 text-sm">No decisions recorded yet.</p>
                </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Your privacy matters to us.
        </div>

    </main>

    @include('layouts.partials.pwa-banner')

</body>
</html>