<?php

namespace Database\Seeders;

use App\Models\{Adviser, ConsentRecord, ConcernCategory, Helper, HelperSchedule, HelpSeeker, Moderator, PsychologyProfessional, ReadinessCheck, SystemAdministrator, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Explicit local demonstration accounts from the setup specification. */
class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo accounts may only be seeded locally or in tests.');
        }
        DB::transaction(function () {
            $accounts = [
                ['admin', 'admin@compass.local', 'Admin@123', 'System', 'Administrator', SystemAdministrator::class],
                ['adviser', 'maria.santos@compass.local', 'Adviser@123', 'Maria', 'Santos', Adviser::class],
                ['helper', 'rina@compass.local', 'Helper@123', 'Rina', 'Helper', Helper::class],
                ['moderator', 'moderator@compass.local', 'Moderator@123', 'Demo', 'Moderator', Moderator::class],
                ['professional', 'anna.cruz@compass.local', 'Professional@123', 'Anna', 'Cruz', PsychologyProfessional::class],
            ];
            $profiles = [];
            foreach ($accounts as [$role, $email, $password, $first, $last, $model]) {
                $user = User::updateOrCreate(['email' => $email], ['name' => "$first $last", 'password' => $password,
                    'role' => $role, 'is_active' => true, 'email_verified_at' => now()]);
                $profiles[$role] = $model::updateOrCreate(['user_account_id' => $user->id], [
                    'first_name' => $first, 'last_name' => $last, 'email' => $email,
                ]);
            }
            $helper = $profiles['helper'];
            $helper->update(['adviser_id' => $profiles['adviser']->id, 'availability' => 'available', 'status' => 'available',
                'competency_level' => 3, 'max_concurrent_sessions' => 2, 'preferred_language' => 'English']);
            $profiles['professional']->update(['is_available' => true]);
            HelperSchedule::updateOrCreate(['helper_id' => $helper->id, 'date' => today()], [
                'shift_start' => '00:00:00', 'shift_end' => '23:59:59', 'is_active' => true,
                'created_by' => $profiles['adviser']->user_account_id, 'approved_by' => $profiles['adviser']->id, 'approved_at' => now(),
            ]);
            ReadinessCheck::updateOrCreate(['helper_id' => $helper->id, 'assessment_date' => today()], [
                'availability_status' => 'available', 'assessment_result' => 'ready', 'emotionally_ready' => true,
                'willing_to_listen' => true, 'stress_level' => 'low', 'valid_until' => now()->addHours(4),
            ]);
            $user = User::updateOrCreate(['email' => 'silentwillow52@compass.local'], [
                'name' => 'SilentWillow52', 'password' => 'Seeker@123', 'role' => 'seeker', 'is_active' => true,
                'email_verified_at' => now(), 'preferred_language' => 'English',
            ]);
            $seeker = HelpSeeker::updateOrCreate(['user_account_id' => $user->id], [
                'generated_alias' => 'SilentWillow52', 'pseudo_id' => 'PS-SILENTWILLOW-052', 'age' => 20, 'gender' => 'prefer-not-to-say',
                'is_verified' => true, 'verified_at' => now(), 'account_created' => now(),
            ]);
            foreach (['privacy_policy', 'informed_consent'] as $type) {
                ConsentRecord::updateOrCreate(['seeker_id' => $seeker->id, 'document_type' => $type], [
                    'consent_given' => true, 'consent_date' => now(), 'version' => '1.0',
                ]);
            }
            foreach (['Academic Stress', 'Family Concerns', 'Relationships', 'Anxiety', 'Others'] as $name) {
                ConcernCategory::firstOrCreate(['concern_name' => $name]);
            }
        });
        $this->call([EmergencyResourceSeeder::class, SelfHelpResourceSeeder::class]);
    }
}
