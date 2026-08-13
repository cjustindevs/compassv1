<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;

class HelperVoiceController extends Controller
{
    /**
     * Voice Call entry point — jump to the most recent session's voice room.
     */
    public function index()
    {
        $helper = Auth::user()->helper;

        $session = Session::where('helper_id', $helper->id)
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->orderByDesc('start_time')
            ->orderByDesc('created_date')
            ->first();

        if ($session) {
            return redirect()->route('helper.session.voice', ['id' => $session->id]);
        }

        return redirect()->route('helper.cases')
            ->with('info', 'You have no active session to call. Accept a case first.');
    }
}