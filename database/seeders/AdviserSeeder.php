<?php

namespace Database\Seeders;

use App\Models\Adviser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the canonical adviser account (Dr. Elena Cruz).
 *
 * User credentials: adviser@compass.edu.ph / password123
 */
class AdviserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'adviser@compass.edu.ph'],
            [
                'name' => 'Dr. Elena Cruz',
                'password' => Hash::make('password123'),
                'role' => 'adviser',
                'email_verified_at' => now(),
            ]
        );

        $adviser = Adviser::firstOrCreate(
            ['user_account_id' => $user->id],
            [
                'first_name' => 'Elena',
                'last_name' => 'Cruz',
                'email' => 'adviser@compass.edu.ph',
            ]
        );

        $this->command->info("Adviser seeded: Dr. Elena Cruz (adviser@compass.edu.ph)");
    }
}