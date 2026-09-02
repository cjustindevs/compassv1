@extends('layouts.helper')

@section('title', 'Complete Your Profile')

@section('heading', 'Welcome to COMPASS')
@section('subheading', 'Complete your helper profile to start taking sessions')

@section('styles')
    <style>
        .onboarding-card {
            max-width: 640px;
            margin: 0 auto;
        }
        .onboarding-card .intro {
            text-align: center;
            margin-bottom: 24px;
        }
        .onboarding-card .intro .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green-50);
            color: var(--green-700);
            font-size: 13px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            margin-bottom: 14px;
        }
        .onboarding-card .intro h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 6px;
        }
        .onboarding-card .intro p {
            font-size: 14px;
            color: var(--gray-500);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 560px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .helper-tips {
            background: var(--blue-50);
            border: 1px solid #BFDBFE;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 13px;
            color: var(--blue-600);
            margin-top: 8px;
        }
        .helper-tips ul {
            margin: 6px 0 0;
            padding-left: 18px;
        }
    </style>
@endsection

@section('content')
    <div class="card onboarding-card">
        <div class="intro">
            <span class="badge"><i class="fas fa-hands-helping"></i> Helper Onboarding</span>
            <h2>Complete your helper profile</h2>
            <p>We just need a few details before you can take on support sessions.</p>
        </div>

        <form method="POST" action="{{ route('helper.onboarding.store') }}">
            @csrf

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                           value="{{ old('first_name', $firstName) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                           value="{{ old('last_name', $lastName) }}" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Contact email</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="{{ old('email', $email) }}" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="phone">Phone <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="{{ old('phone') }}" placeholder="+63 ...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="preferred_language">Preferred language</label>
                    <input type="text" id="preferred_language" name="preferred_language" class="form-control"
                           value="{{ old('preferred_language', 'English') }}" placeholder="English">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="specializations">Specializations <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
                <input type="text" id="specializations" name="specializations" class="form-control"
                       value="{{ old('specializations') }}" placeholder="e.g. Anxiety, Grief, Relationships">
            </div>

            <div class="form-group">
                <label class="form-label" for="bio">Short bio <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
                <textarea id="bio" name="bio" class="form-control" placeholder="A sentence or two about your background as a peer supporter...">{{ old('bio') }}</textarea>
            </div>

            <div class="helper-tips">
                <strong>What happens next?</strong>
                <ul>
                    <li>You'll complete a quick readiness check before each session.</li>
                    <li>Cases are assigned to you by a moderator or adviser.</li>
                    <li>Your profile is visible only to the COMPASS care team.</li>
                </ul>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:20px;">
                <i class="fas fa-check-circle"></i> Create my profile
            </button>
        </form>
    </div>
@endsection
