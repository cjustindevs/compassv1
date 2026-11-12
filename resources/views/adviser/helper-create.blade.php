@extends('layouts.app')
@section('title', 'Add a Helper - COMPASS')

@push('styles')
<style>
    .hc-form .ui-card { margin-bottom: 0; }
    .hc-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (min-width: 1024px) { .hc-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); } .hc-after { grid-column: 1 / -1; } }
    .hc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 480px) { .hc-row { grid-template-columns: 1fr; } }
    .hc-field { display: grid; gap: 4px; font-size: 14px; font-weight: 600; color: var(--compass-text, #1F2937); margin-bottom: 12px; }
    .hc-field input:not([type=checkbox]), .hc-field textarea { border: 1px solid var(--compass-border, #E5E7EB); border-radius: 12px; padding: 10px 12px; font: inherit; font-size: 15px; font-weight: 400; width: 100%; background: #fff; }
    .hc-field input:not([type=checkbox]) { min-height: 44px; }
    .hc-field small, .hc-note { font-size: 13px; font-weight: 400; color: var(--compass-muted, #6B7280); margin: 0; }
    .hc-error { font-size: 13px; font-weight: 500; color: #B91C1C; }
    .hc-field.has-error input, .hc-field.has-error textarea { border-color: #DC2626; }
    .hc-check { display: grid; grid-template-columns: auto 1fr; align-items: start; gap: 10px; padding: 12px; border: 1px solid var(--compass-border, #E5E7EB); border-radius: 12px; margin-bottom: 8px; font-size: 14px; }
    .hc-check input { margin-top: 3px; width: 18px; height: 18px; accent-color: #078749; }
    .hc-check strong { display: block; color: var(--compass-text, #1F2937); }
    .hc-after ol { margin: 0 0 8px; padding-left: 20px; display: grid; gap: 6px; font-size: 14px; color: var(--compass-muted, #6B7280); }
    .hc-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
</style>
@endpush

@section('content')
    <x-ui.page-header title="Add a Helper"
        description="Create an account for a student who will be supervised by you. Record the proof that makes them eligible before the account is created."
        :back-href="route('adviser.helpers')" back-label="Back to Manage Helpers" />

    <form method="POST" action="{{ route('adviser.helpers.store') }}" class="hc-form">
        @csrf
        <div class="hc-grid">
            <x-ui.card title="Account details" id="hcAccount">
                <div class="hc-row">
                    <label class="hc-field {{ $errors->has('first_name') ? 'has-error' : '' }}" for="hcFirst">First name<input id="hcFirst" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="off">@error('first_name')<span class="hc-error" role="alert">{{ $message }}</span>@enderror</label>
                    <label class="hc-field {{ $errors->has('last_name') ? 'has-error' : '' }}" for="hcLast">Last name<input id="hcLast" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="off">@error('last_name')<span class="hc-error" role="alert">{{ $message }}</span>@enderror</label>
                </div>
                <label class="hc-field {{ $errors->has('email') ? 'has-error' : '' }}" for="hcEmail">School email<input id="hcEmail" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off"><small>The Helper receives a link here to set their own password.</small>@error('email')<span class="hc-error" role="alert">{{ $message }}</span>@enderror</label>
                <p class="hc-note">Supervising Adviser: <strong>{{ $adviser->full_name }}</strong> (you)</p>
            </x-ui.card>

            <x-ui.card title="Qualification evidence" subtitle="Proof of eligibility: all three must be true. This is the same verification you would record on an existing Helper." id="hcProof">
                <label class="hc-check"><input type="checkbox" name="currently_enrolled" value="1" @checked(old('currently_enrolled')) required> <span><strong>Currently enrolled</strong> I checked the student's current enrollment record.</span></label>
                @error('currently_enrolled')<span class="hc-error" role="alert">{{ $message }}</span>@enderror
                <label class="hc-check"><input type="checkbox" name="recognized_member" value="1" @checked(old('recognized_member')) required> <span><strong>Recognized peer-support member</strong> The student belongs to the recognized peer-support organization.</span></label>
                @error('recognized_member')<span class="hc-error" role="alert">{{ $message }}</span>@enderror
                <label class="hc-check"><input type="checkbox" name="training_completed" value="1" @checked(old('training_completed')) required> <span><strong>Training completed</strong> The student finished the required peer-support training.</span></label>
                @error('training_completed')<span class="hc-error" role="alert">{{ $message }}</span>@enderror
                <label class="hc-field {{ $errors->has('qualification_evidence') ? 'has-error' : '' }}" for="hcEvidence" style="margin-top:12px">What did you check?<textarea id="hcEvidence" name="qualification_evidence" rows="4" required minlength="10" maxlength="2000" placeholder="For example: Student ID 2023-01234 verified with the registrar on Nov 10; training certificate PS-2026-018.">{{ old('qualification_evidence') }}</textarea><small>Saved with the account as the verification record. Do not include sensitive personal details.</small>@error('qualification_evidence')<span class="hc-error" role="alert">{{ $message }}</span>@enderror</label>
            </x-ui.card>

            <x-ui.card title="After you create the account" id="hcAfter" class="hc-after">
                <ol>
                    <li>The Helper is <strong>verified</strong> and linked to you.</li>
                    <li>They get an <strong>email and notification</strong> to set their password.</li>
                    <li>To take sessions they still need a <strong>duty shift</strong> (set by a Moderator), a passed <strong>readiness check</strong>, and to set themselves <strong>Available</strong>.</li>
                </ol>
                <p class="hc-note">You will see their eligibility checklist on their Helper page.</p>
                <div class="hc-actions">
                    <a href="{{ route('adviser.helpers') }}" class="btn-outline">Cancel</a>
                    <button type="submit" class="btn-primary">Create Helper account</button>
                </div>
            </x-ui.card>
        </div>
    </form>
@endsection
