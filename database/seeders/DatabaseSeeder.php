<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // One test user per role, all with password: "password"
        foreach (['seeker', 'helper', 'adviser', 'moderator', 'professional', 'admin'] as $role) {
            User::updateOrCreate(
                ['email' => $role . '@example.com'],
                [
                    'name' => ucfirst($role) . ' User',
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->call([
            SelfHelpResourceSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}