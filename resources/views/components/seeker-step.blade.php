@props(['title', 'step' => null])
<style>
.main-content:has(.seeker-page) { background:#F8FBF9; }
.seeker-page { padding:24px 32px 80px; color:#163B2D; font-family:Inter,sans-serif; }
.seeker-heading { display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px; }
.seeker-heading h1 {font-size:24px;font-weight:700;line-height:1.3;margin:0;}
.seeker-heading p {font-size:13px;color:#6b7280;margin-top:4px;}
.seeker-card {background:#fff;border:1px solid #e2e8e5;border-radius:20px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,.02);}
.seeker-card p {line-height:1.7;font-size:14px;color:#64748b;}
.seeker-toolbar {display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px;}
.seeker-toolbar h2,.seeker-card h2 {font-size:16px;font-weight:600;}
.seeker-button {display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 18px;border-radius:10px;background:#04a052;color:white;font-weight:600;font-size:13px;border:1px solid #04a052;text-decoration:none;cursor:pointer;}
.seeker-button.secondary {background:white;color:#027039;border-color:#cce8d9;}
.seeker-button.danger {background:white;color:#b91c1c;border-color:#fecaca;}
.seeker-table-wrap {overflow-x:auto;}
.seeker-table {width:100%;border-collapse:collapse;text-align:left;font-size:13px;}
.seeker-table th {background:#f8faf9;color:#6b7280;font-size:11px;text-transform:uppercase;letter-spacing:.04em;padding:14px 16px;}
.seeker-table td {padding:18px 16px;border-bottom:1px solid #edf2ef;vertical-align:middle;}
.seeker-badge {display:inline-flex;padding:5px 10px;border-radius:20px;background:#eaf8f0;color:#027039;font-size:12px;font-weight:600;}
.seeker-badge.muted {background:#f3f4f6;color:#64748b;}
.seeker-empty {padding:40px 16px;text-align:center;color:#64748b;}
.seeker-empty i {display:block;font-size:28px;color:#04a052;margin-bottom:14px;}
.seeker-notice {padding:16px;background:#eaf8f0;border:1px solid #d4eee0;border-radius:12px;margin-bottom:24px;}
.seeker-record {padding:20px;border:1px solid #e5e7eb;border-radius:14px;margin-top:16px;}
.seeker-actions {display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;}
@media(max-width:768px){.seeker-page{padding:20px 16px 100px}.seeker-card{padding:18px}.seeker-heading h1{font-size:21px}.seeker-heading time{display:none}}
</style>
<section class="seeker-page">
<header class="seeker-heading"><div><h1>{{ $title }}</h1><p>{{ $step ?? 'Manage your support and preferences in one place.' }}</p></div><time class="text-xs text-gray-400">{{ now('Asia/Manila')->format('M d, Y') }}</time></header>
@if(session('success'))<div role="status" class="seeker-notice">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="seeker-notice">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="seeker-card">{{ $slot }}</div>
</section>
