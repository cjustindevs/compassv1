@extends('layouts.app')
@section('content')
@include('partials.referral-ui-styles')
<div class="referral-ui"><h1>Areas of concern</h1><p class="ru-muted">Manage choices for new support requests. Existing session history is retained.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<form method="GET" class="ru-actions"><label>Status<select name="status"><option value="">All categories</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></label><button type="submit">Filter</button></form>
<section class="ru-card"><h2>Add concern</h2><form method="POST" action="{{ route('concerns.store') }}">@csrf<label>Name<input name="concern_name" required maxlength="100" value="{{ old('concern_name') }}"></label><label>Description<textarea name="description" maxlength="500">{{ old('description') }}</textarea></label><input type="hidden" name="is_active" value="1"><button type="submit">Add category</button></form></section>
@forelse($categories as $category)
<section class="ru-card"><h2>{{ $category->concern_name }} <span class="ru-muted">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></h2><form method="POST" action="{{ route('concerns.update',$category) }}">@csrf @method('PATCH')<label>Name<input name="concern_name" required maxlength="100" value="{{ $category->concern_name }}"></label><label>Description<textarea name="description" maxlength="500">{{ $category->description }}</textarea></label><label>Status<select name="is_active"><option value="1" @selected($category->is_active)>Active</option><option value="0" @selected(!$category->is_active)>Inactive</option></select></label><button type="submit">Save changes</button></form></section>
@empty<p>No categories match this filter.</p>@endforelse
{{ $categories->links() }}</div>
@endsection
