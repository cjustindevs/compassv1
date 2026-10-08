@extends('layouts.app')
@section('title','Operations Reports - COMPASS')
@section('content')
<header class="flex flex-wrap justify-between items-start gap-3 mb-5"><div><h1 class="text-2xl font-bold">Operations Reports</h1><p class="text-sm text-gray-500 mt-1">Emergency coordination, queue activity and recorded operational actions.</p></div><a class="text-green-700 underline" href="{{ route('moderator.reports.export',request()->except(['cases_page','emergency_page','queue_page','actions_page'])) }}">Export filtered CSV</a></header>
<x-role-activity-report :report="$roleReport" />
@endsection
