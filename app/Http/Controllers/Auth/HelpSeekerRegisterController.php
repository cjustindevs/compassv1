<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\HelpSeeker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class HelpSeekerRegisterController extends Controller
{
    /**
     * Show registration form
     */
    public function showRegisterForm()
    {
        return view('auth.seeker-register');
    }

    /**
     * Generate pseudonymous alias
     */
    public function generateAlias()
{
    // Filipino nicknames / aliases
    $filipinoNames = [
        // Common Filipino nicknames
        'Inday', 'Rene', 'Boyet', 'Baby', 'Nene', 'Totoy', 'Ineng', 'Mang',
        'Kuya', 'Ate', 'Manong', 'Manang', 'Bading', 'Bong', 'Jun', 'Jun-Jun',
        'Biboy', 'Buboy', 'Gigi', 'Dodong', 'Indang', 'Kikay', 'Lolit', 'May-May',
        'Nonoy', 'Onyok', 'Peping', 'Pol', 'Rudy', 'Tata', 'Toto', 'Ute',
        'Yolly', 'Angge', 'Bebeng', 'Celing', 'Ding', 'Elang', 'Fely', 'Goring',
        'Huling', 'Iday', 'Jong', 'Karing', 'Lando', 'Meding', 'Nanding', 'Oring',
        'Pacing', 'Quinito', 'Rosing', 'Susing', 'Turing', 'Ursing', 'Viring',
        'Wiling', 'Yping', 'Zosimo', 'Alonzo', 'Benjie', 'Caloy', 'Dindo', 'Efren',
        
        // Nature-inspired Filipino names
        'Amihan', 'Habagat', 'Marikit', 'Ligaya', 'Tala', 'Bituin', 'Dalisay',
        'Gumamela', 'Jasmin', 'Kamille', 'Lily', 'Maya', 'Rosa', 'Sampaguita',
        'Yasmin', 'Adarna', 'Bulan', 'Gabi', 'Hari', 'Lakan', 'Mutya', 'Sultan',
        
        // Short and cute
        'Andoy', 'Bimbo', 'Cris', 'Denden', 'Eboy', 'Gie', 'Jobo', 'Kaye',
        'Leny', 'Mimay', 'Nilo', 'Obet', 'Pong', 'Remy', 'Siony', 'Tess',
        'Unyol', 'Vic', 'Wendy', 'Xander', 'Yumi', 'Zeny',
        
        // Unique Filipino names
        'Almira', 'Amor', 'Apol', 'Ariel', 'Armando', 'Belen', 'Bong', 'Cecilia',
        'Conching', 'Cristy', 'Dalia', 'Dante', 'Dina', 'Edith', 'Edna', 'Elena',
        'Elisa', 'Emilio', 'Ester', 'Evelyn', 'Fe', 'Felix', 'Flor', 'Gina',
        'Gloria', 'Guillermo', 'Helen', 'Irene', 'Isabel', 'Jose', 'Josie',
        'Leonor', 'Leticia', 'Lilia', 'Lina', 'Lourdes', 'Luz', 'Manuel',
        'Margarita', 'Maria', 'Mario', 'Marissa', 'Martha', 'Milagros',
        'Narciso', 'Nelson', 'Nieves', 'Nora', 'Olivia', 'Oscar', 'Pablo',
        'Pamela', 'Patricia', 'Pilar', 'Ramon', 'Rebecca', 'Rita', 'Ruben',
        'Rufina', 'Salvacion', 'Sam', 'Sandra', 'Serafin', 'Sofia', 'Teresa',
        'Trinidad', 'Verna', 'Vicente', 'Violeta', 'Virginia', 'Wilma', 'Zenaida'
    ];

    // Randomly pick a name
    $alias = $filipinoNames[array_rand($filipinoNames)];
    
    // Add a random number if the name is taken (like "Inday42")
    $counter = 1;
    while (DB::table('help_seekers')->where('generated_alias', $alias)->exists()) {
        $alias = $filipinoNames[array_rand($filipinoNames)] . $counter;
        $counter++;
        // Safety: if counter gets too high, just use a random number
        if ($counter > 100) {
            $alias = $filipinoNames[array_rand($filipinoNames)] . rand(10, 999);
            break;
        }
    }

    return response()->json([
        'success' => true,
        'alias' => $alias
    ]);
}

    /**
     * Complete registration
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'alias' => 'required|string|max:255|unique:help_seekers,generated_alias',
                'email' => 'required|email|unique:users,email',
                'age' => 'required|integer|min:13|max:99',
                'gender' => 'required|string|in:male,female,non-binary,prefer-not-to-say',
                'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
                'consent' => 'accepted',
                'verification_token' => 'required|string'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'One or more fields failed validation.',
                'errors' => $e->errors()
            ], 422);
        }

        // Verify session token matches
        $sessionToken = session('verification_token');
        $verifiedEmail = session('verified_email');

        if (!$sessionToken || $sessionToken !== $request->verification_token) {
            return response()->json([
                'success' => false,
                'message' => 'Verification token invalid. Please verify your email again.'
            ], 400);
        }

        if ($verifiedEmail !== $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email mismatch. Please verify your email again.'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Create user — users table columns: name, email, password, role, email_verified_at
            $user = User::create([
                'name' => $request->alias,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'seeker',
                'email_verified_at' => now()
            ]);

            // Create help seeker — FK user_account_id references users.id
            $helpSeeker = HelpSeeker::create([
                'user_account_id' => $user->id,
                'generated_alias' => $request->alias,
                'age' => $request->age,
                'gender' => $request->gender,
                'account_created' => now()
            ]);

            // Create consent record — FK seeker_id references help_seekers.id
            ConsentRecord::create([
                'seeker_id' => $helpSeeker->id,
                'document_type' => 'informed_consent',
                'consent_given' => true,
                'consent_date' => now()
            ]);

            // Clear session data
            session()->forget([
                'otp', 'otp_email', 'otp_expires_at',
                'otp_attempts', 'verification_token', 'verified_email'
            ]);

            DB::commit();

            Log::info('New help seeker registered', [
                'user_id' => $user->id,
                'seeker_id' => $helpSeeker->id,
                'email' => $request->email,
                'alias' => $request->alias
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully!',
                'alias' => $request->alias,
                'redirect' => route('login')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Seeker registration failed', [
                'email' => $request->email ?? null,
                'alias' => $request->alias ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Registration failed: ' . $e->getMessage()
                    : 'Registration failed. Please try again.'
            ], 500);
        }
    }
}
