<?php

namespace Database\Seeders;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
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
 *  - a helper_competency_history row reviewed by the adviser (Dr. Elena Cruz)
 *
 * The matching engine (Helper::findAvailableForRisk) only picks helpers
 * that satisfy the availability + readiness + competency requirements,
 * so this seeder makes demo matching work out of the box.
 *
 * Idempotent: safe to re-run.
 */
class HelperSeeder extends Seeder
{
    public function run(): void
    {
        $adviser = Adviser::first();

        if (! $adviser) {
            $this->command->warn('No adviser found. Run AdviserSeeder first — skipping competency history.');

            return;
        }

        $helpers = [
            [
                'email' => 'maya@compass.edu.ph',
                'first_name' => 'Maya',
                'last_name' => 'Cordero',
                'score' => 92,
                'competency_level' => 5,
                'specializations' => 'Anxiety, Academic Stress, Family Concerns',
            ],
            [
                'email' => 'noel@compass.edu.ph',
                'first_name' => 'Noel',
                'last_name' => 'Vasquez',
                'score' => 87,
                'competency_level' => 4,
                'specializations' => 'Grief, Depression, Peer Support',
            ],
            [
                'email' => 'rina@compass.edu.ph',
                'first_name' => 'Rina',
                'last_name' => 'Alonzo',
                'score' => 78,
                'competency_level' => 3,
                'specializations' => 'Active Listening, Self-Esteem, Relationships',
            ],
            [
                'email' => 'kai@compass.edu.ph',
                'first_name' => 'Kai',
                'last_name' => 'Domingo',
                'score' => 84,
                'competency_level' => 4,
                'specializations' => 'Mood, Relationships, Bullying',
            ],
            [
                'email' => 'sasha@compass.edu.ph',
                'first_name' => 'Sasha',
                'last_name' => 'Lim',
                'score' => 71,
                'competency_level' => 3,
                'specializations' => 'Career Concerns, Time Management, Financial Problems',
            ],
            [
                'email' => 'theo@compass.edu.ph',
                'first_name' => 'Theo',
                'last_name' => 'Ramirez',
                'score' => 66,
                'competency_level' => 2,
                'specializations' => 'Leadership, Motivation, Peer Support',
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
                    'adviser_id' => $adviser->id,
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

            HelperCompetencyHistory::firstOrCreate(
                ['helper_id' => $helper->id],
                [
                    'adviser_id' => $adviser->id,
                    'evaluation_date' => now()->subWeeks(2),
                    'active_listening_score' => $data['score'] + 1,
                    'empathy_score' => $data['score'],
                    'respect_score' => min(99, $data['score'] + 2),
                    'ethical_practices_score' => min(99, $data['score'] + 1),
                    'referral_accuracy_score' => max(50, $data['score'] - 2),
                    'overall_score' => $data['score'],
                    'competency_level' => $this->levelLabel($data['score']),
                    'evaluation_period' => now()->format('F Y'),
                    'remarks' => 'Quarterly competency review conducted by Dr. Elena Cruz.',
                ]
            );

            $this->command->info("Helper seeded: {$data['first_name']} {$data['last_name']} ({$data['email']}) - {$data['score']}%");
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