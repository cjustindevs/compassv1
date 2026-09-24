<?php

namespace Database\Seeders;

use App\Models\SystemAdministrator;
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
        // One base user per role (password: "password"). The dedicated
        // seeders below create the real profile rows for each role.
        foreach (['seeker', 'helper', 'moderator', 'admin'] as $role) {
            User::firstOrCreate(
                ['email' => $role . '@example.com'],
                [
                    'name' => ucfirst($role) . ' User',
                    'password' => Hash::make('password'),
                    'role' => $role,
                ]
            );
        }

        // The admin module expects an administrator profile row for admin accounts.
        $admin = User::where('role', 'admin')->first();
        if ($admin && $admin->email === 'admin@example.com') {
            SystemAdministrator::firstOrCreate(
                ['user_account_id' => $admin->id],
                ['first_name' => 'System', 'last_name' => 'Administrator', 'email' => $admin->email]
            );
        }

        // Base users for the main demo accounts (password: "password123")
        foreach (['seeker', 'helper', 'adviser', 'professional'] as $role) {
            User::firstOrCreate(
                ['email' => $role . '@compass.edu.ph'],
                [
                    'name' => ucfirst($role) . ' User',
                    'password' => Hash::make('password123'),
                    'role' => $role,
                ]
            );
        }

        $this->call([
            AdviserSeeder::class,
            DemoAccountsSeeder::class,
            ProfessionalSeeder::class,
            HelperModuleSeeder::class,
            HelperSeeder::class,
            SeekerSeeder::class,
            SessionSeeder::class,
            ReferralSeeder::class,
            EvaluationSeeder::class,
            EmergencyResourceSeeder::class,
            SelfHelpResourceSeeder::class,
            NotificationSeeder::class,
            CalendarEventSeeder::class,
            ModeratorSeeder::class,
        ]);
    }
}