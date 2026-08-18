<?php

namespace Database\Seeders;

use App\Models\PsychologyProfessional;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the canonical psychology professional accounts.
 *
 * Credentials:
 * - Dr. Maria Santos    → maria@compass.edu.ph / password123
 * - Dr. Juan Dela Cruz  → juan@compass.edu.ph / password123
 */
class ProfessionalSeeder extends Seeder
{
    public function run(): void
    {
        $professionals = [
            [
                'name' => 'Dr. Maria Santos',
                'email' => 'maria@compass.edu.ph',
                'password' => 'password123',
                'specialization' => 'Clinical Psychology',
                'license_number' => 'PSY-2024-001',
                'phone' => '+63 917 555 0101',
                'is_available' => true,
            ],
            [
                'name' => 'Dr. Juan Dela Cruz',
                'email' => 'juan@compass.edu.ph',
                'password' => 'password123',
                'specialization' => 'Counseling Psychology',
                'license_number' => 'PSY-2024-002',
                'phone' => '+63 917 555 0102',
                'is_available' => true,
            ],
        ];

        foreach ($professionals as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'role' => 'professional',
                    'email_verified_at' => now(),
                ]
            );

            PsychologyProfessional::firstOrCreate(
                ['user_account_id' => $user->id],
                [
                    'first_name' => explode(' ', $data['name'])[1] ?? 'Professional',
                    'last_name' => explode(' ', $data['name'])[0] ?? 'Professional',
                    'email' => $data['email'],
                    'specialization' => $data['specialization'],
                    'license_number' => $data['license_number'],
                    'phone' => $data['phone'],
                    'is_available' => $data['is_available'],
                ]
            );

            $this->command->info('Psychology professional created: ' . $data['name'] . ' (' . $data['email'] . ')');
        }
    }
}
