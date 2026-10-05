@extends('layouts.app')
@section('content')
<x-seeker-step title="What would you like support with?" step="Step 2 of 3: Concern">
<form action="{{ route('request.concern.process') }}" method="POST" class="space-y-5">@csrf
<x-input-label for="concern_id" value="Area of concern"/><select id="concern_id" name="concern_id" required class="w-full rounded-lg border-gray-300"><option value="">Select a concern</option>@foreach($concerns as $concern)<option value="{{ $concern->id }}" @selected(old('concern_id')==$concern->id)>{{ $concern->concern_name }}</option>@endforeach</select>
<x-input-label for="description" value="Anything you would like to add? (optional)"/><p class="text-sm text-gray-500">Please avoid names, phone numbers or identifying details.</p><textarea id="description" name="description" maxlength="500" rows="3" class="w-full rounded-lg border-gray-300">{{ old('description') }}</textarea><x-primary-button>Continue</x-primary-button></form></x-seeker-step>
<script>
document.addEventListener('DOMContentLoaded',()=>{const select=document.getElementById('concern_id'),description=document.getElementById('description');function update(){const other=['other','others'].includes(select.selectedOptions[0]?.textContent.trim().toLowerCase());description.required=other;document.querySelector('label[for="description"]').textContent=other?'Please describe your concern (required)':'Anything you would like to add? (optional)';}select.addEventListener('change',update);update();});
</script>
@endsection
