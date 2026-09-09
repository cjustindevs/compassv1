<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'COMPASS')</title>
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
        .input-focus[aria-invalid="true"] { border-color: #dc2626; }
        .btn-primary:focus-visible { outline: 3px solid #15803d; outline-offset: 3px; }
        @media (prefers-reduced-motion: reduce) { .btn-primary { transition: none; } .btn-primary:hover { transform: none; } }
        .card-shadow { box-shadow: 0 20px 60px rgba(0,0,0,0.06); }
        @media (max-width: 640px) {
            .p-8 { padding: 1.5rem; }
            .text-2xl { font-size: 1.25rem; }
        }
    </style>
    @stack('styles')
</head>
<body class="compass-compact gradient-bg min-h-screen flex items-center justify-center py-12">

    <div class="w-full @yield('container-width', 'max-w-md') mx-4">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="flex justify-center">
                <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS"
                     class="h-14 sm:h-16 w-auto max-w-full">
            </div>
            <p class="text-gray-500 text-base mt-3">@yield('subtitle')</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl card-shadow overflow-hidden">
            <div class="p-6 md:p-8">

                @yield('content')
            </div>

            <div class="border-t border-gray-100 px-6 md:px-8 py-4 bg-gray-50/50">
                @yield('footer')
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            <i class="fas fa-shield-alt text-green-500 mr-1"></i>
            100% Confidential · Anonymous · Secure
        </p>
    </div>

    @include('layouts.partials.pwa-banner')

@stack('scripts')
</body>
</html>
