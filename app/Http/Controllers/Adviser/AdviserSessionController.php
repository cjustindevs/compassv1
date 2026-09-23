<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Services\AdviserScope;
use App\Services\SupportAudit;
use Illuminate\Support\Facades\DB;

class AdviserSessionController extends Controller
{
    public function show($id)
    {
        app(AdviserScope::class)->actor();
        $session = Session::findOrFail($id);
        app(AdviserScope::class)->session($session);
        $session->load(['helper', 'concern', 'report']);
        $revisions = $session->report ? DB::table('session_report_revisions')->where('report_id', $session->report->id)->latest('id')->get() : collect();
        SupportAudit::record('session_documentation_viewed', $session, ['purpose' => 'adviser_supervision']);

        return view('adviser.session', compact('session', 'revisions'));
    }
}
