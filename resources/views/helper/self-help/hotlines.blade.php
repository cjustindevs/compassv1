@extends('layouts.helper')

@section('title', 'Emergency Resources')
@section('heading', 'Emergency Resources')
@section('subheading', 'Immediate support and hotlines if you need someone to talk to.')

@section('content')
    <div class="mb-6 p-4 rounded-xl" style="background:#FEF2F2;border:1px solid #FECACA;">
        <p class="text-sm" style="color:var(--red-600);">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            If you are in immediate danger or a crisis, please contact your local emergency services right away.
        </p>
    </div>

    @forelse($hotlines as $resource)
        <div class="card mb-4" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:20px 24px;">
            <div>
                <h3 style="font-weight:700;color:var(--gray-800);">{{ $resource->agency_name }}</h3>
                @if($resource->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $resource->description }}</p>
                @endif
            </div>
            <a href="tel:{{ $resource->hotline }}" class="btn btn-outline-danger">
                <i class="fas fa-phone-alt mr-1"></i> {{ $resource->hotline }}
            </a>
        </div>
    @empty
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-phone-alt"></i>
                <p>No emergency resources are currently listed.</p>
            </div>
        </div>
    @endforelse

    <div class="mt-6 text-center">
        <a href="{{ route('helper.self-help') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Self-Care
        </a>
    </div>
@endsection
