<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#193b30}h1{font-size:20px}h2{font-size:14px;margin-top:20px}table{width:100%;border-collapse:collapse}th,td{padding:7px;border-bottom:1px solid #dce6df;text-align:left}th{background:#edf8f2}thead{display:table-header-group}tr{page-break-inside:avoid}</style>
</head>
<body>
<h1>COMPASS - My Service Report</h1>
<p>{{ $roleReport['from'] }} to {{ $roleReport['to'] }} / Philippine Time (Asia/Manila)
</p>
<p>Case status: {{ request('case_status') ?: 'All' }} / Category: {{ request('concern_id') ? \App\Models\ConcernCategory::find(request('concern_id'))?->concern_name : 'All' }}</p>
<h2>Summary</h2>
<table>
@foreach($roleReport['summary'] as $label=>$value)
<tr>
<th>{{ $label }}</th>
<td>{{ $value }}</td>
</tr>
@endforeach</table>
@foreach($roleReport['tables'] as $table)
<h2>{{ $table['title'] }}</h2>
<table>
<thead>
<tr>
@foreach($table['columns'] as $column)
<th>{{ $column }}</th>
@endforeach</tr>
</thead>
<tbody>
@forelse($table['records'] as $record)
<tr>
@foreach(($table['format'])($record) as $value)
<td>{{ $value }}</td>
@endforeach</tr>
@empty<tr>
<td colspan="{{ count($table['columns']) }}">No records match the selected filters.</td>
</tr>
@endforelse</tbody>
</table>
@endforeach</body>
</html>
