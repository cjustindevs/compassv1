<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>COMPASS Performance Report</title>
<style>
@page { margin: 100px 36px 55px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #24352c; }
header { position: fixed; top: -80px; left: 0; right: 0; border-bottom: 2px solid #04a052; padding-bottom: 10px; }
header img { height: 30px; float: right; }
footer { position: fixed; bottom: -30px; font-size: 8px; }
h1 { font-size: 17px; margin: 0 0 5px; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { padding: 7px 4px; border-bottom: 1px solid #ddd; text-align: left; }
thead { display: table-header-group; } tr { page-break-inside: avoid; }
</style></head><body>
<header><img src="{{ public_path('images/compass/logo-wordmark.png') }}" alt="COMPASS"><h1>COMPASS Performance Report</h1><div>Generated {{ now()->format('F d, Y H:i') }}</div><div>{{ $startDate->format('M d, Y') }} – {{ $endDate->format('M d, Y') }}</div></header>
<footer>Divine Word College of Calapan · Project Dial-A-Friend</footer>
<p>Activity cohort reported in Asia/Manila. No data means the metric has no valid denominator.</p>
@foreach($metrics as $name=>$value)<span>{{ ucfirst(str_replace('_',' ',$name)) }}: {{ $value ?? 'No data' }} | </span>@endforeach
<table><thead><tr><th>Session</th><th>Helper</th><th>Concern</th><th>Risk</th><th>Status</th><th>Minutes</th><th>Rating / 5</th></tr></thead><tbody>
@forelse($sessions as $session)
<tr><td>{{ $session->reference_number }}</td><td>{{ $session->helper?->public_alias }}</td><td>{{ $session->concern?->concern_name }}</td><td>{{ ucfirst($session->risk_level) }}</td><td>{{ $session->status_label }}</td><td>{{ $session->duration }}</td><td>{{ \App\Services\AdviserAnalytics::rating($session->evaluation?->overall_score) }}</td></tr>
@empty<tr><td colspan="7">No sessions in this period.</td></tr>@endforelse
</tbody></table></body></html>
