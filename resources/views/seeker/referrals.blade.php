<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Referrals and Appointments</title>

    <!-- Tailwind -->
    <!-- Google Fonts -->
    <style>
        *{font-family:Inter,sans-serif;box-sizing:border-box}body{margin:0;background:#f8fbf9;color:#163b2d}
        .main-content{margin-left:260px;padding:24px 32px 48px;min-height:100vh}.referrals-content{width:100%;max-width:none;margin:0}
        .referrals-intro{font-size:14px;line-height:1.6;color:#64748b;margin:0 0 18px;max-width:760px}
        .referral-card{background:#fff;border:1px solid #dfe8e2;border-radius:14px;padding:20px;margin-bottom:16px;min-width:0}
        .referral-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}.referral-heading h2{font-size:18px;font-weight:700;margin:0 0 4px}.referral-meta{font-size:12px;color:#64748b;margin:0}
        .status-badge{display:inline-flex;align-items:center;white-space:nowrap;font-size:12px;font-weight:600;border-radius:20px;background:#eaf8f0;color:#027039;padding:5px 10px}.status-badge.muted{background:#f1f5f3;color:#52645a}
        .referral-overview{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,1fr);gap:20px;align-items:start}.referral-overview>*{min-width:0}
        .referral-card h3{font-size:14px;font-weight:700;margin:0 0 8px}.referral-card p{font-size:14px;line-height:1.6;overflow-wrap:anywhere}.referral-summary{white-space:pre-wrap;margin:0 0 16px;color:#475569}
        .referral-facts{display:flex;gap:24px;flex-wrap:wrap;margin:0}.referral-facts dt{font-size:12px;color:#64748b;margin-bottom:4px}.referral-facts dd{margin:0;font-size:14px;font-weight:600}
        .referral-decision{padding:16px;border:1px solid #cce7d6;background:#f6fcf8;border-radius:10px}.referral-decision p{margin:0 0 10px}
        .referral-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.referral-actions form{margin:0}.page-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;min-height:42px;border:1px solid #049b50;border-radius:9px;background:#049b50;color:white;font-size:14px;font-weight:600;text-decoration:none;cursor:pointer}.page-button:hover{background:#037d41}.page-button.light{background:white;color:#08763f;border-color:#cce3d5}.page-button.danger{background:white;color:#b42332;border-color:#eabdc2}.page-button:focus-visible,summary:focus-visible{outline:2px solid #078749;outline-offset:3px}
        .referral-decision .rv-consent-terms__list{list-style:disc;padding-left:18px;margin:12px 0 0;font-size:13px;color:#475569;line-height:1.6}.referral-decision .rv-consent-terms__list li+li{margin-top:6px}
        .referral-details{border-top:1px solid #e6ede8;margin-top:18px;padding-top:14px}.referral-details summary{cursor:pointer;font-size:13px;font-weight:600;color:#08763f}.referral-details[open]>summary{margin-bottom:12px}.referral-details>section{margin:0;border:0;padding:0}.referral-details dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.referral-details dl>div{min-width:0;margin:0!important}.referral-details dt{font-size:12px;color:#64748b;margin-bottom:4px}.referral-details dd{margin:0;font-size:14px;line-height:1.6;overflow-wrap:anywhere}
        .referral-coordination{background:#f5f9f7;border-radius:10px;padding:14px;margin:14px 0}.referral-coordination p{margin:0}.referral-empty{text-align:center;padding:36px 16px}.referral-empty h2{font-size:18px}.referral-flash{padding:12px 16px;border:1px solid #cce7d6;background:#eaf8f0;border-radius:10px;margin-bottom:16px;font-size:14px}
        .hamburger{display:none;background:none;border:0;font-size:24px;color:#374151;cursor:pointer;padding:4px}.sidebar-overlay{display:none;position:fixed;inset:0;background:#0004;z-index:99}.sidebar-overlay.active{display:block}
        @media(min-width:769px){.sidebar-overlay{display:none!important}}@media(max-width:1100px){.referral-overview{grid-template-columns:1fr}.main-content{padding-left:24px;padding-right:24px}}@media(max-width:768px){.main-content{margin-left:0;padding:16px 16px 40px}.hamburger{display:block}.referral-card{padding:16px}.referral-details dl{grid-template-columns:1fr}.referral-actions>*{flex:1 1 auto}.referral-actions form .page-button{width:100%}.referral-heading{flex-wrap:wrap}}
    </style>
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['seeker.referrals'],
        'role'   => 'Help Seeker',
    ])

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="referrals-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar">
                    <x-ui-icon name="menu"  />
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Referrals and Appointments</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Review recommendations for professional support.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now('Asia/Manila')->format('M d, Y') }}</span>
            </div>
        </div>

            <p class="referrals-intro">Review your approved recommendations here. You choose whether to proceed. Contact details and identity disclosure are a separate step.</p>
            @if(session('success'))<div role="status" class="referral-flash">{{ session('success') }}</div>@endif
            @if($errors->any())<div role="alert" class="referral-flash">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

            @forelse($referrals as $referral)
                @php
                    $statusLabel = ucwords(str_replace('_', ' ', $referral->status));
                    $canDecide = $referral->approved_at && $referral->status === \App\Models\Referral::STATUS_PENDING_CONSENT;
                    $recommendationSummary = $referral->referral_reason ?: ($referral->recommendation_form['session_summary'] ?? null);
                @endphp
                <article class="referral-card" aria-labelledby="referral-title-{{ $referral->id }}">
                    <header class="referral-heading">
                        <div><h2 id="referral-title-{{ $referral->id }}">Referral #{{ $referral->id }}</h2><p class="referral-meta">{{ $referral->referral_date?->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</p></div>
                        <span class="status-badge {{ $canDecide ? '' : 'muted' }}">{{ $canDecide ? 'Your decision needed' : $statusLabel }}</span>
                    </header>
                    <div class="{{ $canDecide ? 'referral-overview' : '' }}">
                        <section aria-label="Recommendation overview">
                            <h3>Why this was recommended</h3>
                            <p class="referral-summary">{{ $recommendationSummary ?: 'Professional support has been recommended. Review the recommendation details below.' }}</p>
                            <dl class="referral-facts"><div><dt>Priority</dt><dd>{{ ucfirst($referral->priority_level) }}</dd></div><div><dt>Adviser review</dt><dd>{{ $referral->approved_at ? 'Approved' : 'Awaiting review' }}</dd></div></dl>
                        </section>
                        @if($canDecide)
                            <section class="referral-decision" aria-labelledby="referral-decision-{{ $referral->id }}">
                                <h3 id="referral-decision-{{ $referral->id }}">Would you like to proceed?</h3>
                                <p>Your Adviser approved this recommendation. Agreeing allows the assigned Professional to review authorized referral records.</p>
                                @include('partials.referral-consent-terms')
                                <div class="referral-actions">
                                    <form method="POST" action="{{ route('referrals.consent', $referral) }}" data-referral-decision="agree" data-confirm="Agree to this referral?" data-confirm-message="Your decision will be recorded. Contact details and identity disclosure remain a separate step." data-confirm-text="Agree to referral" data-confirm-class="bg-green-700 hover:bg-green-800">
                                        @csrf<input type="hidden" name="consent_given" value="1"><button class="page-button" type="submit">Agree to referral</button>
                                    </form>
                                    <form method="POST" action="{{ route('referrals.consent', $referral) }}" data-referral-decision="decline" data-confirm="Decline this referral?" data-confirm-message="This records your decision not to proceed. You can continue using self-help tools. An open safety review is handled separately." data-confirm-text="Decline referral">
                                        @csrf<input type="hidden" name="consent_given" value="0"><button class="page-button danger" type="submit">Decline referral</button>
                                    </form>
                                </div>
                            </section>
                        @endif
                    </div>
                    @if(!$canDecide)
                        @if($referral->approved_at && $referral->help_seeker_consent)
                            <div class="referral-coordination">
                                @if($referral->professional && app(\App\Services\ConsentService::class)->valid($referral->session->seeker, 'identity_disclosure', $referral->id))
                                    <p><strong>Your assigned professional: {{ $referral->professional->full_name }}</strong></p>
                                @elseif($referral->professional)
                                    <p>Complete identity-disclosure consent to view your professional coordination details.</p>
                                @else
                                    <p><strong>Professional assignment pending.</strong> After your identity details are saved, your Adviser assigns a Professional for review.</p>
                                @endif
                            </div>
                        @endif
                        @if($referral->canProvideIdentity())
                            <p>You agreed to this referral. Contact details are optional and collected separately for coordination.</p>
                            <div class="referral-actions">
                                <button type="button" class="page-button light" onclick="document.getElementById('identity-dialog-{{ $referral->id }}').showModal()">Provide contact details for coordination</button>
                                <form method="POST" action="{{ route('seeker.privacy.decision') }}" data-confirm="Withdraw referral consent and stop future professional access?">
                                    @csrf<input type="hidden" name="purpose" value="referral"><input type="hidden" name="decision" value="withdrawn"><input type="hidden" name="referral_id" value="{{ $referral->id }}"><button class="page-button danger" type="submit">Withdraw referral consent</button>
                                </form>
                            </div>
                            @include('partials.referral-identity-modal')
                        @elseif($referral->help_seeker_consent)
                            <p>You agreed to this referral. It is no longer an open case, so no further action is available here.</p>
                        @elseif($referral->status === \App\Models\Referral::STATUS_CONSENT_REQUESTED)
                            <p>Your Helper recommended a referral. Open the consent request in your live chat to review the next step.</p>
                        @elseif(in_array($referral->status, ['closed','declined','completed'], true))
                            <p>{{ $referral->consent_declined_at ? 'You declined this referral. Your decision was recorded.' : 'This referral is '.strtolower($statusLabel).'.' }}</p>
                            <a href="{{ url('/selfhelp') }}" class="page-button light">Open self-help tools</a>
                        @else
                            <p>This referral is awaiting your Adviser's review. There is nothing to do right now.</p>
                        @endif
                    @endif
                    @if($referral->recommendation_form)
                        <details class="referral-details"><summary>View full recommendation</summary>@include('partials.referral-recommendation')</details>
                    @endif
                    @include('partials.referral-appointments')
                </article>
            @empty
                <div class="referral-card referral-empty"><h2>No referral decisions yet</h2><p>Recommendations will appear here when they are ready for review.</p></div>
            @endforelse

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">

            Every decision you make is respected.
        </div>

        </div>
    </main>

    @include('partials.workflow-notice', ['inlineNotices'=>['success'], 'inlineErrors'=>true])
    @include('layouts.partials.pwa-banner')

</body>
</html>
