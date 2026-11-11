<?php

namespace Tests\Concerns;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;

trait BuildsAdviserWorld
{
    protected function adviser(): Adviser
    {
        $user = User::factory()->create(['role' => 'adviser', 'is_active' => true]);

        return Adviser::create([
            'user_account_id' => $user->id,
            'first_name' => 'Demo',
            'last_name' => 'Adviser',
            'email' => $user->email,
        ]);
    }

    protected function helperFor(Adviser $adviser): Helper
    {
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);

        return Helper::create([
            'user_account_id' => $user->id,
            'adviser_id' => $adviser->id,
            'first_name' => 'Demo',
            'last_name' => 'Helper',
            'email' => $user->email,
        ]);
    }

    protected function documentedReport(Adviser $adviser, string $status = 'completed', string $risk = 'low'): SessionReport
    {
        $helper = $this->helperFor($adviser);
        $seekerUser = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'WorldSeeker'.$seekerUser->id, 'age' => 20, 'gender' => 'prefer-not-to-say']);
        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_type' => 'chat',
            'session_status' => $status,
            'risk_level' => $risk,
            'created_date' => now()->subHour(),
            'start_time' => now()->subMinutes(50),
            'end_time' => $status === Session::STATUS_ACTIVE ? null : now()->subMinutes(10),
        ]);

        return SessionReport::create([
            'session_id' => $session->id,
            'session_summary' => 'Evidence of listening and clarification.',
            'personal_reflection' => 'Improve how questions are summarized.',
        ]);
    }

    protected function activeEmergency(?Adviser $adviser = null, string $status = 'pending'): EmergencyAlert
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'SafeAlias'.$user->id]);
        $session = Session::create(['seeker_id' => $seeker->id, 'session_status' => 'waiting', 'risk_level' => 'emergency', 'session_type' => 'chat', 'submitted_at' => now(), 'created_date' => now()]);

        return EmergencyAlert::create([
            'session_id' => $session->id,
            'seeker_id' => $seeker->id,
            'adviser_id' => $adviser?->id,
            'status' => $status,
            'risk_level' => 'emergency',
            'trigger_reason' => 'x',
            'triggered_at' => now()->subMinutes(20),
        ]);
    }
}
