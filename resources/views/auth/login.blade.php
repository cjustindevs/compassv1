<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #ECFDF5 0%, #DCFCE7 50%, #FFFFFF 100%); }
        .btn-primary {
            background: linear-gradient(135deg, #16A34A, #22C55E);
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(22, 163, 74, 0.3);
        }
        .btn-primary:hover { transform: scale(1.02); box-shadow: 0 8px 40px rgba(22, 163, 74, 0.4); }
        .input-focus:focus { border-color: #16A34A; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15); }
        .card-shadow { box-shadow: 0 20px 60px rgba(0,0,0,0.06); }
        @media (max-width: 640px) {
            .p-8 { padding: 1.5rem; }
            .text-2xl { font-size: 1.25rem; }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center py-12">

    <div class="w-full max-w-md mx-4">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/20">
                    <span class="text-white font-extrabold text-xl">C</span>
                </div>
                <span class="text-2xl font-extrabold text-gray-800">COMPASS</span>
            </div>
            <p class="text-gray-500 text-sm mt-1">Sign in to your account</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl card-shadow overflow-hidden">
            <div class="p-6 md:p-8">

                <h2 class="text-xl font-bold text-gray-800 mb-2">Welcome Back</h2>
                <p class="text-gray-500 text-sm mb-6">Enter your credentials to continue.</p>

                @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                               placeholder="you@university.edu" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <input type="password" name="password"
                               class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                               placeholder="Enter your password" required>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remember" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                            Remember me
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm text-green-600 hover:underline">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn-primary w-full py-3 rounded-xl text-white font-semibold transition-all">
                        <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                    </button>
                </form>
            </div>

            <div class="border-t border-gray-100 px-6 md:px-8 py-4 bg-gray-50/50">
                <p class="text-center text-sm text-gray-500">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-green-600 font-medium hover:underline">Get Started</a>
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            <i class="fas fa-shield-alt text-green-500 mr-1"></i>
            100% Confidential · Anonymous · Secure
        </p>
    </div>

</body>
</html>