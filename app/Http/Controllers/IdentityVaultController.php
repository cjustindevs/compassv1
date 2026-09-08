<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Services\IdentityVaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IdentityVaultController extends Controller
{
    public function form(Request $request, Referral $referral)
    {
        abort_unless($request->user()->role === 'seeker' && $request->user()->helpSeeker?->id === $referral->session->seeker_id, 403);
        abort_unless($referral->approved_at, 409, 'Adviser approval is required.');
        return response()->view('session.identity', compact('referral'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Referral $referral, IdentityVaultService $vault)
    {
        // Never flash this form's input into the operational session database.
        $rules = array_fill_keys(IdentityVaultService::FIELDS, ['nullable', 'string', 'max:500']);
        $rules['real_name'] = ['required', 'string', 'max:200'];
        $rules['phone_number'] = ['required', 'string', 'regex:/^[+0-9 ()-]{7,30}$/'];
        $rules['email'] = ['nullable', 'email', 'max:254'];
        $validator = Validator::make($request->only(IdentityVaultService::FIELDS), $rules);
        if ($validator->fails()) {
            return response()->json(['message' => 'Please check the identity fields.', 'errors' => $validator->errors()], 422);
        }
        $vault->storeForReferral($referral, $validator->validated());
        if ($referral->adviser?->user_account_id) {
            \App\Models\Notification::create([
                'user_account_id' => $referral->adviser->user_account_id, 'title' => 'Identity ready for release',
                'message' => 'Referral #' . $referral->id . ' has identity information ready for authorized release.',
                'notification_type' => 'referral', 'link' => '/adviser/referral/' . $referral->id,
            ]);
        }
        return response()->json(['message' => 'Your identity was securely stored. Your adviser can now authorize its release to the assigned professional.']);
    }

    public function release(Referral $referral, IdentityVaultService $vault)
    {
        $vault->releaseForReferral($referral);
        return back()->with('success', 'Identity released to the assigned professional.');
    }

    public function show(Referral $referral, IdentityVaultService $vault)
    {
        $identity = $vault->readForReferral($referral);
        return response()->view('professional.identity', compact('identity', 'referral'))
            ->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache')->header('Referrer-Policy', 'no-referrer');
    }

    public function acknowledge(Referral $referral, IdentityVaultService $vault)
    {
        $vault->acknowledge($referral);
        return back()->with('success', 'Receipt acknowledged.');
    }

    public function emergency(Request $request, \App\Models\Session $session, IdentityVaultService $vault)
    {
        $reason = $request->input('reason');
        abort_unless(is_string($reason) && mb_strlen($reason) <= 1000, 422, 'A reason of 20–1000 characters is required.');
        $identity = $vault->emergencyRead($session, $reason);
        return response()->view('professional.emergency-identity', compact('identity', 'session'))
            ->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache')->header('Referrer-Policy', 'no-referrer');
    }

    public function reviewEmergency(Request $request, \App\Models\Session $session, IdentityVaultService $vault)
    {
        $notes = $request->input('notes');
        abort_unless(is_string($notes) && mb_strlen(trim($notes)) >= 20 && mb_strlen($notes) <= 1000, 422);
        $vault->reviewEmergency($session, $notes);
        return back()->with('success', 'Emergency access review recorded.');
    }
}
