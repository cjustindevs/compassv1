@extends('layouts.app')

@section('title', 'COMPASS – Resources')

@push('styles')
<style>
        .category-pill {
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            border: 1px solid #E5E7EB; background: white; color: #6B7280;
            text-decoration: none; transition: all 0.15s; display: inline-flex; align-items: center; gap: 4px;
        }
        .category-pill:hover { border-color: #04A052; color: #027039; }
        .category-pill.active { background: #EAF8F0; border-color: #04A052; color: #027039; }

        .adviser-resource-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
        .resource-history { border-top: 1px solid #edf2ef; padding-top: 8px; font-size: 12px; }
        .resource-history > summary { cursor: pointer; color: #087341; font-weight: 600; }
        .resource-history .review-history > h3 { display: none; }
        .resource-card {
            background: white; border: 1px solid #E5E7EB; border-radius: 18px;
            padding: 20px; transition: all 0.2s; display: flex; flex-direction: column; gap: 10px;
        }
        .resource-card:hover { border-color: #04A052; box-shadow: none; transform: translateY(-2px); }
        .resource-card .icon-box {
            width: 48px; height: 48px; border-radius: 14px; background: #EAF8F0;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .category-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #027039; }
        .unpublished-tag { font-size: 11px; font-weight: 700; background: #F3F4F6; color: #6B7280; padding: 2px 8px; border-radius: 10px; }

        .form-input {
            width: 100%; padding: 10px 14px; border: 1px solid #E5E7EB;
            border-radius: 12px; font-size: 14px; outline: none; transition: border-color 0.15s;
        }
        .form-input:focus { border-color: #04A052; }
        .form-label { font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px; display: block; }

        .modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.35);
            z-index: 300; align-items: center; justify-content: center; padding: 16px;
        }
        .modal-backdrop.active { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <x-ui.page-header title="Resources" description="Manage the self-help content library">
            <x-slot:actions>
                <a class="btn-secondary" href="{{ route('adviser.resources',['section'=>'emergency']) }}">Manage emergency contacts</a>
                <button type="button" class="btn-primary" id="openAddModal"><x-ui-icon name="plus" /> Add resource</button>
            </x-slot:actions>
        </x-ui.page-header>

        @if(session('success'))
            <div class="flash-success" role="status"> {{ session('success') }}</div>
        @endif

        @if($errors->any())<div role="alert" class="ui-notice">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <x-ui.kpi-grid>
            <x-ui.kpi label="Total Resources" :value="$stats['total']" tone="neutral" />
            <x-ui.kpi label="Published" :value="$stats['published']" tone="green" />
            <x-ui.kpi label="Total Views" :value="number_format($stats['views'])" tone="blue" />
            <x-ui.kpi label="Featured" :value="$stats['featured']" tone="amber" />
        </x-ui.kpi-grid>

        <x-ui.filter-bar :action="route('adviser.resources')" aria-label="Resource filters">
            @if(request()->filled('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
            <x-ui.field label="Search" for="resourceSearch"><input id="resourceSearch" type="text" name="search" data-control="search" value="{{ request('search') }}" placeholder="Search resources..."></x-ui.field>
            <x-slot:buttons><button type="submit" class="btn-primary" aria-label="Search" title="Search"><x-ui-icon name="search" /> Search</button></x-slot:buttons>
        </x-ui.filter-bar>
        <div class="flex items-center gap-2 mb-5 flex-wrap">
            <a href="{{ route('adviser.resources') }}" class="category-pill {{ !request('category') || request('category') === 'all' ? 'active' : '' }}">All</a>
            @foreach($categories as $category)
                <a href="{{ route('adviser.resources', ['category' => $category, 'search' => request('search')]) }}"
                   class="category-pill {{ request('category') === $category ? 'active' : '' }}">
                    {{ ucfirst($category) }}
                </a>
            @endforeach
        </div>

        <!-- Resource Grid -->
        @if($resources->isNotEmpty())
            <div class="adviser-resource-grid">
                @foreach($resources as $resource)
                    <div class="resource-card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">

                                <div>
                                    <p class="category-tag">{{ $resource->category }}</p>
                                    @if($resource->is_featured)
                                        <span class="text-xs text-yellow-500"> Featured</span>
                                    @endif
                                </div>
                            </div>
                            @if(!$resource->is_published)
                                <span class="unpublished-tag">Unpublished</span>
                            @endif
                        </div>

                        <p class="text-xs text-gray-500">{{ ucfirst($resource->visibility) }} | {{ $resource->archived_at ? 'Archived' : (($resource->review_date && $resource->review_date < now('Asia/Manila')->toDateString()) ? 'Review overdue' : 'Current') }}</p>
                        <h3 class="font-bold text-gray-800 leading-snug">{{ $resource->title }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed line-clamp-2">{{ $resource->description }}</p>

                        <div class="flex items-center gap-3 text-xs text-gray-400">
                            @if($resource->duration)
                                <span> {{ $resource->duration }}</span>
                            @endif
                            <span class="capitalize"> {{ $resource->difficulty_label }}</span>
                            <span>{{ number_format($resource->views_count) }} views</span>
                            @if($resource->saved_count > 0)
                                <span>{{ $resource->saved_count }} saved</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 mt-1">
                            <button class="btn-secondary edit-resource" type="button"
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
                                    data-visibility="{{ $resource->visibility }}" data-review="{{ $resource->review_date }}" data-content="{{ $resource->content }}">
                                <x-ui-icon name="edit"  /> Edit
                            </button>
                            <form class="form-maximized" method="POST" action="{{ route('adviser.resources.destroy', ['id' => $resource->id]) }}"
                                  data-confirm="Archive resource?"
                                  data-confirm-message="This resource will be hidden; version history will remain available."
                                  data-confirm-text="Archive"
                                  data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-secondary" style="color:#EF4444;border-color:#FECACA;"><x-ui-icon name="trash"  /> Archive</button>
                            </form>
                        </div>
                        <details class="resource-history"><summary>Update history</summary><x-supervision-history :record="$resource" /></details>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $resources->links('vendor.pagination.compass', ['noun' => 'resources']) }}
            </div>
        @else
            <x-ui.empty title="No resources found" text="Try a different search or add a new resource." />
        @endif

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">

            Quality supervision leads to quality support.
        </div>
</div>
    <!-- Bottom Navigation -->
    <!-- Add / Edit Modal -->
    <div class="modal-backdrop" id="resourceModal">
        <div class="modal-box">
            <div class="flex items-center justify-between mb-4">
                <h2 class="ui-card-title" id="modalTitle">Add Resource</h2>
                <button type="button" class="btn-secondary" id="closeModal" aria-label="Close dialog" title="Close dialog"><x-ui-icon name="close"  /></button>
            </div>

            <form class="form-maximized" method="POST" action="{{ route('adviser.resources.store') }}" id="resourceForm">
                @csrf
                <input type="hidden" name="_method" value="POST">
<label class="block text-sm mb-3">Visibility<select name="visibility" class="form-input"><option value="public">Public resource library</option><option value="internal">Adviser-only reference</option></select></label>
<label class="block text-sm mb-3">Next review date<input type="date" name="review_date" class="form-input"></label>
<label class="block text-sm mb-3">Change reason<input name="change_reason" maxlength="1000" class="form-input"></label>
                <div class="mb-4">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" required maxlength="255" class="form-input" placeholder="e.g. Breathing exercise guide">
                </div>
                <div class="mb-4">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2" class="form-input" placeholder="Short summary shown in the library"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
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
                        <label class="form-label">Icon class</label>
                        <input type="text" name="icon" maxlength="50" class="form-input" placeholder="e.g. fa-book-open">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
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
                <button type="submit" class="btn-primary w-full justify-center"><x-ui-icon name="save"  /> Save Resource</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                form.querySelector('[name="_method"]').value = 'POST';
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
                    form.querySelector('[name="_method"]').value = 'PUT';
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
<script>document.querySelectorAll('.edit-resource').forEach(button=>button.addEventListener('click',()=>{const form=document.getElementById('resourceForm');form.elements.visibility.value=button.dataset.visibility||'public';form.elements.review_date.value=button.dataset.review||'';}));</script>
@endsection
