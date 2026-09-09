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
    public function create(Request $request)
    {
        if (!$request->session()->has('registration_alias')) {
            $request->session()->put('registration_alias', $this->newAlias());
        }
        return view('auth.pseudonymous-register');
    }

    private function newAlias(): string
    {
        do {
            $alias = collect(['Calm', 'Brave', 'Kind', 'Gentle', 'Bright', 'Quiet'])->random()
                .collect(['Fox', 'Deer', 'Eagle', 'Bear', 'Wolf', 'Owl'])->random().random_int(10, 9999);
        } while (HelpSeeker::where('generated_alias', $alias)->exists()
            || User::where('email', strtolower($alias).'@compass.local')->exists()
            || $alias === session('registration_alias'));
        return $alias;
    }

    public function shuffleAlias(Request $request)
    {
        $alias = $this->newAlias();
        $request->session()->put('registration_alias', $alias);
        return response()->json(['alias' => $alias]);
    }

    public function store(Request $request)
    {
        if ($request->session()->get('registration_verified_until', 0) <= now()->timestamp) {
            return back()->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['email' => 'Please verify your email before creating an account.']);
        }
        $data = $request->validate([
            'alias' => ['required', 'string', 'in:'.$request->session()->get('registration_alias'), 'unique:help_seekers,generated_alias'],
            'age' => 'required|integer|min:13|max:99',
            'gender' => 'required|in:male,female,non-binary,prefer-not-to-say',
            'preferred_language' => 'required|in:English,Tagalog,English/Tagalog',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = DB::transaction(function () use ($request, $data) {
            $alias = $data['alias'];

            $user = User::create([
                'name' => $alias, 'email' => strtolower($alias).'@compass.local',
                'password' => $data['password'], 'role' => 'seeker',
                // Older pending sessions stored only the fixed ten-minute expiry.
                'email_verified_at' => \Illuminate\Support\Carbon::createFromTimestamp(
                    $request->session()->get('registration_verified_at', $request->session()->get('registration_verified_until') - 600)
                ),
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

        $request->session()->forget(['registration_otp', 'registration_verified_until', 'registration_verified_at', 'registration_alias']);
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
