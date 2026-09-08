<?php

namespace App\Http\Controllers;

use App\Models\ConsentRecord;
use App\Models\HelpSeeker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SeekerOnboardingController extends Controller
{
    public function create()
    {
        return view('auth.pseudonymous-register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'age' => 'required|integer|min:13|max:99',
            'gender' => 'required|in:male,female,non-binary,prefer-not-to-say',
            'preferred_language' => 'required|in:English,Tagalog,English/Tagalog',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (HelpSeeker::where('registration_ip', $request->ip())->where('created_at', '>', now()->subDay())->exists()) {
            return back()->withErrors(['age' => 'A recent registration was detected from this location. Please sign in or contact support.']);
        }

        $user = DB::transaction(function () use ($request, $data) {
            do {
                $alias = collect(['Calm', 'Brave', 'Kind', 'Gentle', 'Bright', 'Quiet'])->random()
                    .collect(['Fox', 'Deer', 'Eagle', 'Bear', 'Wolf', 'Owl'])->random().random_int(10, 9999);
            } while (HelpSeeker::where('generated_alias', $alias)->exists());

            $user = User::create([
                'name' => $alias, 'email' => strtolower($alias).'@compass.local',
                'password' => $data['password'], 'role' => 'seeker',
                'preferred_language' => $data['preferred_language'],
            ]);
            HelpSeeker::create([
                'user_account_id' => $user->id, 'generated_alias' => $alias,
                'pseudo_id' => 'PS-'.Str::upper(Str::random(12)),
                'age' => $data['age'], 'gender' => $data['gender'],
                'registration_ip' => $request->ip(), 'account_created' => now(),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('seeker.consent');
    }

    public function consent()
    {
        abort_unless(Auth::user()->helpSeeker, 403);
        return view('auth.seeker-consent');
    }

    public function accept(Request $request)
    {
        $request->validate([
            'agree_privacy' => 'accepted', 'agree_terms' => 'accepted',
            'agree_emergency' => 'accepted', 'agree_consent' => 'accepted',
        ]);
        $seeker = Auth::user()->helpSeeker;
        abort_unless($seeker, 403);
        DB::transaction(function () use ($seeker, $request) {
            foreach (['privacy_policy', 'informed_consent'] as $type) {
                ConsentRecord::create([
                    'seeker_id' => $seeker->id, 'document_type' => $type,
                    'consent_given' => true, 'consent_date' => now(),
                    'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'version' => '1.0',
                ]);
            }
            $seeker->update(['is_verified' => true, 'verified_at' => now()]);
        });
        return redirect()->route('request.screening');
    }
}
