@extends('layouts.app')
@section('title', 'Areas of concern - COMPASS')
@section('content')
@php
    $chips = \App\Support\FilterChips::from(request(), ['status' => 'Status']);
@endphp
<div class="concern-management">
    <x-ui.page-header title="Areas of concern" description="Manage the categories available when a seeker requests support.">
        <x-slot:actions>
            <x-ui.badge tone="green">{{ $categories->total() }} categories</x-ui.badge>
            <button type="button" class="btn-primary" data-dialog-open="addConcernDialog"><x-ui-icon name="plus" /> Add category</button>
        </x-slot:actions>
    </x-ui.page-header>
    @if(session('success'))<div class="ui-card" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="ui-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    {{-- Add a category: a pop-up opened from the header. It reopens by itself after a failed submit. --}}
    <dialog id="addConcernDialog" class="workflow-dialog" aria-labelledby="addConcernTitle" @if($errors->any() && old('concern_form') === 'add') data-open-on-load @endif>
        <header class="workflow-dialog__header"><div><h2 id="addConcernTitle">Add a category</h2><p>New categories are active immediately. Keep names short and easy to understand.</p></div><button type="button" data-dialog-close aria-label="Close"><x-ui-icon name="close" /></button></header>
        <form method="POST" action="{{ route('concerns.store') }}">@csrf
            <div class="workflow-dialog__body concern-fields">
                <x-ui.field label="Category name" for="newConcernName"><input id="newConcernName" name="concern_name" required maxlength="100" placeholder="Enter a category name" value="{{ old('concern_form') === 'add' ? old('concern_name') : '' }}"></x-ui.field>
                <x-ui.field label="Description (optional)" for="newConcernDescription"><textarea id="newConcernDescription" name="description" maxlength="500" rows="2" placeholder="Briefly describe this concern">{{ old('concern_form') === 'add' ? old('description') : '' }}</textarea></x-ui.field>
            </div>
            <input type="hidden" name="is_active" value="1"><input type="hidden" name="concern_form" value="add">
            <footer class="workflow-dialog__footer"><button type="button" class="btn-secondary" data-dialog-close>Cancel</button><button class="btn-primary" type="submit">Add category</button></footer>
        </form>
    </dialog>

    <x-ui.card class="concern-list" aria-labelledby="categoriesTitle">
        <div class="ui-card-head"><div><h2 class="ui-card-title" id="categoriesTitle">Existing categories</h2><p class="ui-card-sub">Deactivate a category to hide it from new requests. Previous session records are retained.</p></div></div>
        <x-ui.filter-bar :action="route('concerns.manage')" aria-label="Category filters">
            <x-ui.field label="Search" for="concernSearch"><input type="search" id="concernSearch" data-control="search" placeholder="Search categories" autocomplete="off"></x-ui.field>
            <x-ui.field label="Status" for="categoryStatus"><select id="categoryStatus" name="status" data-control="select"><option value="">All categories</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></x-ui.field>
            <x-slot:buttons><button class="btn-primary" type="submit">Apply filter</button></x-slot:buttons>
        </x-ui.filter-bar>
        <x-ui.filter-chips :chips="$chips" :reset-href="route('concerns.manage')" />
        @if($categories->isNotEmpty())
        <x-ui.table :stack="true">
            <thead><tr><th>Category</th><th>Description</th><th>Status</th><th>Edit</th></tr></thead>
            <tbody>
            @foreach($categories as $category)
                <tr data-concern-search="{{ mb_strtolower(trim($category->concern_name.' '.$category->description)) }}">
                    <td data-label="Category"><strong>{{ $category->concern_name }}</strong></td>
                    <td data-label="Description">@if($category->description){{ \Illuminate\Support\Str::limit($category->description, 100) }}@else<span class="ui-muted-dash" aria-label="No description">—</span>@endif</td>
                    <td data-label="Status"><x-ui.badge :tone="$category->is_active ? 'green' : 'neutral'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                    <td data-label="Edit" class="ui-actions concern-edit-cell">
                        <details class="concern-edit"><summary class="ui-icon-btn" aria-label="Edit category" data-tooltip="Edit category"><x-ui-icon name="edit" /></summary>
                            <form method="POST" action="{{ route('concerns.update',$category) }}">@csrf @method('PATCH')
                                <div class="concern-fields">
                                    <x-ui.field label="Category name" for="concernName{{ $category->id }}"><input id="concernName{{ $category->id }}" name="concern_name" required maxlength="100" value="{{ $category->concern_name }}"></x-ui.field>
                                    <x-ui.field label="Status" for="concernStatus{{ $category->id }}"><select id="concernStatus{{ $category->id }}" name="is_active" data-control="select"><option value="1" @selected($category->is_active)>Active</option><option value="0" @selected(!$category->is_active)>Inactive</option></select></x-ui.field>
                                    <x-ui.field label="Description" for="concernDescription{{ $category->id }}"><textarea id="concernDescription{{ $category->id }}" name="description" maxlength="500" rows="2">{{ $category->description }}</textarea></x-ui.field>
                                </div>
                                <button class="btn-primary" type="submit" style="white-space:nowrap">Save changes</button>
                            </form>
                        </details>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </x-ui.table>
        <p class="ui-list-sub" id="concernNoMatch" hidden>No categories on this page match your search.</p>
        @else
        <x-ui.empty title="No categories found" text="Try another status filter or add a category." />
        @endif
        {{ $categories->links('vendor.pagination.compass', ['noun' => 'categories']) }}
    </x-ui.card>
</div>
<style>
.concern-management .concern-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:16px 0}
.concern-management dialog .concern-fields{margin:0;grid-template-columns:minmax(0,1fr)}
.compass-ui .concern-management .ui-table td{padding-top:10px;padding-bottom:10px;height:auto}
.compass-ui .concern-management .ui-table :is(th,td):last-child{text-align:right}
.concern-management tr[hidden]{display:none}
.concern-management .ui-field textarea{padding:12px;font-size:15px;border:1px solid var(--compass-border,#e5e7eb);border-radius:12px;background:#fff;resize:vertical;font-family:inherit}
.concern-management .ui-field textarea:focus-visible{outline:none;box-shadow:var(--ring,0 0 0 3px rgba(78, 154, 42,.25))}
.concern-management .ui-field{min-width:0}
.concern-management .concern-edit summary{cursor:pointer;list-style:none;display:inline-flex}
.concern-management .concern-edit summary::-webkit-details-marker{display:none}
.concern-management .concern-edit[open]{text-align:left;white-space:normal;min-width:min(100%,320px)}
.concern-management .concern-edit[open] summary{margin-bottom:8px}
@media(min-width:769px){.compass-ui .concern-management .ui-table table td.concern-edit-cell{vertical-align:top}}
</style>
<script>
(() => {
    document.querySelectorAll('dialog[data-open-on-load]').forEach((d) => (window.CompassDialogs?.open ? window.CompassDialogs.open(d) : d.showModal()));
    const search = document.getElementById('concernSearch');
    if (!search) return;
    const rows = [...document.querySelectorAll('tr[data-concern-search]')];
    const none = document.getElementById('concernNoMatch');
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach((row) => { row.hidden = q !== '' && !row.dataset.concernSearch.includes(q); if (!row.hidden) shown++; });
        if (none) none.hidden = shown > 0;
    });
})();
</script>
@endsection
