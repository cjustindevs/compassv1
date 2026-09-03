<!-- ── PWA Meta Tags ── -->
<meta name="theme-color" content="#04A052">
<meta name="application-name" content="COMPASS">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="COMPASS">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/images/compass/apple-touch-icon.png">

<!-- Favicons: multi-size BMP ICO + PNG fallbacks for every browser -->
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="/icons/icon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32x32.png">
<link rel="icon" type="image/png" sizes="48x48" href="/icons/icon-48x48.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">

<!-- Install / home-screen icons -->
<link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="/images/compass/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192x192.png">

<!-- Microsoft Tiles -->
<meta name="msapplication-TileColor" content="#04A052">
<meta name="msapplication-TileImage" content="/icons/icon-144x144.png">

<!-- Open Graph / social preview (uses the 512 app icon) -->
<meta property="og:title" content="COMPASS - Peer Support System">
<meta property="og:description" content="Competency Oversight, Monitoring, Peer Assistance, Support, and Supervision">
<meta property="og:image" content="/icons/icon-512x512.png">
<meta property="og:image:width" content="512">
<meta property="og:image:height" content="512">
<meta property="og:type" content="website">
<meta property="og:site_name" content="COMPASS">
<meta property="og:url" content="{{ url('/') }}">

<meta name="theme-prefs" content="{{ json_encode($themePrefs ?? ['high_contrast' => false, 'reduced_motion' => false, 'font_size' => 'medium']) }}">
