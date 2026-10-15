@extends('layouts.app')
@section('title','Emergency Alerts - COMPASS')
@section('content')
<link rel="stylesheet" href="{{ asset('css/moderator-operations.css') }}?v={{ filemtime(public_path('css/moderator-operations.css')) }}">
@include('moderator.partials.emergency-content')
<meta id="moderatorEmergencyConfig" content="{{ json_encode(['stats' => route('moderator.emergency.stats'), 'signature' => $emergencySignature]) }}">
<script src="{{ asset('js/moderator-emergency.js') }}?v={{ filemtime(public_path('js/moderator-emergency.js')) }}" defer></script>
@endsection
