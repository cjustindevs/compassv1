<?php

namespace Database\Seeders;

use App\Models\HelpSeeker;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the five demonstration help seekers.
 *
 * All share the password "password123" and use anonymized aliases.
 */
class SeekerSeeder extends Seeder
{
    public function run(): void
    {
        $seekers = [
            ['alias' => 'QuietWillow52', 'age' => 19, 'gender' => 'female'],
            ['alias' => 'CalmForest144', 'age' => 21, 'gender' => 'female'],
            ['alias' => 'BraveHarbor12', 'age' => 20, 'gender' => 'male'],
            ['alias' => 'SunlitPath79', 'age' => 22, 'gender' => 'non-binary'],
            ['alias' => 'MorningLekko07', 'age' => 18, 'gender' => 'female'],
        ];

        foreach ($seekers as $i => $data) {
            $user = User::firstOrCreate(
                ['email' => 'seeker' . ($i + 1) . '@compass.edu.ph'],
                [
                    'name' => $data['alias'],
                    'password' => Hash::make('password123'),
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
                    'account_created' => now()->subWeeks($i + 1),
                ]
            );

            $this->command->info("Seeker seeded: {$data['alias']} (seeker" . ($i + 1) . "@compass.edu.ph)");
        }
    }
}