<?php

namespace Database\Seeders;

use App\Models\Helper;
use App\Models\ReadinessCheck;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds ready-and-available peer helper accounts.
 *
 * Each helper gets:
 *  - a users row (role = helper)
 *  - a helpers row with status = 'available' and a competency level
 *  - a readiness_checks row with assessment_result = 'ready'
 *
 * The matching engine (Helper::findAvailableForRisk) only picks helpers
 * that satisfy all three, so this seeder makes demo matching work out of the box.
 *
 * Idempotent: safe to re-run.
 */
class HelperSeeder extends Seeder
{
    public function run(): void
    {
        $helpers = [
            [
                'email' => 'maya@compass.edu.ph',
                'first_name' => 'Maya',
                'last_name' => 'Cordero',
                'competency_level' => 3,
                'specializations' => 'Anxiety, Academic Stress, Family Concerns',
            ],
            [
                'email' => 'kai@compass.edu.ph',
                'first_name' => 'Kai',
                'last_name' => 'Delacruz',
                'competency_level' => 2,
                'specializations' => 'Peer Support, Relationships, Mood',
            ],
            [
                'email' => 'rina@compass.edu.ph',
                'first_name' => 'Rina',
                'last_name' => 'Abueva',
                'competency_level' => 2,
                'specializations' => 'Active Listening, Grief, Self-Esteem',
            ],
        ];

        foreach ($helpers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'password' => Hash::make('password123'),
                    'role' => 'helper',
                    'email_verified_at' => now(),
                ]
            );

            $helper = Helper::firstOrCreate(
                ['user_account_id' => $user->id],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'status' => 'available',
                    'competency_level' => $data['competency_level'],
                    'max_concurrent_sessions' => 2,
                    'specializations' => $data['specializations'],
                    'bio' => 'Psychology student peer helper dedicated to active listening and compassionate support.',
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

            $this->command->info("Helper seeded: {$data['first_name']} {$data['last_name']} ({$data['email']})");
        }
    }
}
