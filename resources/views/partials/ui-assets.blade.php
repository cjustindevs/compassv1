{{-- Common screen primitives. Keep after page CSS; print/email use their own assets. --}}
@once
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="compass-icon-sprite" content="{{ asset('images/compass-icons.svg') }}?v={{ filemtime(public_path('images/compass-icons.svg')) }}">
    <meta name="compass-display-preferences" content="{{ json_encode(['font_size' => auth()->user()?->font_size ?? 'medium', 'high_contrast' => (bool) auth()->user()?->high_contrast, 'reduced_motion' => (bool) auth()->user()?->reduced_motion]) }}">
    <link rel="stylesheet" href="{{ asset('css/compass-ui.css') }}?v={{ filemtime(public_path('css/compass-ui.css')) }}">
    <script src="{{ asset('js/compass-ui.js') }}?v={{ filemtime(public_path('js/compass-ui.js')) }}" defer></script>
@endonce
