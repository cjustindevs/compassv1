<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Services\RoleActivityReport;
use Illuminate\Http\Request;

class HelperReportController extends Controller
{
    public function index(Request $request, RoleActivityReport $reports)
    {
        abort_unless($request->user()?->role === 'helper', 403);

        return view('helper.reports', ['roleReport' => $reports->report($request)]);
    }
}
