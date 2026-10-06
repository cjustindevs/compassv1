@extends('layouts.app')
@section('title', 'Areas of concern - COMPASS')
@section('content')
@include('partials.management-ui-styles')
<div class="cm-page">
    <header class="cm-header"><div><h1>Areas of concern</h1><p class="cm-muted">Manage the categories available when a seeker requests support.</p></div><span class="cm-badge">{{ $categories->total() }} categories</span></header>
    @if(session('success'))<div class="cm-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="cm-alert cm-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="cm-grid">
        <section class="cm-card" aria-labelledby="addConcernTitle">
            <h2 id="addConcernTitle">Add a category</h2><p class="cm-help">New categories are active immediately. Keep names short and easy to understand.</p>
            <form method="POST" action="{{ route('concerns.store') }}">@csrf
                <div class="cm-field"><label for="newConcernName">Category name</label><input id="newConcernName" name="concern_name" required maxlength="100" placeholder="Enter a category name" value="{{ old('concern_name') }}"></div>
                <div class="cm-field"><label for="newConcernDescription">Description <span class="cm-muted">(optional)</span></label><textarea id="newConcernDescription" name="description" maxlength="500" rows="3" placeholder="Briefly describe this concern">{{ old('description') }}</textarea></div>
                <input type="hidden" name="is_active" value="1"><button class="cm-button cm-primary" type="submit">Add category</button>
            </form>
        </section>
        <section class="cm-card" aria-labelledby="categoriesTitle">
            <h2 id="categoriesTitle">Existing categories</h2><p class="cm-muted">Deactivate a category to hide it from new requests. Previous session records are retained.</p>
            <form method="GET" class="cm-toolbar"><div><label for="categoryStatus">Status</label><select id="categoryStatus" name="status"><option value="">All categories</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></div><button class="cm-button" type="submit">Apply filter</button>@if(request('status'))<a class="cm-button" href="{{ route('concerns.manage') }}">Clear</a>@endif</form>
            @forelse($categories as $category)
            <details class="cm-entry"><summary><div><h2>{{ $category->concern_name }}</h2><span class="cm-badge {{ $category->is_active ? '' : 'off' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></div><span class="cm-edit-label">Edit category</span></summary>
                <form method="POST" action="{{ route('concerns.update',$category) }}">@csrf @method('PATCH')
                    <div class="cm-form-grid"><div class="cm-field"><label for="concernName{{ $category->id }}">Category name</label><input id="concernName{{ $category->id }}" name="concern_name" required maxlength="100" value="{{ $category->concern_name }}"></div><div class="cm-field"><label for="concernStatus{{ $category->id }}">Status</label><select id="concernStatus{{ $category->id }}" name="is_active"><option value="1" @selected($category->is_active)>Active</option><option value="0" @selected(!$category->is_active)>Inactive</option></select></div></div>
                    <div class="cm-field"><label for="concernDescription{{ $category->id }}">Description</label><textarea id="concernDescription{{ $category->id }}" name="description" maxlength="500" rows="3">{{ $category->description }}</textarea></div><button class="cm-button cm-primary" type="submit">Save changes</button>
                </form>
            </details>
            @empty<div class="cm-empty"><h2>No categories found</h2><p>Try another status filter or add a category.</p></div>@endforelse
            <div class="cm-pagination">{{ $categories->links() }}</div>
        </section>
    </div>
</div>
@endsection
