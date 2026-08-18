<?php

namespace Database\Seeders;

use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds referral records across the full pipeline:
 * pending review → approved (pending professional) → declined → completed.
 *
 * Also seeds one psychology professional so the adviser can assign
 * approved referrals (user: professional@compass.edu.ph / password123).
 */
class ReferralSeeder extends Seeder
{
    public function run(): void
    {
        if (Referral::count() > 0) {
            $this->command->info('ReferralSeeder skipped: referrals already exist.');

            return;
        }

        $professionalUser = User::firstOrCreate(
            ['email' => 'professional@compass.edu.ph'],
            [
                'name' => 'Dr. Marcus Tan',
                'password' => Hash::make('password123'),
                'role' => 'professional',
                'email_verified_at' => now(),
            ]
        );

        $professional = PsychologyProfessional::firstOrCreate(
            ['user_account_id' => $professionalUser->id],
            [
                'first_name' => 'Marcus',
                'last_name' => 'Tan',
                'email' => 'professional@compass.edu.ph',
                'specialization' => 'Clinical Psychology - Grief and Trauma',
            ]
        );

        // Emergency referral from the active emergency session (BraveHarbor12)
        $emergencySession = Session::where('session_status', 'active')
            ->where('risk_level', 'emergency')
            ->latest('created_date')
            ->first();

        if ($emergencySession) {
            Referral::create([
                'session_id' => $emergencySession->id,
                'helper_id' => $emergencySession->helper_id,
                'priority_level' => 'emergency',
                'help_seeker_consent' => true,
                'identity_disclosed' => false,
                'referral_reason' => 'Seeker reported escalating family conflict with safety concerns. Immediate professional intervention recommended.',
                'referral_date' => now()->subMinutes(10),
                'status' => 'pending_adviser',
            ]);
        }

        // High-priority referral from the completed high-risk session (CalmForest144)
        $griefSession = Session::where('session_status', 'completed')
            ->where('risk_level', 'high')
            ->latest('created_date')
            ->first();

        if ($griefSession) {
            Referral::create([
                'session_id' => $griefSession->id,
                'helper_id' => $griefSession->helper_id,
                'priority_level' => 'high',
                'help_seeker_consent' => true,
                'identity_disclosed' => true,
                'referral_reason' => 'Prolonged grief reaction affecting daily functioning. Seeker agreed to professional grief counseling.',
                'referral_date' => now()->subDays(3)->addHours(1),
                'status' => 'pending_adviser',
            ]);
        }

        // Approved referral (pending professional assignment) on the evaluated session
        $evaluatedSession = Session::where('session_status', 'evaluated')->first();

        if ($evaluatedSession) {
            Referral::create([
                'session_id' => $evaluatedSession->id,
                'helper_id' => $evaluatedSession->helper_id,
                'adviser_id' => $this->adviserId(),
                'professional_id' => $professional->id,
                'priority_level' => 'moderate',
                'help_seeker_consent' => true,
                'identity_disclosed' => false,
                'referral_reason' => 'Persistent anxiety interfering with academic performance. Recommended ongoing professional support.',
                'referral_date' => now()->subWeek()->addHours(2),
                'status' => 'pending_professional',
            ]);
        }

        // Declined referral (no-show session)
        $noShowSession = Session::where('session_status', 'no_show')->first();

        if ($noShowSession) {
            Referral::create([
                'session_id' => $noShowSession->id,
                'helper_id' => $noShowSession->helper_id,
                'adviser_id' => $this->adviserId(),
                'priority_level' => 'low',
                'help_seeker_consent' => false,
                'identity_disclosed' => false,
                'referral_reason' => 'Seeker requested follow-up support after missed session.',
                'referral_date' => now()->subDays(2),
                'closed_date' => now()->subDay(),
                'status' => 'declined',
            ]);
        }

        // Completed referral on the scheduled session's history
        $scheduledSession = Session::where('session_status', 'scheduled')->first();

        if ($scheduledSession) {
            Referral::create([
                'session_id' => $scheduledSession->id,
                'helper_id' => $scheduledSession->helper_id,
                'adviser_id' => $this->adviserId(),
                'professional_id' => $professional->id,
                'priority_level' => 'low',
                'help_seeker_consent' => true,
                'identity_disclosed' => true,
                'referral_reason' => 'Career counseling referral following academic stress session.',
                'referral_date' => now()->subDays(4),
                'closed_date' => now()->subDays(2),
                'status' => 'completed',
            ]);
        }

        $this->command->info('ReferralSeeder: 5 referrals + 1 professional seeded.');
    }

    private function adviserId(): ?int
    {
        return \App\Models\Adviser::query()->value('id');
    }
}