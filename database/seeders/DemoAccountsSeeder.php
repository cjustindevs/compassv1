<?php

namespace Database\Seeders;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeeker;
use App\Models\Moderator;
use App\Models\PsychologyProfessional;
use App\Models\ReadinessCheck;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds additional named demo accounts using the @compass.local domain.
 *
 * Password format is <Role>@123, e.g.:
 *  - seeker@...      → Seeker@123
 *  - helper@...      → Helper@123
 *  - adviser@...     → Adviser@123
 *  - professional@... → Professional@123
 *  - moderator@...   → Moderator@123
 *
 * Each account gets its full profile row (help_seekers / helpers / advisers /
 * psychology_professionals / moderators) so every login lands on a working
 * dashboard. Helpers also get readiness_checks and competency history reviewed
 * by the first adviser, so the matching engine picks them up.
 *
 * Idempotent: safe to re-run.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSeekers();
        $this->seedAdvisers();
        $this->seedHelpers();
        $this->seedProfessionals();
        $this->seedModerators();
    }

    private function seedSeekers(): void
    {
        $seekers = [
            ['email' => 'rina@compass.local', 'first_name' => 'Rina', 'last_name' => 'Santos', 'alias' => 'CalmRiver98', 'age' => 20, 'gender' => 'female'],
            ['email' => 'andre@compass.local', 'first_name' => 'Andre', 'last_name' => 'Villanueva', 'alias' => 'BraveHorizon23', 'age' => 21, 'gender' => 'male'],
            ['email' => 'jasmine@compass.local', 'first_name' => 'Jasmine', 'last_name' => 'Del Rosario', 'alias' => 'SunnyPath64', 'age' => 19, 'gender' => 'female'],
            ['email' => 'leo@compass.local', 'first_name' => 'Leo', 'last_name' => 'Olivarez', 'alias' => 'QuietOcean11', 'age' => 22, 'gender' => 'male'],
        ];

        foreach ($seekers as $i => $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'password' => Hash::make('Seeker@123'),
                    'role' => 'seeker',
                    'email_verified_at' => now(),
                ]
            );

            HelpSeeker::firstOrCreate(
                ['user_account_id' => $user->id],
                [
                    'generated_alias' => $data['alias'],
                    'age' => $data['age'],
                    'gender' => $data['gender'],
                    'account_created' => now()->subWeeks($i + 2),
                ]
            );

            $this->command->info("Seeker seeded: {$data['first_name']} {$data['last_name']} ({$data['email']}) / Seeker@123");
        }
    }

    private function seedAdvisers(): void
    {
        $advisers = [
            ['email' => 'camille@compass.local', 'first_name' => 'Camille', 'last_name' => 'Navarro'],
            ['email' => 'miguel@compass.local', 'first_name' => 'Miguel', 'last_name' => 'Ramos'],
        ];

        foreach ($advisers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'password' => Hash::make('Adviser@123'),
                    'role' => 'adviser',
                    'email_verified_at' => now(),
                ]
            );

            Adviser::firstOrCreate(
                ['user_account_id' => $user->id],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                ]
            );

            $this->command->info("Adviser seeded: {$data['first_name']} {$data['last_name']} ({$data['email']}) / Adviser@123");
        }
    }

    private function seedHelpers(): void
    {
        $adviser = Adviser::first();

        if (! $adviser) {
            $this->command->warn('No adviser found for competency history — skipped for new helpers.');

            return;
        }

        $helpers = [
            [
                'email' => 'elsa@compass.local',
                'first_name' => 'Elsa',
                'last_name' => 'Bautista',
                'score' => 88,
                'competency_level' => 4,
                'specializations' => 'Anxiety, Peer Support, Self-Care',
            ],
            [
                'email' => 'gab@compass.local',
                'first_name' => 'Gab',
                'last_name' => 'Torres',
                'score' => 82,
                'competency_level' => 4,
                'specializations' => 'Relationships, Academic Stress, Mindfulness',
            ],
        ];

        foreach ($helpers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'password' => Hash::make('Helper@123'),
                    'role' => 'helper',
                    'email_verified_at' => now(),
                ]
            );

            $helper = Helper::firstOrCreate(
                ['user_account_id' => $user->id],
                [
                    'adviser_id' => $adviser->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'status' => 'available',
                    'competency_level' => $data['competency_level'],
                    'max_concurrent_sessions' => 2,
                    'specializations' => $data['specializations'],
                    'bio' => 'Peer helper dedicated to active listening, empathy, and compassionate support.',
                    'preferred_language' => 'English',
                ]
            );

            ReadinessCheck::firstOrCreate(
                ['helper_id' => $helper->id],
                [
                    'availability_status' => 'available',
                    'assessment_result' => 'ready',
                    'emotionally_ready' => true,
                    'willing_to_listen' => true,
                    'stress_level' => 'low',
                    'assessment_date' => now(),
                ]
            );

            HelperCompetencyHistory::firstOrCreate(
                ['helper_id' => $helper->id],
                [
                    'adviser_id' => $adviser->id,
                    'evaluation_date' => now()->subWeeks(1),
                    'active_listening_score' => $data['score'] + 1,
                    'empathy_score' => $data['score'],
                    'respect_score' => min(99, $data['score'] + 2),
                    'ethical_practices_score' => min(99, $data['score'] + 1),
                    'referral_accuracy_score' => max(50, $data['score'] - 2),
                    'overall_score' => $data['score'],
                    'competency_level' => $this->levelLabel($data['score']),
                    'evaluation_period' => now()->format('F Y'),
                    'remarks' => 'Quarterly competency review conducted by the assigned adviser.',
                ]
            );

            $this->command->info("Helper seeded: {$data['first_name']} {$data['last_name']} ({$data['email']}) / Helper@123");
        }
    }

    private function seedProfessionals(): void
    {
        $professionals = [
            [
                'email' => 'angelica@compass.local',
                'first_name' => 'Angelica',
                'last_name' => 'Suarez',
                'specialization' => 'Clinical Psychology',
                'license_number' => 'PSY-2026-001',
                'phone' => '+63 917 555 0201',
            ],
            [
                'email' => 'victor@compass.local',
                'first_name' => 'Victor',
                'last_name' => 'Aquino',
                'specialization' => 'Counseling Psychology',
                'license_number' => 'PSY-2026-002',
                'phone' => '+63 917 555 0202',
            ],
        ];

        foreach ($professionals as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => 'Dr. ' . $data['first_name'] . ' ' . $data['last_name'],
                    'password' => Hash::make('Professional@123'),
                    'role' => 'professional',
                    'email_verified_at' => now(),
                ]
            );

            PsychologyProfessional::firstOrCreate(
                ['user_account_id' => $user->id],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'specialization' => $data['specialization'],
                    'license_number' => $data['license_number'],
                    'phone' => $data['phone'],
                    'is_available' => true,
                ]
            );

            $this->command->info("Professional seeded: Dr. {$data['first_name']} {$data['last_name']} ({$data['email']}) / Professional@123");
        }
    }

    private function seedModerators(): void
    {
        $moderators = [
            ['email' => 'sophia@compass.local', 'first_name' => 'Sophia', 'last_name' => 'Barretto', 'shift' => 'Morning Shift'],
            ['email' => 'rafael@compass.local', 'first_name' => 'Rafael', 'last_name' => 'Mendoza', 'shift' => 'Night Shift'],
        ];

        foreach ($moderators as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'password' => Hash::make('Moderator@123'),
                    'role' => 'moderator',
                    'email_verified_at' => now(),
                ]
            );

            Moderator::firstOrCreate(
                ['user_account_id' => $user->id],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'assigned_shift' => $data['shift'],
                    'status' => 'available',
                    'email' => $data['email'],
                ]
            );

            $this->command->info("Moderator seeded: {$data['first_name']} {$data['last_name']} ({$data['email']}) / Moderator@123");
        }
    }

    private function levelLabel(int $score): string
    {
        if ($score >= 90) return 'expert';
        if ($score >= 80) return 'advanced';
        if ($score >= 70) return 'proficient';
        return 'developing';
    }
}