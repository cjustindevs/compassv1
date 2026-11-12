@extends('layouts.app')
@section('title', 'Areas of concern - COMPASS')
@section('content')
@php
    $chips = \App\Support\FilterChips::from(request(), ['status' => 'Status']);
@endphp
<div class="concern-management">
    <x-ui.page-header title="Areas of concern" description="Manage the categories available when a seeker requests support.">
        <x-slot:actions><x-ui.badge tone="green">{{ $categories->total() }} categories</x-ui.badge></x-slot:actions>
    </x-ui.page-header>
    @if(session('success'))<div class="ui-card" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="ui-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <x-ui.card class="concern-add" aria-labelledby="addConcernTitle">
        <div class="ui-card-head"><div><h2 class="ui-card-title" id="addConcernTitle">Add a category</h2><p class="ui-card-sub">New categories are active immediately. Keep names short and easy to understand.</p></div></div>
        <form method="POST" action="{{ route('concerns.store') }}">@csrf
            <div class="concern-fields">
                <x-ui.field label="Category name" for="newConcernName"><input id="newConcernName" name="concern_name" required maxlength="100" placeholder="Enter a category name" value="{{ old('concern_name') }}"></x-ui.field>
                <x-ui.field label="Description (optional)" for="newConcernDescription"><textarea id="newConcernDescription" name="description" maxlength="500" rows="2" placeholder="Briefly describe this concern">{{ old('description') }}</textarea></x-ui.field>
            </div>
            <input type="hidden" name="is_active" value="1"><button class="btn-primary" type="submit">Add category</button>
        </form>
    </x-ui.card>

    <x-ui.card class="concern-list" aria-labelledby="categoriesTitle">
        <div class="ui-card-head"><div><h2 class="ui-card-title" id="categoriesTitle">Existing categories</h2><p class="ui-card-sub">Deactivate a category to hide it from new requests. Previous session records are retained.</p></div></div>
        <x-ui.filter-bar :action="route('concerns.manage')" aria-label="Category filters">
            <x-ui.field label="Status" for="categoryStatus"><select id="categoryStatus" name="status" data-control="select"><option value="">All categories</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></x-ui.field>
            <x-slot:buttons><button class="btn-primary" type="submit">Apply filter</button></x-slot:buttons>
        </x-ui.filter-bar>
        <x-ui.filter-chips :chips="$chips" :reset-href="route('concerns.manage')" />
        @if($categories->isNotEmpty())
        <x-ui.table :stack="true">
            <thead><tr><th>Category</th><th>Description</th><th>Status</th><th>Edit</th></tr></thead>
            <tbody>
            @foreach($categories as $category)
                <tr>
                    <td data-label="Category"><strong>{{ $category->concern_name }}</strong></td>
                    <td data-label="Description">{{ $category->description ? \Illuminate\Support\Str::limit($category->description, 100) : 'No description' }}</td>
                    <td data-label="Status"><x-ui.badge :tone="$category->is_active ? 'green' : 'neutral'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                    <td data-label="Edit" class="ui-actions concern-edit-cell">
                        <details class="concern-edit"><summary class="btn-secondary">Edit category</summary>
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
        @else
        <x-ui.empty title="No categories found" text="Try another status filter or add a category." />
        @endif
        {{ $categories->links('vendor.pagination.compass', ['noun' => 'categories']) }}
    </x-ui.card>
</div>
<style>
.concern-management .concern-add{margin-bottom:24px}
.concern-management .concern-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:16px 0}
.concern-management .ui-field textarea{padding:12px;font-size:15px;border:1px solid var(--compass-border,#e5e7eb);border-radius:12px;background:#fff;resize:vertical;font-family:inherit}
.concern-management .ui-field textarea:focus-visible{outline:none;box-shadow:var(--ring,0 0 0 3px rgba(4,160,82,.25))}
.concern-management .ui-field{min-width:0}
.concern-management .concern-edit summary{cursor:pointer;list-style:none;display:inline-flex}
.concern-management .concern-edit summary::-webkit-details-marker{display:none}
.concern-management .concern-edit[open]{text-align:left;white-space:normal;min-width:min(100%,320px)}
.concern-management .concern-edit[open] summary{margin-bottom:8px}
@media(min-width:769px){.compass-ui .concern-management .ui-table table td.concern-edit-cell{vertical-align:top}}
</style>
@endsection
