<?php

namespace Database\Seeders;

use App\Models\CallLog;
use App\Models\ConcernCategory;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Message;
use App\Models\Session;
use App\Models\SessionReport;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic spread of sessions across the full status flow:
 * screening_completed → preferences_set → waiting → helper_assigned → active
 * → completed → evaluated, plus messages, session reports and call logs.
 *
 * Idempotent: safe to re-run.
 */
class SessionSeeder extends Seeder
{
    public function run(): void
    {
        // Only the five demonstration seekers get sessions here; the
        // HelperModuleSeeder account has its own separate sessions.
        $demoAliases = ['QuietWillow52', 'CalmForest144', 'BraveHarbor12', 'SunlitPath79', 'MorningLekko07'];
        $seekers = HelpSeeker::whereIn('generated_alias', $demoAliases)->get()->sortBy(function ($seeker) use ($demoAliases) {
            return array_search($seeker->generated_alias, $demoAliases, true);
        })->values();
        $helpers = Helper::all();
        $concerns = ConcernCategory::pluck('id', 'concern_name');

        if ($seekers->count() < 5 || $helpers->isEmpty() || $concerns->isEmpty()) {
            $this->command->warn('SessionSeeder skipped: missing seekers, helpers, or concerns.');

            return;
        }

        // Skip only if these demo seekers already have sessions
        // (idempotent: safe to re-run).
        if (Session::whereIn('seeker_id', $seekers->pluck('id'))->exists()) {
            $this->command->info('SessionSeeder skipped: sessions already exist.');

            return;
        }

        $s1 = $seekers->firstWhere('generated_alias', 'QuietWillow52') ?? $seekers[0];
        $s2 = $seekers->firstWhere('generated_alias', 'CalmForest144') ?? $seekers[1];
        $s3 = $seekers->firstWhere('generated_alias', 'BraveHarbor12') ?? $seekers[2];
        $s4 = $seekers->firstWhere('generated_alias', 'SunlitPath79') ?? $seekers[3];
        $s5 = $seekers->firstWhere('generated_alias', 'MorningLekko07') ?? $seekers[4];

        $h1 = $this->helperByEmail($helpers, 'maya@compass.edu.ph', 0);
        $h2 = $this->helperByEmail($helpers, 'noel@compass.edu.ph', 1);
        $h3 = $this->helperByEmail($helpers, 'rina@compass.edu.ph', 2);
        $h4 = $this->helperByEmail($helpers, 'kai@compass.edu.ph', 3);
        $h5 = $this->helperByEmail($helpers, 'sasha@compass.edu.ph', 4);
        $h6 = $this->helperByEmail($helpers, 'theo@compass.edu.ph', 5);

        $anxiety = $concerns['Anxiety'] ?? $concerns->values()->first();
        $academic = $concerns['Academic Stress'] ?? $concerns->values()->first();
        $family = $concerns['Family Problems'] ?? $concerns->values()->first();
        $mood = $concerns['Depression'] ?? $concerns->values()->first();
        $grief = $concerns['Grief'] ?? $concerns->values()->first();

        // ── Evaluated session (QuietWillow52 × Maya, a week ago) ──────────
        $evaluated = $this->createSession($s1, $h1, $anxiety, [
            'session_status' => 'evaluated',
            'risk_level' => 'moderate',
            'start_time' => now()->subWeek()->subHours(2),
            'end_time' => now()->subWeek()->subHours(1),
            'duration' => 52,
            'completion_status' => 'completed',
            'created_date' => now()->subWeek()->subHours(3),
        ]);

        $this->addMessages($evaluated, [
            ['sender' => 'seeker', 'text' => 'I have been so anxious about my midterms I can barely sleep.', 'minutes_ago' => 7 * 24 * 60 + 110],
            ['sender' => 'helper', 'text' => 'That sounds exhausting. Let us take this slowly — what feels heaviest right now?', 'minutes_ago' => 7 * 24 * 60 + 105],
        ]);

        SessionReport::create([
            'session_id' => $evaluated->id,
            'help_seeker_condition' => 'Moderate test anxiety with sleep disturbance.',
            'session_summary' => 'Practiced box breathing and reframed catastrophic thoughts about exams. Seeker reported feeling calmer.',
            'personal_reflection' => 'I stayed with the feeling instead of rushing to solutions. This worked well.',
            'skills_applied' => ['active_listening', 'validation', 'crisis_intervention'],
            'referral_recommended' => false,
            'adviser_reviewed' => true,
            'reviewed_date' => now()->subWeek()->addDay(),
            'created_date' => now()->subWeek()->addHours(1),
        ]);

        // ── Completed session (CalmForest144 × Noel, three days ago) ──────
        $completed = $this->createSession($s2, $h2, $grief, [
            'session_status' => 'completed',
            'risk_level' => 'high',
            'start_time' => now()->subDays(3)->subHours(2),
            'end_time' => now()->subDays(3)->subHours(1),
            'duration' => 40,
            'completion_status' => 'completed',
            'created_date' => now()->subDays(3)->subHours(3),
        ]);

        $this->addMessages($completed, [
            ['sender' => 'seeker', 'text' => 'I lost my grandmother last month and I still cannot function.', 'minutes_ago' => 3 * 24 * 60 + 115],
            ['sender' => 'helper', 'text' => 'Grief has no timeline. It is okay that you are still carrying this.', 'minutes_ago' => 3 * 24 * 60 + 110],
        ]);

        SessionReport::create([
            'session_id' => $completed->id,
            'help_seeker_condition' => 'Prolonged grief reaction with functional impairment.',
            'session_summary' => 'Validated the grieving process and explored a small daily routine to restore structure.',
            'personal_reflection' => 'This case was heavy — I checked my own capacity before and after.',
            'skills_applied' => ['active_listening', 'validation', 'empathy'],
            'referral_recommended' => true,
            'adviser_reviewed' => false,
            'created_date' => now()->subDays(3)->subHours(1),
        ]);

        CallLog::create([
            'session_id' => $completed->id,
            'recording_consent' => false,
            'call_start' => now()->subDays(3)->subHours(2),
            'call_end' => now()->subDays(3)->subHours(1),
            'duration' => 2400,
            'review_status' => 'pending',
        ]);

        // ── Active session (BraveHarbor12 × Rina, right now) ──────────────
        $active = $this->createSession($s3, $h3, $family, [
            'session_status' => 'active',
            'risk_level' => 'emergency',
            'voice_recording_consent' => true,
            'escalation_required' => true,
            'start_time' => now()->subMinutes(18),
            'completion_status' => 'pending',
            'created_date' => now()->subMinutes(40),
        ]);

        $this->addMessages($active, [
            ['sender' => 'seeker', 'text' => 'Things at home are getting really bad. I do not know who else to talk to.', 'minutes_ago' => 18],
            ['sender' => 'helper', 'text' => 'You are safe here. Thank you for trusting me with this.', 'minutes_ago' => 15],
        ]);

        // ── Waiting for helper (SunlitPath79, in queue) ───────────────────
        $this->createSession($s4, null, $academic, [
            'session_status' => 'waiting',
            'risk_level' => 'moderate',
            'completion_status' => 'pending',
            'created_date' => now()->subMinutes(25),
        ]);

        // ── Helper assigned, not accepted yet (MorningLekko07 × Sasha) ────
        $assigned = $this->createSession($s5, $h5, $mood, [
            'session_status' => 'helper_assigned',
            'risk_level' => 'high',
            'completion_status' => 'pending',
            'created_date' => now()->subMinutes(35),
        ]);

        $h5->update(['status' => 'busy']);

        // ── Preferences set, not yet in queue (SilentRiver21 second request) ──
        $this->createSession($s1, null, $academic, [
            'session_status' => 'preferences_set',
            'risk_level' => 'low',
            'completion_status' => 'pending',
            'created_date' => now()->subMinutes(12),
        ]);

        // ── Screening completed (CalmForest144 second request) ────────────
        $this->createSession($s2, null, $family, [
            'session_status' => 'screening_completed',
            'risk_level' => 'low',
            'completion_status' => 'pending',
            'created_date' => now()->subMinutes(8),
        ]);

        // ── Scheduled upcoming session (SunlitPath79 × Theo) ──────────────
        $this->createSession($s4, $h6, $academic, [
            'session_status' => 'scheduled',
            'risk_level' => 'low',
            'scheduled_start' => now()->addDay()->addHours(2),
            'completion_status' => 'pending',
            'created_date' => now()->subHours(6),
        ]);

        // ── Cancelled / no-show examples ──────────────────────────────────
        $this->createSession($s3, $h4, $mood, [
            'session_status' => 'cancelled',
            'risk_level' => 'low',
            'completion_status' => 'cancelled',
            'end_time' => now()->subDays(5),
            'created_date' => now()->subDays(6),
        ]);

        $this->createSession($s5, $h1, $academic, [
            'session_status' => 'no_show',
            'risk_level' => 'low',
            'completion_status' => 'cancelled',
            'scheduled_start' => now()->subDays(2),
            'end_time' => now()->subDays(2)->addHours(1),
            'created_date' => now()->subDays(3),
        ]);

        $this->command->info('SessionSeeder: 10 demo sessions created.');
    }

    private function helperByEmail($helpers, string $email, int $fallbackIndex): ?Helper
    {
        return $helpers->firstWhere('email', $email) ?? $helpers->get($fallbackIndex);
    }

    private function createSession(HelpSeeker $seeker, ?Helper $helper, $concernId, array $data): Session
    {
        return Session::create(array_merge([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper?->id,
            'concern_id' => $concernId,
            'session_type' => $data['session_type'] ?? 'chat',
            'created_date' => now(),
        ], $data));
    }

    private function addMessages(Session $session, array $messages): void
    {
        foreach ($messages as $message) {
            $sentAt = now()->subMinutes($message['minutes_ago']);
            Message::create([
                'session_id' => $session->id,
                'sender' => $message['sender'],
                'sender_id' => $message['sender'] === 'helper'
                    ? $session->helper?->user_account_id
                    : $session->seeker->user_account_id,
                'message_text' => $message['text'],
                'sent_datetime' => $sentAt,
            ]);
        }
    }
}