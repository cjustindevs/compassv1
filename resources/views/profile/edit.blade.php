<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Edit Profile</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0; --green-100: #DCF5E0; --green-200: #A8E0B0; --green-300: #6DCB80;
            --green-400: #30B650; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
            --green-800: #01562B; --green-900: #003D1E;
            --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB; --gray-300: #D1D5DB;
            --gray-400: #9CA3AF; --gray-500: #6B7280; --gray-600: #4B5563; --gray-700: #374151;
            --gray-800: #163B2D; --gray-900: #111827;
        }

        body { background: #F8FBF9; font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .card {
            background: white; border: 1px solid var(--gray-200); border-radius: 24px; padding: 28px;
            margin-bottom: 20px;
        }
        .card h2 { font-weight: 800; font-size: 17px; color: var(--gray-800); }
        .card .sub { font-size: 13px; color: var(--gray-500); margin-top: 2px; }

        .form-label { display: block; font-weight: 600; font-size: 13px; color: var(--gray-600); margin-bottom: 6px; }
        .form-input {
            width: 100%; border: 1px solid var(--gray-200); border-radius: 12px;
            padding: 11px 14px; font-size: 14px; color: var(--gray-800); outline: none;
            transition: all 0.2s ease; background: white;
        }
        .form-input:focus { border-color: var(--green-500); box-shadow: 0 0 0 4px rgba(4,160,82,0.08); }
        .form-error { font-size: 12px; color: #DC2626; margin-top: 4px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            font-weight: 600; font-size: 14px; padding: 11px 22px; border-radius: 12px;
            border: none; cursor: pointer; transition: all 0.25s ease; text-decoration: none;
        }
        .btn-primary { background: linear-gradient(135deg, var(--green-500), var(--green-600)); color: white; box-shadow: 0 4px 16px rgba(4,160,82,0.25); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(4,160,82,0.35); }
        .btn-danger { background: #DC2626; color: white; box-shadow: 0 4px 16px rgba(220,38,38,0.25); }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(220,38,38,0.3); }

        .avatar {
            width: 96px; height: 96px; border-radius: 50%; background: linear-gradient(135deg, var(--green-400), var(--green-600));
            border: 3px solid white; box-shadow: 0 6px 24px rgba(4,160,82,0.25);
            display: flex; align-items: center; justify-content: center; color: white;
            font-weight: 800; font-size: 32px; overflow: hidden; flex-shrink: 0;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }

        .flash {
            border-radius: 14px; padding: 12px 18px; font-size: 14px; font-weight: 500;
            display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
        }
        .flash.success { background: var(--green-500); color: white; box-shadow: 0 8px 28px rgba(4,160,82,0.3); }
        .flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        @if(session('status') === 'profile-updated')
            <div class="flash success">
                <i class="fas fa-check-circle"></i> Profile information updated.
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif
        @if(session('status') === 'password-updated')
            <div class="flash success">
                <i class="fas fa-check-circle"></i> Password updated.
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif
        @if(session('success'))
            <div class="flash success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <a href="{{ route('profile.show') }}" class="text-xs font-semibold text-[#04A052] hover:underline">
                        <i class="fas fa-arrow-left mr-1"></i>Back to profile
                    </a>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800 mt-1">Edit Profile</h1>
                </div>
            </div>
        </div>

        <!-- Avatar -->
        <div class="card">
            <h2><i class="fas fa-camera text-[#04A052] mr-2"></i>Profile picture</h2>
            <p class="sub">Your photo or initials are shown to helpers and peers.</p>
            <div class="flex items-center gap-6 mt-5 flex-wrap">
                <div class="avatar">
                    @if($user->avatar_path)
                        <img src="{{ asset($user->avatar_path) }}" alt="Profile picture">
                    @else
                        {{ $user->initials() }}
                    @endif
                </div>
                <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="flex-1 min-w-[240px]">
                    @csrf
                    <div class="flex items-center gap-3 flex-wrap">
                        <label for="avatar" class="btn btn-primary text-sm cursor-pointer">
                            <i class="fas fa-upload mr-1"></i> Upload new picture
                        </label>
                        <input type="file" id="avatar" name="avatar" accept="image/*" class="hidden" onchange="this.form.submit()">
                        <p class="text-xs text-gray-400">JPG, PNG, GIF or WebP · max 2MB</p>
                    </div>
                    @error('avatar')
                        <p class="form-error"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                    @enderror
                </form>
            </div>
        </div>

        <!-- Profile information -->
        <div class="card">
            <h2><i class="fas fa-user text-[#04A052] mr-2"></i>Profile information</h2>
            <p class="sub">Update your display name and email address.</p>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-5">
                @csrf
                @method('patch')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="form-label">Display name</label>
                        <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" class="form-input" value="{{ old('email', $user->email) }}" required autocomplete="email">
                        @error('email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-5">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Save changes
                    </button>
                    @if($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                        <div>
                            <p class="text-xs text-gray-500">Your email address is unverified.</p>
                            <form method="POST" action="{{ route('verification.send') }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-[#04A052] hover:underline mt-1">
                                    Click here to re-send the verification email.
                                </button>
                            </form>
                            @if(session('status') === 'verification-link-sent')
                                <p class="mt-2 text-xs text-[#04A052]">A new verification link has been sent to your email address.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- Password -->
        <div class="card">
            <h2><i class="fas fa-key text-[#04A052] mr-2"></i>Update password</h2>
            <p class="sub">Use at least 8 characters. Don't reuse an old password.</p>

            <form method="POST" action="{{ route('password.update') }}" class="mt-5">
                @csrf
                @method('put')

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="current_password" class="form-label">Current password</label>
                        <input id="current_password" name="current_password" type="password" class="form-input" required autocomplete="current-password">
                        @error('current_password')
                            <p class="form-error"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="form-label">New password</label>
                        <input id="password" name="password" type="password" class="form-input" required autocomplete="new-password">
                        @error('password') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="form-input" required autocomplete="new-password">
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-5">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key mr-1"></i> Update password
                    </button>
                </div>
            </form>
        </div>

        <!-- Delete account -->
        <div class="card border-red-200">
            <h2 class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Delete account</h2>
            <p class="sub">This permanently deletes your account, sessions, and saved resources. This cannot be undone.</p>

            <form method="POST" action="{{ route('profile.delete-account') }}" class="mt-5"
                  onsubmit="return confirm('Are you absolutely sure? Your account, sessions, and all data will be permanently deleted.');">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="form-label">Enter your password to confirm</label>
                        <input id="password" name="password" type="password" class="form-input" required autocomplete="current-password">
                        @error('password')
                            <p class="form-error"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox" name="confirm_delete" value="1" required class="w-4 h-4 accent-[#04A052]">
                            I understand this will permanently delete my account and all my data.
                        </label>
                    </div>
                </div>

                <div class="mt-5">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt mr-1"></i> Permanently delete my account
                    </button>
                </div>
            </form>
        </div>

    </main>

    @include('partials.sidebar', [
        'active' => ['profile*'],
        'role'   => 'Help Seeker',
    ])

    @include('layouts.partials.pwa-banner')

</body>
</html>