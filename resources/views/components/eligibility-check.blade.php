@props(['helper', 'audience' => 'helper'])
{{--
    "Why can't I take cases?" (QA: validation / verify record so it's clear why a Helper is not eligible).
    Uses HelperEligibilityService::reasons() as the single source of truth; this only lays it out
    as a pass/fail checklist with a next step for each failing rule.
--}}
@php
    $failing = app(\App\Services\HelperEligibilityService::class)->reasons($helper);
    $self = $audience === 'helper';
    $rules = [
        ['Account active', 'Account is inactive.', $self ? 'Contact your Adviser or the administrator.' : 'Re-enable the account in User management.'],
        ['Verified and training approved', 'Institutional verification and training approval are required.', $self ? 'Your Adviser verifies your student record and training.' : 'Verify the institutional record and approve training.'],
        ['Adviser assigned', 'An active primary adviser is required.', $self ? 'A Moderator assigns your Adviser.' : 'Assign an active primary Adviser.'],
        ['Not under review', 'Session assignments are restricted pending adviser review.', $self ? 'Your Adviser is reviewing your assignments.' : 'Complete the assignment review.'],
        ['Service hours open', 'Service is closed for new assignments.', 'New sessions start again during service hours.'],
        ['On a duty shift', 'You are not on an official duty shift.', $self ? 'Duty shifts are set by your Moderator. See Calendar.' : 'Schedule a duty shift.'],
        ['Readiness check passed', 'A current passed readiness check is required.', $self ? 'Take the readiness check.' : 'The Helper must pass a readiness check.'],
        ['Availability set to Available', 'Availability is not set to Available.', $self ? 'Turn on availability from your dashboard.' : 'The Helper must set themselves Available.'],
        ['Capacity free', 'Another assignment or active session already occupies your capacity.', 'Finish or end the current session first.'],
        ['Under the shift session limit', 'The two-session duty-shift limit has been reached.', 'The limit resets on the next duty shift.'],
    ];
    $passCount = collect($rules)->reject(fn ($r) => in_array($r[1], $failing, true))->count();
    $eligible = $failing === [];
@endphp
<section {{ $attributes->merge(['class' => 'eligibility-check']) }} aria-labelledby="eligibilityTitle">
    <header>
        <h3 id="eligibilityTitle">{{ $self ? 'Can I take sessions?' : 'Eligibility check' }}</h3>
        <span class="ec-badge {{ $eligible ? 'is-ok' : 'is-blocked' }}">{{ $eligible ? 'Eligible for new sessions' : 'Not eligible yet' }} · {{ $passCount }}/{{ count($rules) }}</span>
    </header>
    <ul class="ec-list">
        @foreach($rules as [$label, $reason, $hint])
            @php($ok = ! in_array($reason, $failing, true))
            <li class="{{ $ok ? 'is-ok' : 'is-blocked' }}">
                <span class="ec-mark" aria-hidden="true"><svg viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round">@if($ok)<path d="M2.5 6.2 5 8.5 9.5 3.5"/>@else<path d="M3 3l6 6M9 3l-6 6"/>@endif</svg></span>
                <span class="ec-text"><strong>{{ $label }}</strong><span class="sr-only">{{ $ok ? ': passed' : ': not met' }}</span>
                    @unless($ok)<small>{{ $reason }} {{ $hint }}@if($self && $label === 'Readiness check passed') <a href="{{ route('helper.readiness') }}">Open readiness check</a>@endif</small>@endunless
                </span>
            </li>
        @endforeach
    </ul>
</section>
@once
<style>
.eligibility-check { background: #fff; border: 1px solid #dfe9e3; border-radius: 14px; padding: 14px 16px; display: grid; gap: 10px; margin: 14px 0; }
.eligibility-check header { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; }
.eligibility-check h3 { margin: 0; font-size: 14px; font-weight: 700; color: #163b2d; }
.ec-badge { font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
.ec-badge.is-ok { background: #e7f6ee; color: #05683a; }
.ec-badge.is-blocked { background: #fff4e0; color: #8a5300; }
.ec-list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 6px 18px; }
.ec-list li { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; padding: 4px 0; }
.ec-mark { flex: none; width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; font-size: 11px; font-weight: 800; color: #fff; margin-top: 1px; }
.is-ok > .ec-mark { background: #0ca30c; }
.is-blocked > .ec-mark { background: #d03b3b; }
.ec-text strong { color: #163b2d; font-weight: 600; }
.ec-text small { display: block; color: #8a3b30; font-size: 12px; line-height: 1.45; margin-top: 2px; }
.ec-text small a { color: #05683a; font-weight: 600; }
</style>
@endonce
