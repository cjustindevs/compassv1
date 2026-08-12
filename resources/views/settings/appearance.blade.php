<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Appearance</title>

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

        .tab-nav { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
        .tab-link {
            padding: 9px 18px; border-radius: 20px; font-size: 13px; font-weight: 600;
            border: 1px solid var(--gray-200); background: white; color: var(--gray-500);
            text-decoration: none; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 7px;
        }
        .tab-link:hover { border-color: var(--green-300); color: var(--green-700); }
        .tab-link.active { background: var(--green-500); border-color: var(--green-500); color: white; box-shadow: 0 4px 14px rgba(4,160,82,0.25); }

        .card { background: white; border: 1px solid var(--gray-200); border-radius: 24px; padding: 28px; margin-bottom: 20px; }
        .card h2 { font-weight: 800; font-size: 17px; color: var(--gray-800); }
        .card .sub { font-size: 13px; color: var(--gray-500); margin-top: 2px; }

        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 0; border-bottom: 1px solid var(--gray-100); gap: 16px;
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-switch { position: relative; width: 46px; height: 25px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-switch .slider {
            position: absolute; inset: 0; background: var(--gray-200);
            border-radius: 25px; transition: all 0.3s ease; cursor: pointer;
        }
        .toggle-switch .slider::before {
            content: ''; position: absolute; width: 19px; height: 19px; border-radius: 50%;
            background: white; top: 3px; left: 3px; transition: all 0.3s ease;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .toggle-switch input:checked + .slider { background: var(--green-500); }
        .toggle-switch input:checked + .slider::before { transform: translateX(21px); }

        .font-option {
            border: 2px solid var(--gray-200); border-radius: 16px; padding: 14px 18px;
            cursor: pointer; transition: all 0.25s ease; text-align: center; background: white;
        }
        .font-option:hover { border-color: var(--green-300); }
        .font-option.selected { border-color: var(--green-500); background: var(--green-50); box-shadow: 0 4px 16px rgba(4,160,82,0.15); }
        .font-option .sample { font-weight: 600; color: var(--gray-800); }
        .font-option .size-name { font-size: 11px; color: var(--gray-500); margin-top: 4px; }
        .font-option.small .sample { font-size: 14px; }
        .font-option.medium .sample { font-size: 16px; }
        .font-option.large .sample { font-size: 20px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            font-weight: 600; font-size: 14px; padding: 11px 22px; border-radius: 12px;
            border: none; cursor: pointer; transition: all 0.25s ease; text-decoration: none;
        }
        .btn-primary { background: linear-gradient(135deg, var(--green-500), var(--green-600)); color: white; box-shadow: 0 4px 16px rgba(4,160,82,0.25); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(4,160,82,0.35); }
        .btn-outline { background: white; border: 1px solid var(--gray-300); color: var(--gray-600); }
        .btn-outline:hover { border-color: var(--green-500); color: var(--green-700); }

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

        @if(session('success'))
            <div class="flash success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif
        @if($errors->any())
            <div class="flash error">
                <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <a href="{{ route('settings') }}" class="text-xs font-semibold text-[#04A052] hover:underline">
                        <i class="fas fa-arrow-left mr-1"></i>All settings
                    </a>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800 mt-1">Appearance</h1>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-nav">
            <a href="{{ route('settings.account') }}" class="tab-link"><i class="fas fa-user-cog"></i>Account</a>
            <a href="{{ route('settings.preferences') }}" class="tab-link"><i class="fas fa-sliders-h"></i>Preferences</a>
            <a href="{{ route('settings.privacy') }}" class="tab-link"><i class="fas fa-shield-alt"></i>Privacy</a>
            <a href="{{ route('settings.appearance') }}" class="tab-link active"><i class="fas fa-palette"></i>Appearance</a>
        </div>

        <form method="POST" action="{{ route('settings.appearance.update') }}">
            @csrf
            @method('patch')

            <!-- Theme -->
            <div class="card">
                <h2><i class="fas fa-moon text-[#04A052] mr-2"></i>Theme</h2>
                <p class="sub">Choose a look that feels comfortable to you.</p>
                <div class="mt-3">
                    <div class="toggle-row">
                        <div>
                            <p class="font-semibold text-sm text-gray-800">Dark mode</p>
                            <p class="text-xs text-gray-500 mt-0.5">Easier on the eyes in low light</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="dark_mode" value="1" @checked($user->dark_mode)>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <p class="font-semibold text-sm text-gray-800">High contrast mode</p>
                            <p class="text-xs text-gray-500 mt-0.5">Stronger colors and sharper contrast for readability</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="high_contrast" value="1" @checked($user->high_contrast)>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Font size -->
            <div class="card">
                <h2><i class="fas fa-text-height text-[#04A052] mr-2"></i>Font size</h2>
                <p class="sub">Adjust text size across COMPASS.</p>
                <div class="grid grid-cols-3 gap-3 mt-5 max-w-xl">
                    @foreach($fontSizes as $size)
                        <label class="cursor-pointer">
                            <input type="radio" name="font_size" value="{{ $size }}" class="hidden"
                                   @checked($user->font_size === $size)>
                            <div class="font-option {{ $size }} {{ $user->font_size === $size ? 'selected' : '' }}"
                                 onclick="this.closest('label').querySelector('input').checked = true; document.querySelectorAll('.font-option').forEach(o => o.classList.remove('selected')); this.classList.add('selected');">
                                <div class="sample">Aa</div>
                                <div class="size-name">{{ ucfirst($size) }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Accessibility -->
            <div class="card">
                <h2><i class="fas fa-universal-access text-[#04A052] mr-2"></i>Accessibility</h2>
                <p class="sub">COMPASS is designed to be usable by everyone.</p>
                <ul class="space-y-2 text-sm text-gray-600 mt-3">
                    <li class="flex items-center gap-2"><i class="fas fa-keyboard text-[#04A052] w-4"></i> Full keyboard navigation supported</li>
                    <li class="flex items-center gap-2"><i class="fas fa-volume-up text-[#04A052] w-4"></i> Compatible with screen readers</li>
                    <li class="flex items-center gap-2"><i class="fas fa-text-height text-[#04A052] w-4"></i> Font size options above apply everywhere</li>
                    <li class="flex items-center gap-2"><i class="fas fa-eye text-[#04A052] w-4"></i> High contrast mode enhances text clarity</li>
                </ul>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Save appearance
            </button>
        </form>

    </main>

    @include('partials.sidebar', [
        'active' => ['settings*'],
        'role'   => 'Help Seeker',
    ])

</body>
</html>