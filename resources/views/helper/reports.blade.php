@extends('layouts.app')
@section('title','My Service Reports - COMPASS')
@section('content')
<header class="mb-5"><h1 class="text-2xl font-bold">My Service Reports</h1><p class="text-sm text-gray-500 mt-1">Your own assignments, duty dates, availability history and service outcomes.</p></header>
<x-role-activity-report :report="$roleReport" />
@endsection
