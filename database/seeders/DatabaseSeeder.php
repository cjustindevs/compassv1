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
            User::factory()->create([
                'name' => ucfirst($role) . ' User',
                'email' => $role . '@example.com',
                'password' => Hash::make('password'),
                'role' => $role,
            ]);
        }

        $this->call([
            SelfHelpResourceSeeder::class,
            NotificationSeeder::class,
            HelperModuleSeeder::class,
            HelperSeeder::class,
        ]);
    }
}