<?php

// Local HTTP smoke check, using the explicit demo credentials from TestUsersSeeder.
// Cookies stay in memory; neither credentials nor HTML responses are logged.
$base = 'http://127.0.0.1:8000';
$accounts = [
    ['admin', 'admin@compass.local', 'Admin@123'],
    ['adviser', 'maria.santos@compass.local', 'Adviser@123'],
    ['helper', 'rina@compass.local', 'Helper@123'],
    ['seeker', 'SilentWillow52', 'Seeker@123'],
    ['moderator', 'moderator@compass.local', 'Moderator@123'],
    ['professional', 'anna.cruz@compass.local', 'Professional@123'],
];
$failed = false;
foreach ($accounts as [$role, $login, $password]) {
    $client = curl_init();
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20, CURLOPT_COOKIEFILE => '', CURLOPT_URL => $base . '/login']);
    $html = curl_exec($client);
    if (! is_string($html) || ! preg_match('/name="_token"\s+value="([^"]+)"/', $html, $match)) {
        fwrite(STDERR, "$role: login page unavailable\n"); $failed = true; curl_close($client); continue;
    }
    curl_setopt_array($client, [CURLOPT_URL => $base . '/login', CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['_token' => html_entity_decode($match[1]), 'email' => $login, 'password' => $password])]);
    curl_exec($client);
    $code = curl_getinfo($client, CURLINFO_RESPONSE_CODE);
    $path = parse_url(curl_getinfo($client, CURLINFO_EFFECTIVE_URL), PHP_URL_PATH);
    $ok = $code === 200 && $path === '/' . $role . '/dashboard';
    echo $role . ': ' . ($ok ? 'login and dashboard OK' : 'FAILED (HTTP ' . $code . ')') . PHP_EOL;
    $failed = $failed || ! $ok;
    curl_close($client);
}
exit($failed ? 1 : 0);
