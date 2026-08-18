<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Resources</title>

    @vite(['resources/js/app.js', 'resources/js/adviser-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
            --green-100: #D0F0D8;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #163B2D;
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
        }

        body { background: #F8FBF9; }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4,160,82,0.06);
            box-shadow: 4px 0 40px rgba(0,0,0,0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
        }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-label { font-size: 13px; color: var(--gray-500); }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 6px 14px;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        .category-pill {
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            border: 1px solid var(--gray-200); background: white; color: var(--gray-500);
            text-decoration: none; transition: all 0.15s; display: inline-flex; align-items: center; gap: 4px;
        }
        .category-pill:hover { border-color: var(--green-500); color: var(--green-700); }
        .category-pill.active { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        .resource-card {
            background: white; border: 1px solid var(--gray-200); border-radius: 18px;
            padding: 20px; transition: all 0.2s; display: flex; flex-direction: column; gap: 10px;
        }
        .resource-card:hover { border-color: var(--green-500); box-shadow: 0 8px 24px rgba(4,160,82,0.08); transform: translateY(-2px); }
        .resource-card .icon-box {
            width: 48px; height: 48px; border-radius: 14px; background: var(--green-50);
            display: flex; align-items: center; justify-content: center; font-size: 22px;
        }
        .category-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--green-700); }
        .unpublished-tag { font-size: 10px; font-weight: 700; background: var(--gray-100); color: var(--gray-500); padding: 2px 8px; border-radius: 10px; }

        .form-input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200);
            border-radius: 12px; font-size: 13px; outline: none; transition: border-color 0.15s;
        }
        .form-input:focus { border-color: var(--green-500); }
        .form-label { font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; display: block; }

        .modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.35);
            z-index: 300; align-items: center; justify-content: center; padding: 16px;
        }
        .modal-backdrop.active { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }

        .flash-success { background: var(--green-50); color: var(--green-700); border: 1px solid var(--green-100); border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--gray-700); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.adviser-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Resources</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Manage the self-help content library
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Resources</span>
                    <span class="text-2xl">📚</span>
                </div>
                <div class="stat-number">{{ $stats['total'] }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Published</span>
                    <span class="text-2xl">✅</span>
                </div>
                <div class="stat-number">{{ $stats['published'] }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Views</span>
                    <span class="text-2xl">👁️</span>
                </div>
                <div class="stat-number">{{ number_format($stats['views']) }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Featured</span>
                    <span class="text-2xl">⭐</span>
                </div>
                <div class="stat-number">{{ $stats['featured'] }}</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="{{ route('adviser.resources') }}" class="flex items-center gap-2 flex-1 min-w-[220px]">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search resources..."
                           class="form-input flex-1">
                    <button type="submit" class="btn-outline"><i class="fas fa-search"></i></button>
                    @if(request()->has('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                </form>
                <button class="btn-primary" id="openAddModal"><i class="fas fa-plus"></i> Add Resource</button>
            </div>

            <div class="flex items-center gap-2 mt-4 flex-wrap">
                <a href="{{ route('adviser.resources') }}" class="category-pill {{ !request('category') || request('category') === 'all' ? 'active' : '' }}">
                    <i class="fas fa-th-large"></i> All
                </a>
                @foreach($categories as $category)
                    <a href="{{ route('adviser.resources', ['category' => $category, 'search' => request('search')]) }}"
                       class="category-pill {{ request('category') === $category ? 'active' : '' }}">
                        {{ ucfirst($category) }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Resource Grid -->
        @if($resources->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($resources as $resource)
                    <div class="resource-card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="icon-box">{{ $resource->icon ?: '📄' }}</div>
                                <div>
                                    <p class="category-tag">{{ $resource->category }}</p>
                                    @if($resource->is_featured)
                                        <span class="text-xs text-yellow-500"><i class="fas fa-star"></i> Featured</span>
                                    @endif
                                </div>
                            </div>
                            @if(!$resource->is_published)
                                <span class="unpublished-tag">Unpublished</span>
                            @endif
                        </div>

                        <h3 class="font-bold text-gray-800 leading-snug">{{ $resource->title }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed line-clamp-2">{{ $resource->description }}</p>

                        <div class="flex items-center gap-3 text-xs text-gray-400">
                            @if($resource->duration)
                                <span><i class="far fa-clock"></i> {{ $resource->duration }}</span>
                            @endif
                            <span class="capitalize"><i class="fas fa-signal"></i> {{ $resource->difficulty_label }}</span>
                            <span><i class="far fa-eye"></i> {{ number_format($resource->views_count) }}</span>
                            @if($resource->saved_count > 0)
                                <span><i class="fas fa-bookmark"></i> {{ $resource->saved_count }}</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 mt-1">
                            <button class="btn-outline edit-resource"
                                    data-id="{{ $resource->id }}"
                                    data-title="{{ $resource->title }}"
                                    data-description="{{ $resource->description }}"
                                    data-category="{{ $resource->category }}"
                                    data-duration="{{ $resource->duration }}"
                                    data-difficulty="{{ $resource->difficulty }}"
                                    data-icon="{{ $resource->icon }}"
                                    data-tags="{{ is_array($resource->tags) ? implode(', ', $resource->tags) : '' }}"
                                    data-featured="{{ $resource->is_featured ? '1' : '0' }}"
                                    data-published="{{ $resource->is_published ? '1' : '0' }}"
                                    data-content="{{ $resource->content }}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" action="{{ route('adviser.resources.destroy', ['id' => $resource->id]) }}"
                                  onsubmit="return confirm('Delete this resource? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-outline" style="color:var(--red-500);border-color:#FECACA;"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $resources->links() }}
            </div>
        @else
            <div class="card">
                <div class="empty-state text-center py-12 text-gray-400">
                    <i class="fas fa-book-open text-4xl mb-3 block opacity-50"></i>
                    <p class="text-lg font-medium text-gray-600">No resources found</p>
                    <p>Try a different search or add a new resource.</p>
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item"><i class="fas fa-clipboard-list"></i><span>Evaluations</span></a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item"><i class="fas fa-arrow-right"></i><span>Referrals</span></a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item"><i class="fas fa-users"></i><span>Helpers</span></a>
        <a href="{{ route('adviser.notifications') }}" class="nav-item"><i class="fas fa-bell"></i><span>Alerts</span></a>
    </nav>

    <!-- Add / Edit Modal -->
    <div class="modal-backdrop" id="resourceModal">
        <div class="modal-box">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-gray-800 text-lg" id="modalTitle">Add Resource</h3>
                <button class="btn-outline" id="closeModal"><i class="fas fa-times"></i></button>
            </div>

            <form method="POST" action="{{ route('adviser.resources.store') }}" id="resourceForm">
                @csrf
                <div class="mb-4">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" required maxlength="255" class="form-input" placeholder="e.g. Breathing exercise guide">
                </div>
                <div class="mb-4">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2" class="form-input" placeholder="Short summary shown in the library"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-input" id="categorySelect">
                            @foreach(['article', 'video', 'tool', 'guide', 'meditation', 'exercise'] as $cat)
                                <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                            @endforeach
                            @foreach($categories as $category)
                                @if(!in_array($category, ['article', 'video', 'tool', 'guide', 'meditation', 'exercise'], true))
                                    <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Icon (emoji)</label>
                        <input type="text" name="icon" maxlength="50" class="form-input" placeholder="e.g. 🧘">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label">Duration</label>
                        <input type="text" name="duration" maxlength="50" class="form-input" placeholder="e.g. 10 min">
                    </div>
                    <div>
                        <label class="form-label">Difficulty</label>
                        <select name="difficulty" class="form-input">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Tags (comma separated)</label>
                    <input type="text" name="tags" maxlength="255" class="form-input" placeholder="e.g. anxiety, breathing, mindfulness">
                </div>
                <div class="mb-4">
                    <label class="form-label">Content</label>
                    <textarea name="content" rows="4" class="form-input" placeholder="Full content or link"></textarea>
                </div>
                <div class="flex items-center gap-6 mb-6">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                        <input type="checkbox" name="is_featured" value="1" class="accent-[#04A052]"> Featured
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                        <input type="checkbox" name="is_published" value="1" checked class="accent-[#04A052]"> Published
                    </label>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-save"></i> Save Resource</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburgerBtn');

            function toggleSidebar() {
                sidebar.classList.toggle('closed');
                overlay.classList.toggle('active');
            }

            function closeSidebar() {
                sidebar.classList.add('closed');
                overlay.classList.remove('active');
            }

            hamburger.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) closeSidebar();
            });

            // ── Add / Edit modal ──
            const modal = document.getElementById('resourceModal');
            const form = document.getElementById('resourceForm');
            const modalTitle = document.getElementById('modalTitle');

            function openModal() {
                modal.classList.add('active');
            }

            document.getElementById('openAddModal').addEventListener('click', function () {
                form.action = @json(route('adviser.resources.store'));
                form.method = 'POST';
                modalTitle.textContent = 'Add Resource';
                form.reset();
                form.querySelector('[name="is_published"]').checked = true;
                openModal();
            });

            document.getElementById('closeModal').addEventListener('click', function () {
                modal.classList.remove('active');
            });
            modal.addEventListener('click', function (e) {
                if (e.target === modal) modal.classList.remove('active');
            });

            document.querySelectorAll('.edit-resource').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const d = btn.dataset;
                    form.action = @json(route('adviser.resources.update', ['id' => '__ID__'])).replace('__ID__', d.id);
                    modalTitle.textContent = 'Edit Resource';
                    form.querySelector('[name="title"]').value = d.title || '';
                    form.querySelector('[name="description"]').value = d.description || '';
                    form.querySelector('[name="category"]').value = d.category || 'article';
                    form.querySelector('[name="icon"]').value = d.icon || '';
                    form.querySelector('[name="duration"]').value = d.duration || '';
                    form.querySelector('[name="difficulty"]').value = d.difficulty || 'beginner';
                    form.querySelector('[name="tags"]').value = d.tags || '';
                    form.querySelector('[name="content"]').value = d.content || '';
                    form.querySelector('[name="is_featured"]').checked = d.featured === '1';
                    form.querySelector('[name="is_published"]').checked = d.published === '1';
                    openModal();
                });
            });
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
