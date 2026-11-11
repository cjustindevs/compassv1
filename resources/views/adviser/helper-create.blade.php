@extends('layouts.app')
@section('title', 'Add a Helper - COMPASS')
@section('content')
<div class="adviser-page-content hc-page">
    <a class="hc-back" href="{{ route('adviser.helpers') }}">&larr; Back to Manage Helpers</a>
    <div class="hc-head">
        <h1>Add a Helper</h1>
        <p>Create an account for a student who will be supervised by you. Record the proof that makes them eligible before the account is created.</p>
    </div>

    @if($errors->any())
        <div class="hc-errors" role="alert"><strong>Please fix the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('adviser.helpers.store') }}" class="hc-grid">
        @csrf
        <section class="hc-card" aria-labelledby="hcAccount">
            <h2 id="hcAccount"><span class="hc-num">1</span> Account details</h2>
            <div class="hc-row">
                <label for="hcFirst">First name<input id="hcFirst" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="off"></label>
                <label for="hcLast">Last name<input id="hcLast" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="off"></label>
            </div>
            <label for="hcEmail">School email<input id="hcEmail" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off"><small>The Helper receives a link here to set their own password.</small></label>
            <p class="hc-note">Supervising Adviser: <strong>{{ $adviser->full_name }}</strong> (you)</p>
        </section>

        <section class="hc-card" aria-labelledby="hcProof">
            <h2 id="hcProof"><span class="hc-num">2</span> Proof of eligibility</h2>
            <p class="hc-sub">All three must be true. This is the same verification you would record on an existing Helper.</p>
            <label class="hc-check"><input type="checkbox" name="currently_enrolled" value="1" @checked(old('currently_enrolled')) required> <span><strong>Currently enrolled</strong> I checked the student's current enrollment record.</span></label>
            <label class="hc-check"><input type="checkbox" name="recognized_member" value="1" @checked(old('recognized_member')) required> <span><strong>Recognized peer-support member</strong> The student belongs to the recognized peer-support organization.</span></label>
            <label class="hc-check"><input type="checkbox" name="training_completed" value="1" @checked(old('training_completed')) required> <span><strong>Training completed</strong> The student finished the required peer-support training.</span></label>
            <label for="hcEvidence">What did you check?<textarea id="hcEvidence" name="qualification_evidence" rows="4" required minlength="10" maxlength="2000" placeholder="For example: Student ID 2023-01234 verified with the registrar on Nov 10; training certificate PS-2026-018.">{{ old('qualification_evidence') }}</textarea><small>Saved with the account as the verification record. Do not include sensitive personal details.</small></label>
        </section>

        <aside class="hc-card hc-after" aria-labelledby="hcAfter">
            <h2 id="hcAfter">After you create the account</h2>
            <ol>
                <li>The Helper is <strong>verified</strong> and linked to you.</li>
                <li>They get an <strong>email and notification</strong> to set their password.</li>
                <li>To take sessions they still need a <strong>duty shift</strong> (set by a Moderator), a passed <strong>readiness check</strong>, and to set themselves <strong>Available</strong>.</li>
            </ol>
            <p>You will see their eligibility checklist on their Helper page.</p>
            <button type="submit" class="hc-submit">Create Helper account</button>
        </aside>
    </form>
</div>
<style>
.hc-page { padding: 20px 28px 40px; display: grid; gap: 14px; }
.hc-back { font-size: 13px; font-weight: 600; color: #047a3e; text-decoration: none; }
.hc-head h1 { margin: 0; font-size: 24px; font-weight: 800; color: #163b2d; }
.hc-head p { margin: 4px 0 0; font-size: 14px; color: #64746b; max-width: 70ch; }
.hc-errors { background: #fdecec; border: 1px solid #f5c2c2; color: #8a1f1f; border-radius: 12px; padding: 12px 16px; font-size: 13px; }
.hc-errors ul { margin: 6px 0 0 18px; }
.hc-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr) minmax(260px, .8fr); gap: 14px; align-items: start; }
.hc-card { background: #fff; border: 1px solid #dfe9e3; border-radius: 14px; padding: 16px 18px; display: grid; gap: 12px; }
.hc-card h2 { margin: 0; font-size: 14px; font-weight: 700; color: #163b2d; display: flex; gap: 8px; align-items: center; }
.hc-num { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; background: #eaf8f0; color: #027039; font-size: 12px; font-weight: 800; }
.hc-sub, .hc-note { margin: 0; font-size: 13px; color: #64746b; }
.hc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.hc-card label { display: grid; gap: 4px; font-size: 13px; font-weight: 600; color: #26443a; }
.hc-card input:not([type=checkbox]), .hc-card textarea { border: 1px solid #cbd5cf; border-radius: 10px; padding: 9px 11px; font: inherit; font-weight: 400; width: 100%; }
.hc-card small { font-weight: 400; font-size: 12px; color: #6b7b74; }
.hc-check { grid-template-columns: auto 1fr !important; align-items: start; gap: 10px !important; padding: 10px 12px; border: 1px solid #e2e8e4; border-radius: 10px; font-weight: 400 !important; }
.hc-check input { margin-top: 3px; width: 16px; height: 16px; accent-color: #078749; }
.hc-check strong { display: block; color: #163b2d; }
.hc-after ol { margin: 0; padding-left: 18px; display: grid; gap: 6px; font-size: 13px; color: #40564b; }
.hc-after p { margin: 0; font-size: 13px; color: #64746b; }
.hc-submit { min-height: 44px; border: 0; border-radius: 10px; background: #078749; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
.hc-submit:hover { background: #05683a; }
@media (max-width: 1150px) { .hc-grid { grid-template-columns: 1fr 1fr; } .hc-after { grid-column: 1 / -1; } }
@media (max-width: 700px) { .hc-page { padding: 16px; } .hc-grid, .hc-row { grid-template-columns: 1fr; } }
</style>
@endsection
