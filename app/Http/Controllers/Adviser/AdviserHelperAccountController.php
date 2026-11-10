<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\User;
use App\Services\AdviserScope;
use App\Services\StaffMailService;
use App\Services\SupportAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * QA: Advisers create accounts for their own Helpers, with proof of eligibility recorded at
 * creation. Uses the same three attestations + written evidence as the existing verify action,
 * so a created Helper is immediately verified, linked to this Adviser, and told how to sign in.
 */
class AdviserHelperAccountController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()?->role === 'adviser' && $request->user()?->is_active, 403);

        return view('adviser.helper-create', ['adviser' => app(AdviserScope::class)->actor()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'adviser' && $request->user()?->is_active, 403);
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:255',
            'currently_enrolled' => 'accepted',
            'recognized_member' => 'accepted',
            'training_completed' => 'accepted',
            'qualification_evidence' => 'required|string|min:10|max:2000',
        ], [
            'currently_enrolled.accepted' => 'Confirm the student is currently enrolled.',
            'recognized_member.accepted' => 'Confirm the student is a recognized peer-support member.',
            'training_completed.accepted' => 'Confirm the student completed peer-support training.',
            'qualification_evidence.required' => 'Describe the record you checked (for example the student ID and training certificate).',
        ]);
        $email = Str::lower(trim($data['email']));
        if (User::whereRaw('LOWER(email) = ?', [$email])->exists() || Helper::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already belongs to an account. Ask the administrator to link it instead.']);
        }

        $adviser = app(AdviserScope::class)->actor();
        $helper = DB::transaction(function () use ($data, $email, $adviser, $request) {
            $user = User::create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $email,
                // A random password nobody knows; the Helper sets their own through the emailed link.
                'password' => Str::password(32),
                'role' => 'helper',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $helper = Helper::create([
                'user_account_id' => $user->id, 'adviser_id' => $adviser->id,
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $email,
                'verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $request->user()->id,
                'training_verified' => true, 'qualification_evidence' => $data['qualification_evidence'],
            ]);
            // The Helper model records the Adviser assignment history itself when adviser_id is set.
            SupportAudit::record('helper_account_created_by_adviser', $helper, ['adviser_id' => $adviser->id, 'verifier_id' => $request->user()->id]);
            Notification::create(['user_account_id' => $user->id, 'notification_type' => 'system', 'title' => 'Welcome to COMPASS',
                'message' => 'Your Helper account was created and verified by '.$adviser->full_name.'. Complete a readiness check when you are on duty to start taking sessions.',
                'link' => '/helper/dashboard']);

            return $helper;
        });

        // Let the Helper choose their own password. Mail failures never undo the account.
        $user = $helper->user;
        try {
            $token = Password::broker()->createToken($user);
            app(StaffMailService::class)->enqueue($user, 'account-created-'.$user->id, 'COMPASS - Your Helper account',
                'Your Adviser '.$adviser->full_name.' created your COMPASS Helper account. Set your password here: '
                .url(route('password.reset', ['token' => $token, 'email' => $user->email], false)));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('adviser.helper.show', $helper->id)
            ->with('success', 'Helper account created and verified. '.$helper->first_name.' was emailed a link to set their password.');
    }
}
