<?php

namespace Database\Seeders;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeeker;
use App\Models\IncidentReport;
use App\Models\Moderator;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\ReadinessCheck;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the moderator workspace (Ivan Reyes).
 *
 * User credentials: moderator@compass.edu.ph / password123
 *
 * Creates:
 *  - the moderator profile
 *  - a second adviser (Dr. Marcus Villanueva) so assignment workspaces have
 *    more than one option
 *  - three extra helpers with no adviser (unassigned pool)
 *  - realistic queue_requests (waiting + assigned)
 *  - incident_reports (open / under_review / escalated / resolved)
 *  - notifications for the moderator
 *
 * Idempotent: safe to re-run.
 */
class ModeratorSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'moderator@compass.edu.ph'],
            [
                'name' => 'Ivan Reyes',
                'password' => Hash::make('password123'),
                'role' => 'moderator',
                'email_verified_at' => now(),
            ]
        );

        Moderator::firstOrCreate(
            ['user_account_id' => $user->id],
            [
                'first_name' => 'Ivan',
                'last_name' => 'Reyes',
                'assigned_shift' => 'Morning Shift',
                'status' => 'available',
                'email' => 'moderator@compass.edu.ph',
            ]
        );

        $this->seedSecondAdviser();
        $this->seedExtraHelpers();
        $this->seedQueueRequests();
        $this->seedIncidents();
        $this->seedNotifications($user);

        $this->command->info("Moderator seeded: Ivan Reyes (moderator@compass.edu.ph)");
    }

    private function seedSecondAdviser(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'adviser2@compass.edu.ph'],
            [
                'name' => 'Dr. Marcus Villanueva',
                'password' => Hash::make('password123'),
                'role' => 'adviser',
                'email_verified_at' => now(),
            ]
        );

        $adviser = Adviser::firstOrCreate(
            ['user_account_id' => $user->id],
            [
                'first_name' => 'Marcus',
                'last_name' => 'Villanueva',
                'email' => 'adviser2@compass.edu.ph',
            ]
        );

        // Move two existing helpers under the new adviser so the Manage page
        // shows a realistic distribution.
        foreach (['noel@compass.edu.ph', 'kai@compass.edu.ph'] as $email) {
            Helper::where('email', $email)->update(['adviser_id' => $adviser->id]);
        }
    }

    private function seedExtraHelpers(): void
    {
        $adviser = Adviser::first();
        $pool = [
            [
                'email' => 'luna@compass.edu.ph',
                'first_name' => 'Luna',
                'last_name' => 'Santos',
                'score' => 74,
                'competency_level' => 3,
                'specializations' => 'Stress Management, Sleep, Mindfulness',
            ],
            [
                'email' => 'marco@compass.edu.ph',
                'first_name' => 'Marco',
                'last_name' => 'Reyes',
                'score' => 61,
                'competency_level' => 2,
                'specializations' => 'Peer Support, Study Skills',
            ],
            [
                'email' => 'tara@compass.edu.ph',
                'first_name' => 'Tara',
                'last_name' => 'Mendoza',
                'score' => 79,
                'competency_level' => 3,
                'specializations' => 'Anxiety, Relationships, Self-Care',
            ],
        ];

        foreach ($pool as $data) {
            $u = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'password' => Hash::make('password123'),
                    'role' => 'helper',
                    'email_verified_at' => now(),
                ]
            );

            $helper = Helper::firstOrCreate(
                ['user_account_id' => $u->id],
                [
                    'adviser_id' => null,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'status' => 'available',
                    'competency_level' => $data['competency_level'],
                    'max_concurrent_sessions' => 2,
                    'specializations' => $data['specializations'],
                    'bio' => 'Peer helper in the moderation pool, ready for adviser assignment.',
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

            if ($adviser) {
                HelperCompetencyHistory::firstOrCreate(
                    ['helper_id' => $helper->id],
                    [
                        'adviser_id' => $adviser->id,
                        'evaluation_date' => now()->subWeeks(3),
                        'active_listening_score' => $data['score'] + 1,
                        'empathy_score' => $data['score'],
                        'respect_score' => min(99, $data['score'] + 2),
                        'ethical_practices_score' => min(99, $data['score'] + 1),
                        'referral_accuracy_score' => max(50, $data['score'] - 2),
                        'overall_score' => $data['score'],
                        'competency_level' => $this->levelLabel($data['score']),
                        'evaluation_period' => now()->format('F Y'),
                        'remarks' => 'Entry-level competency review.',
                    ]
                );
            }
        }
    }

    private function seedQueueRequests(): void
    {
        $seekers = HelpSeeker::limit(6)->get();
        $helpers = Helper::whereNotNull('adviser_id')->get();

        if ($seekers->count() < 6 || $helpers->count() < 2) {
            $this->command->warn('ModeratorSeeder: not enough seekers/helpers for queue_requests.');

            return;
        }

        if (QueueRequest::count() > 0) {
            $this->command->info('ModeratorSeeder: queue_requests already exist, skipping.');

            return;
        }

        $moderator = Moderator::first();
        $priorities = ['emergency', 'high', 'high', 'moderate', 'moderate', 'low', 'low', 'moderate'];
        $types = ['chat', 'voice', 'chat', 'chat', 'voice', 'chat', 'voice', 'chat'];

        foreach (range(0, 7) as $i) {
            $seeker = $seekers[$i % $seekers->count()];
            $requestDate = now()->subMinutes([12, 38, 4, 65, 22, 95, 47, 8][$i]);

            QueueRequest::create([
                'seeker_id' => $seeker->id,
                'moderator_id' => $moderator->id,
                'request_date' => $requestDate,
                'request_status' => $i === 4 ? 'assigned' : 'waiting',
                'priority_level' => $priorities[$i],
                'preferred_session_type' => $types[$i],
                'assigned_helper_id' => $i === 4 ? $helpers[0]->id : null,
                'queue_position' => $i === 4 ? null : $i + 1,
                'estimated_wait' => $i === 4 ? null : (5 + $i * 4),
                'matched_date' => $i === 4 ? now()->subMinutes(30) : null,
            ]);
        }
    }

    private function seedIncidents(): void
    {
        if (IncidentReport::count() > 1) {
            $this->command->info('ModeratorSeeder: incident_reports already exist, skipping.');

            return;
        }

        $activeSessions = Session::where('session_status', 'active')->with('seeker')->get();
        $moderator = Moderator::first();
        $seekerUsers = User::where('role', 'seeker')->get();

        if ($activeSessions->count() < 2 || $seekerUsers->isEmpty()) {
            $this->command->warn('ModeratorSeeder: not enough sessions/users for incidents.');

            return;
        }

        $s1 = $activeSessions[0];
        $s2 = $activeSessions[1] ?? $activeSessions[0];

        IncidentReport::firstOrCreate(
            ['session_id' => $s1->id],
            [
                'user_account_id' => $s1->seeker?->user_account_id ?? $seekerUsers[0]->id,
                'moderator_id' => $moderator->id,
                'incident_category' => 'Self-Harm Risk',
                'description' => 'Helper flagged strong distress cues during the session. Seeker expressed hopelessness and needs immediate professional attention.',
                'immediate_action' => 'Helper stayed on the line and activated the emergency protocol.',
                'risk_level' => 'emergency',
                'recommendation' => 'Escalate to a psychology professional and notify the adviser.',
                'status' => 'under_review',
            ]
        );

        IncidentReport::firstOrCreate(
            ['session_id' => $s2->id],
            [
                'user_account_id' => $s2->seeker?->user_account_id ?? $seekerUsers[1]->id,
                'moderator_id' => $moderator->id,
                'incident_category' => 'Panic Episode',
                'description' => 'Seeker reported a panic episode during a voice session. Helper guided breathing exercises and the seeker stabilized.',
                'immediate_action' => 'Session continued with active grounding techniques.',
                'risk_level' => 'high',
                'recommendation' => 'Schedule a follow-up session within 48 hours.',
                'status' => 'open',
            ]
        );

        $completed = Session::where('session_status', 'completed')->first();

        if ($completed) {
            IncidentReport::firstOrCreate(
                ['session_id' => $completed->id],
                [
                    'user_account_id' => $completed->seeker?->user_account_id ?? $seekerUsers[0]->id,
                    'moderator_id' => $moderator->id,
                    'incident_category' => 'Acute Grief Response',
                    'description' => 'Seeker showed acute grief symptoms; referred to a professional and support resources were shared.',
                    'immediate_action' => 'Referral prepared and sent to the adviser.',
                    'risk_level' => 'moderate',
                    'recommendation' => 'Professional follow-up scheduled.',
                    'status' => 'resolved',
                    'resolved_at' => now()->subDays(6),
                ]
            );
        }
    }

    private function seedNotifications(User $user): void
    {
        $samples = [
            ['title' => 'Emergency case flagged', 'message' => 'A self-harm risk incident is awaiting review in the emergency queue.', 'notification_type' => 'emergency', 'type_icon' => '🚨', 'link' => '/moderator/emergency'],
            ['title' => 'New seeker in queue', 'message' => 'A new help seeker joined the incoming queue and is waiting for a helper.', 'notification_type' => 'queue', 'type_icon' => '⏳', 'link' => '/moderator/queue'],
            ['title' => 'Referral recommended', 'message' => 'A helper recommended a referral for a completed session. Review in the emergency workspace.', 'notification_type' => 'referral', 'type_icon' => '📋', 'link' => '/moderator/emergency'],
        ];

        foreach ($samples as $sample) {
            Notification::firstOrCreate(
                ['user_account_id' => $user->id, 'title' => $sample['title']],
                array_merge($sample, [
                    'user_account_id' => $user->id,
                    'status' => 'unread',
                    'read_at' => null,
                ])
            );
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