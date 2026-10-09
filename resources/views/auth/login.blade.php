@extends('layouts.auth')

@section('title', 'COMPASS - Login')
@section('subtitle', 'Sign in to your account')

@section('content')
                <h1 class="auth-title">Welcome back</h1>
                <p class="auth-intro">Sign in with your email or your COMPASS nickname.</p>

                @if ($errors->any())
                    <div id="login-error" role="alert" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1.5">Email or alias</label>
                        <input id="login-email" type="text" name="email" value="{{ old('email') }}"
                               class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                               placeholder="Nickname@compass.local" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
                               aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if($errors->any()) aria-describedby="login-error" @endif>
                    </div>

                    <div class="mb-4">
                        <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <input id="login-password" type="password" name="password"
                               class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                               placeholder="Enter your password" autocomplete="current-password" required>
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
                        <x-ui-icon name="arrow-right" class="mr-2" /> Sign In
                    </button>
                </form>
@endsection

@section('footer')
                <p class="text-center text-sm text-gray-500">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-green-600 font-medium hover:underline">Get Started</a>
                </p>
@endsection
