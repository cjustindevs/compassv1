<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Helper Dashboard</title>
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
        .btn-outline {
            border: 2px solid #16A34A;
            color: #16A34A;
            transition: all 0.3s ease;
        }
        .btn-outline:hover { background: #16A34A; color: white; transform: scale(1.02); }
        .card-shadow { box-shadow: 0 20px 60px rgba(0,0,0,0.06); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 24px 80px rgba(0,0,0,0.1); }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #16A34A, #22C55E);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        @media (max-width: 640px) {
            .stat-number { font-size: 1.8rem; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white border-b border-gray-200 px-4 py-3 shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/20">
                    <span class="text-white font-extrabold text-lg">C</span>
                </div>
                <span class="text-xl font-extrabold text-gray-800">COMPASS</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600 hidden sm:inline">
                    Welcome, <span class="font-semibold text-gray-800">{{ auth()->user()->name }}</span>
                </span>
                <span class="text-xs text-gray-400 sm:hidden">
                    {{ auth()->user()->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-red-500 hover:text-red-700 transition font-medium">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="max-w-7xl mx-auto px-4 py-6 md:py-10">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 md:mb-10">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Helper Dashboard</h1>
                <p class="text-gray-500 text-sm mt-1">
                    You are logged in as a <span class="font-semibold text-green-600">Peer Helper</span>
                </p>
            </div>
            <div class="flex gap-3">
                <button class="btn-primary px-5 py-2.5 rounded-xl text-white font-semibold text-sm">
                    <i class="fas fa-clock mr-2"></i> Set Availability
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-10">
            <div class="card-shadow bg-white rounded-2xl p-4 md:p-6 card-hover">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Active Sessions</p>
                <p class="stat-number">0</p>
            </div>
            <div class="card-shadow bg-white rounded-2xl p-4 md:p-6 card-hover">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Queue</p>
                <p class="stat-number">0</p>
            </div>
            <div class="card-shadow bg-white rounded-2xl p-4 md:p-6 card-hover">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Completed</p>
                <p class="stat-number">0</p>
            </div>
            <div class="card-shadow bg-white rounded-2xl p-4 md:p-6 card-hover">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Competency</p>
                <p class="stat-number">--</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">

            <div class="card-shadow bg-white rounded-2xl p-5 md:p-6 card-hover">
                <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4">
                    <i class="fas fa-users text-2xl text-green-600"></i>
                </div>
                <h3 class="font-semibold text-gray-800">View Queue</h3>
                <p class="text-sm text-gray-500 mt-1">Check pending support requests.</p>
                <button class="btn-primary mt-4 w-full px-4 py-2.5 rounded-xl text-white font-semibold text-sm">
                    View Queue
                </button>
            </div>

            <div class="card-shadow bg-white rounded-2xl p-5 md:p-6 card-hover">
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center mb-4">
                    <i class="fas fa-star text-2xl text-blue-600"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Competency</h3>
                <p class="text-sm text-gray-500 mt-1">View your evaluation and feedback.</p>
                <button class="btn-outline mt-4 w-full px-4 py-2.5 rounded-xl font-semibold text-sm">
                    View Details
                </button>
            </div>

            <div class="card-shadow bg-white rounded-2xl p-5 md:p-6 card-hover">
                <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center mb-4">
                    <i class="fas fa-clock text-2xl text-purple-600"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Duty Hours</h3>
                <p class="text-sm text-gray-500 mt-1">Manage your availability schedule.</p>
                <button class="btn-outline mt-4 w-full px-4 py-2.5 rounded-xl font-semibold text-sm">
                    Set Schedule
                </button>
            </div>

        </div>

        <!-- Recent Sessions -->
        <div class="mt-8 md:mt-12">
            <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-4">Recent Sessions</h2>
            <div class="card-shadow bg-white rounded-2xl p-5 md:p-6">
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                    <p>No sessions yet.</p>
                    <p class="text-sm">Check the queue for pending requests.</p>
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <footer class="bg-white border-t border-gray-200 mt-8 py-4">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs text-gray-400">
            &copy; 2026 COMPASS · Project Dial-A-Friend · Divine Word College of Calapan
        </div>
    </footer>

</body>
</html>