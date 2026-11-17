@props(['status'])
@php
    // One colour per status, used everywhere a session or case status is shown.
    $tones = [
        'completed' => 'green', 'resolved' => 'green', 'published' => 'green',
        'evaluated' => 'blue', 'scheduled' => 'blue', 'reviewed' => 'blue',
        'active' => 'amber', 'in_progress' => 'amber', 'ongoing' => 'amber', 'pending' => 'amber', 'waiting' => 'amber', 'queued' => 'amber',
        'no_show' => 'orange',
        'cancelled' => 'red', 'declined' => 'red', 'rejected' => 'red',
    ];
    $key = strtolower((string) $status);
@endphp
<x-ui.badge :tone="$tones[$key] ?? 'neutral'" {{ $attributes }}>{{ ucwords(str_replace('_', ' ', $key)) }}</x-ui.badge>
