@extends('layouts.app')

@section('title', 'Helper Detail – COMPASS')

@push('styles')
<style>
    .helper-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
    .hd-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px 24px; margin: 0 0 16px; }
    .hd-facts dt { font-size: 13px; color: var(--compass-muted, #6B7280); }
    .hd-facts dd { margin: 2px 0 0; font-size: 15px; font-weight: 600; color: var(--compass-text, #1F2937); }
    .hd-note { font-size: 14px; color: var(--compass-muted, #6B7280); margin: 0 0 12px; }
    .hd-warn { font-size: 14px; color: #92400E; margin: 0 0 12px; }
    .hd-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px; background: #F9FAFB; border-radius: 12px; margin-bottom: 8px; }
    .hd-row p { margin: 0; }
    .hd-sub { font-size: 14px; color: var(--compass-muted, #6B7280); }
    .hd-hist { border: 1px solid var(--compass-border, #E5E7EB); border-radius: 12px; padding: 12px; margin-bottom: 8px; font-size: 14px; }
    .hd-hist p { margin: 0; }
    .hd-stack > * + * { margin-top: 12px; }
    .hd-details { border: 1px solid var(--compass-border, #E5E7EB); border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }
    .hd-details > summary { cursor: pointer; font-weight: 600; font-size: 15px; }
    .hd-details table { width: 100%; font-size: 14px; margin-top: 8px; }
    .hd-details th { text-align: left; }
    .hd-error { font-size: 14px; color: #B91C1C; margin: 4px 0; }
    .hd-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
    .hd-checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .hd-field { display: block; font-size: 14px; font-weight: 600; margin-bottom: 12px; }
    .hd-field select, .hd-field textarea { display: block; width: 100%; margin-top: 4px; border: 1px solid var(--compass-border, #E5E7EB); border-radius: 12px; padding: 10px 12px; font-size: 15px; font-weight: 400; background: #fff; }
    .hd-field select { min-height: 44px; }
</style>
@endpush

@section('content')
    @php
        $latest = $competencyHistory->first();
        $levelName = $latest->competency_level ?? 'Beginner';
        $levelTone = ['expert' => 'green', 'advanced' => 'blue', 'intermediate' => 'amber', 'beginner' => 'red', 'trainee' => 'neutral'];
        $readiness = $helper->getCurrentReadiness()?->assessment_result === 'ready' ? 'Ready' : 'Readiness required';
        $availability = ucfirst((string) ($helper->availability ?? 'unavailable'));
        $verificationVerified = ($helper->verification_status ?? 'pending') === 'verified';
        $adviserName = $helper->adviser ? $helper->adviser->first_name.' '.$helper->adviser->last_name : null;
    @endphp

    <x-ui.page-header :title="$helper->first_name.' '.$helper->last_name"
        :description="$adviserName ? 'Supervised by '.$adviserName : 'No adviser assigned'"
        :back-href="route('adviser.helpers')" back-label="Back to Manage Helpers" />

    @if(session('success'))
        <div class="helper-flash">{{ session('success') }}</div>
    @endif

    <x-ui.card title="Summary">
        <x-slot:actions>
            <x-ui.badge :tone="$levelTone[strtolower($levelName)] ?? 'neutral'">{{ $levelName }}</x-ui.badge>
        </x-slot:actions>
        <dl class="hd-facts">
            <div><dt>Readiness</dt><dd>{{ $readiness }}</dd></div>
            <div><dt>Availability</dt><dd>{{ $availability }}</dd></div>
            <div><dt>Level</dt><dd>{{ $levelName }} · Score {{ $latest->overall_score ?? 'N/A' }}</dd></div>
            <div><dt>Assigned adviser</dt><dd>{{ $adviserName ?? 'None' }}</dd></div>
            <div><dt>Active sessions</dt><dd>{{ $helper->sessions->count() }}</dd></div>
            <div><dt>Evaluations</dt><dd>{{ $competencyHistory->count() }}</dd></div>
        </dl>
        <div class="hd-stack">
            <div><x-helper-capacity :helper="$helper" /></div>
            <x-eligibility-check :helper="$helper" audience="adviser" />
            <x-rating-explainer :helper="$helper" audience="adviser" />
        </div>
    </x-ui.card>

    @if($helper->is_under_review)
        <x-ui.card aria-labelledby="helper-review-title">
            <h2 id="helper-review-title" class="ui-card-title" style="margin-bottom:8px">Assignment review required</h2>
            <p class="hd-note">Automatic matching is paused for this Helper. Review the missed offers and agree on next steps before clearing the restriction.</p>
            <p class="hd-warn">{{ $helper->review_reason }}</p>
            <form method="POST" action="{{ route('adviser.helper.review.complete', $helper->id) }}">
                @csrf
                <label class="form-label" for="review_resolution">Review outcome and next steps</label>
                <textarea class="form-control" id="review_resolution" name="review_resolution" rows="3" required minlength="10" maxlength="2000">{{ old('review_resolution') }}</textarea>
                @error('review_resolution')<p class="hd-error">{{ $message }}</p>@enderror
                <p class="hd-note" style="margin-top:8px">This clears the review restriction only. Verification, readiness, duty and capacity checks still apply.</p>
                <button type="submit" class="btn-primary">Complete Adviser review</button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Institutional eligibility and training">
        <x-slot:actions>
            <x-ui.badge :tone="$verificationVerified ? 'green' : 'amber'">{{ $verificationVerified ? 'Verified' : 'Pending verification' }}</x-ui.badge>
        </x-slot:actions>
        <p class="hd-note">
            Verify current DWCC psychology enrollment, recognized membership and completed orientation or training before this helper can receive assignments.
        </p>

        @if($verificationVerified)
            <dl class="hd-facts">
                <div><dt>Status</dt><dd>Verified</dd></div>
                <div><dt>Last verified</dt><dd>{{ $helper->verified_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? '—' }}</dd></div>
            </dl>
            @if($helper->qualification_evidence)
                <p class="hd-note"><strong>Evidence on file:</strong> {{ $helper->qualification_evidence }}</p>
            @endif
        @else
            <p class="hd-warn" role="status">This helper cannot receive assignments until an adviser records eligibility and training verification.</p>
        @endif

        @if($errors->any())
            <p role="alert" class="hd-error">{{ $errors->first() }}</p>
        @endif

        <details class="hd-details" {{ $verificationVerified ? '' : 'open' }}>
            <summary>
                <x-ui-icon name="edit" class="mr-1" />{{ $verificationVerified ? 'Update verification' : 'Record verification' }}
            </summary>
            <form method="POST" action="{{ route('adviser.helper.verify', $helper->id) }}" class="form-container" style="margin-top:16px">
                @csrf
                <div class="hd-checks">
                    <label class="checkbox-group requirement-card">
                        <input type="checkbox" name="currently_enrolled" value="1" required>
                        <span class="requirement-icon"><x-ui-icon name="graduation"  /></span>
                        <span>
                            <strong class="block text-sm font-semibold text-gray-800">Current DWCC psychology enrollment</strong>
                            <span class="hd-sub">Enrollment confirmed for this term</span>
                        </span>
                    </label>
                    <label class="checkbox-group requirement-card">
                        <input type="checkbox" name="recognized_member" value="1" required>
                        <span class="requirement-icon"><x-ui-icon name="badge"  /></span>
                        <span>
                            <strong class="block text-sm font-semibold text-gray-800">Recognized membership</strong>
                            <span class="hd-sub">Member of a recognized organization</span>
                        </span>
                    </label>
                    <label class="checkbox-group requirement-card">
                        <input type="checkbox" name="training_completed" value="1" required>
                        <span class="requirement-icon"><x-ui-icon name="clipboard"  /></span>
                        <span>
                            <strong class="block text-sm font-semibold text-gray-800">Training and orientation</strong>
                            <span class="hd-sub">Completed required orientation or training</span>
                        </span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="qualification_evidence">Verification evidence or institutional reference</label>
                    <textarea id="qualification_evidence" name="qualification_evidence" class="form-control" maxlength="2000" required>{{ old('qualification_evidence', $helper->qualification_evidence) }}</textarea>
                </div>
                <div class="hd-actions">
                    <button class="btn btn-primary" type="submit">
                        <x-ui-icon name="user-check"  /> {{ $verificationVerified ? 'Update verification' : 'Record verification' }}
                    </button>
                </div>
            </form>
        </details>
    </x-ui.card>

    <x-ui.card title="Supervision">
        <p class="hd-note">Assigned adviser: <strong>{{ $adviserName }}</strong>. Active referrals follow the helper when supervision is transferred.</p>

        <details class="hd-details">
            <summary>Transfer supervision</summary>
            <p class="hd-note" style="margin-top:12px">This transfers the helper and their active referrals to another adviser. Completed referral records stay with their original reviewer.</p>
            @if($errors->any())<p role="alert" class="hd-error">{{ $errors->first() }}</p>@endif
            <form method="POST" action="{{ route('adviser.helpers.reassign') }}">
                @csrf
                <input type="hidden" name="helper_ids[]" value="{{ $helper->id }}">
                <label class="hd-field">Receiving adviser
                    <select name="adviser_id" required>
                        <option value="">Select adviser</option>
                        @foreach($transferAdvisers as $adviser)<option value="{{ $adviser->id }}">{{ $adviser->full_name }}</option>@endforeach
                    </select>
                </label>
                <label class="hd-field">Reason
                    <textarea name="reason" required maxlength="1000" rows="2">{{ old('reason') }}</textarea>
                </label>
                <div class="hd-actions"><button type="submit" class="btn-primary">Transfer supervision</button></div>
            </form>
        </details>

        <h3 class="ui-card-title" style="font-size:16px;margin-bottom:8px">Supervision history</h3>
        @forelse($assignmentHistory as $assignment)
            <div class="hd-hist">
                <p><strong>Adviser #{{ $assignment->adviser_id }} | {{ $assignment->ended_at ? 'Previous' : 'Current' }}</strong></p>
                <p class="hd-sub">{{ $assignment->started_at ?? 'Original start date unknown' }} | {{ $assignment->ended_at ?? 'Present' }}</p>
                <p>{{ $assignment->reason }}</p>
            </div>
        @empty
            <p class="hd-note">Current supervision is recorded on the Helper profile; historical assignment dates are unavailable.</p>
        @endforelse
    </x-ui.card>

    <x-ui.card title="Competency History">
        @if($competencyHistory->isNotEmpty())
            @foreach($competencyHistory as $evaluation)
                @php $evalLevel = $evaluation->competency_level ?? 'Beginner'; @endphp
                <div class="hd-row">
                    <div>
                        <p><strong>{{ $evaluation->evaluation_period ?? $evaluation->created_at->format('M Y') }}</strong></p>
                        <p class="hd-sub">Adviser: {{ $evaluation->adviser->first_name ?? 'Unknown' }}</p>
                    </div>
                    <div style="text-align:right">
                        <p><strong>{{ $evaluation->overall_score === null || $evaluation->normalized_score < 1 || $evaluation->normalized_score > 5 ? 'Not evaluated' : number_format($evaluation->normalized_score, 2) }}</strong></p>
                        <x-ui.badge :tone="$levelTone[strtolower($evalLevel)] ?? 'neutral'">{{ $evalLevel }}</x-ui.badge>
                    </div>
                </div>
                <details class="hd-details"><summary>Evaluation criteria and weights</summary><table><thead><tr><th>Criterion</th><th>Weight</th><th>Recorded rating</th></tr></thead><tbody>
                    @foreach(\App\Services\CompetencyRubric::CRITERIA as [$criterionLabel, $weight, $field])
                    <tr><td>{{ $criterionLabel }}</td><td>{{ $weight }}%</td><td>{{ $evaluation->$field === null ? 'Not recorded' : ($evaluation->$field > 5 ? $evaluation->$field.' (legacy scale)' : $evaluation->$field) }}</td></tr>
                    @endforeach
                </tbody></table><p class="hd-sub">Evaluated {{ ($evaluation->evaluation_date ?? $evaluation->created_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT. Legacy criterion scales are shown as recorded.</p></details>
            @endforeach
        @else
            <p class="hd-note">No competency evaluations recorded yet.</p>
        @endif
    </x-ui.card>
@endsection
