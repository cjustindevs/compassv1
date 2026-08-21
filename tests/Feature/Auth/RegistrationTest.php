<?php

namespace Tests\Feature\Auth;

use App\Models\ConsentRecord;
use App\Models\HelpSeeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_via_otp_flow(): void
    {
        Mail::fake();

        // Step 1: request an OTP
        $this->post('/api/send-otp', ['email' => 'test@example.com'])
            ->assertJson(['success' => true]);

        $otp = session('otp');
        $this->assertNotNull($otp);

        // Step 2: verify the OTP — creates the verification token
        $verify = $this->post('/api/verify-otp', ['otp' => $otp])
            ->assertJson(['success' => true]);

        $token = $verify->json('token');

        // Step 3: complete registration with the token
        $response = $this->post('/api/register-seeker', [
            'alias' => 'Test_Seeker',
            'email' => 'test@example.com',
            'age' => 22,
            'gender' => 'female',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => true,
            'verification_token' => $token,
        ]);

        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'seeker',
        ]);

        $this->assertDatabaseHas('help_seekers', [
            'generated_alias' => 'Test_Seeker',
        ]);

        $this->assertDatabaseHas('consent_records', [
            'document_type' => 'informed_consent',
            'consent_given' => true,
        ]);

        $this->assertGuest();
    }

    public function test_registration_rejects_an_unverified_email(): void
    {
        $response = $this->post('/api/register-seeker', [
            'alias' => 'Test_Seeker',
            'email' => 'test@example.com',
            'age' => 22,
            'gender' => 'female',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => true,
            'verification_token' => 'invalid-token',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}