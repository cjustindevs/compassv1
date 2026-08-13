<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;

class HelperNotesController extends Controller
{
    /**
     * Session Notes entry point — jump to the most recent session's notes page.
     */
    public function index()
    {
        $helper = Auth::user()->helper;

        $session = Session::where('helper_id', $helper->id)
            ->whereIn('session_status', ['active', 'helper_assigned', 'completed'])
            ->orderByDesc('end_time')
            ->orderByDesc('created_date')
            ->first();

        if ($session) {
            return redirect()->route('helper.session.notes', ['id' => $session->id]);
        }

        return redirect()->route('helper.cases')
            ->with('info', 'You have no session to document yet.');
    }
}