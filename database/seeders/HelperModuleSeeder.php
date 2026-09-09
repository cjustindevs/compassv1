<?php

namespace Database\Seeders;

use App\Models\Adviser;
use App\Models\CallLog;
use App\Models\ConcernCategory;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeeker;
use App\Models\Message;
use App\Models\Notification;
use App\Models\ReadinessCheck;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds real, database-stored demonstration data for the Helper Module.
 * All rows are persisted to the tables used by the helper features.
 */
class HelperModuleSeeder extends Seeder
{
    public function run(): void
    {
        // ── User accounts ──────────────────────────────────────────────
        $helperUser = User::firstOrCreate(
            ['email' => 'helper@example.com'],
            ['name' => 'Helper User', 'password' => Hash::make('password'), 'role' => 'helper', 'email_verified_at' => now()]
        );

        $seekerUser = User::firstOrCreate(
            ['email' => 'seeker@example.com'],
            ['name' => 'Seeker User', 'password' => Hash::make('password'), 'role' => 'seeker', 'email_verified_at' => now()]
        );

        // ── Adviser (canonical account from AdviserSeeder) ────────────────
        $adviserUser = User::firstOrCreate(
            ['email' => 'adviser@compass.edu.ph'],
            ['name' => 'Dr. Elena Cruz', 'password' => Hash::make('password123'), 'role' => 'adviser', 'email_verified_at' => now()]
        );

        // ── Profiles ───────────────────────────────────────────────────
        $adviser = Adviser::firstOrCreate(
            ['user_account_id' => $adviserUser->id],
            ['first_name' => 'Elena', 'last_name' => 'Cruz', 'email' => 'adviser@compass.edu.ph']
        );

        $helper = Helper::firstOrCreate(
            ['user_account_id' => $helperUser->id],
            [
                'first_name' => 'Helper',
                'last_name' => 'User',
                'email' => 'helper@example.com',
                'status' => 'available',
                'competency_level' => 2,
                'max_concurrent_sessions' => 2,
                'specializations' => 'Anxiety, Family Stress, Academics',
                'bio' => 'Psychology student helper passionate about active listening and peer support.',
                'preferred_language' => 'English',
            ]
        );
        $helper->update(['status' => 'available']);

        $seeker = HelpSeeker::firstOrCreate(
            ['user_account_id' => $seekerUser->id],
            ['generated_alias' => 'SilentRiver21', 'age' => 19, 'gender' => 'female', 'account_created' => now()]
        );

        $concerns = ConcernCategory::pluck('id', 'concern_name');
        if ($concerns->isEmpty()) {
            return;
        }
        $concernId = $concerns->values()->first();

        // ── Readiness checks ───────────────────────────────────────────
        ReadinessCheck::firstOrCreate(
            ['helper_id' => $helper->id, 'assessment_date' => now()->subDay()],
            [
                'availability_status' => 'available',
                'assessment_result' => 'ready',
                'emotionally_ready' => true,
                'willing_to_listen' => true,
                'stress_level' => 'low',
                'valid_until' => now()->addHours(4),
            ]
        );

        // ── Competency history ─────────────────────────────────────────
        HelperCompetencyHistory::firstOrCreate(
            ['helper_id' => $helper->id, 'adviser_id' => $adviser->id, 'evaluation_date' => now()->subWeeks(2)],
            [
                'active_listening_score' => 90,
                'empathy_score' => 92,
                'respect_score' => 95,
                'ethical_practices_score' => 94,
                'referral_accuracy_score' => 89,
                'overall_score' => 92,
                'competency_level' => 'advanced',
                'evaluation_period' => 'Q1',
                'remarks' => 'Consistently demonstrates strong active listening and a calm, non-judgmental presence.',
            ]
        );

        // ── Sessions ───────────────────────────────────────────────────
        $sessions = [
            [
                'seeker_id' => $seeker->id,
                'helper_id' => $helper->id,
                'concern_id' => $concernId,
                'session_type' => 'chat',
                'session_status' => 'completed',
                'risk_level' => 'low',
                'start_time' => now()->subDays(6)->subHours(2),
                'end_time' => now()->subDays(6)->subHours(1),
                'duration' => 45,
                'completion_status' => 'completed',
            ],
            [
                'seeker_id' => $seeker->id,
                'helper_id' => $helper->id,
                'concern_id' => $concernId,
                'session_type' => 'voice',
                'session_status' => 'completed',
                'risk_level' => 'moderate',
                'start_time' => now()->subDays(3),
                'end_time' => now()->subDays(3)->addMinutes(30),
                'duration' => 30,
                'completion_status' => 'completed',
                'voice_recording_consent' => true,
            ],
            [
                'seeker_id' => $seeker->id,
                'helper_id' => $helper->id,
                'concern_id' => $concernId,
                'session_type' => 'chat',
                'session_status' => 'active',
                'risk_level' => 'low',
                'start_time' => now()->subMinutes(18),
                'completion_status' => 'pending',
            ],
            [
                'seeker_id' => $seeker->id,
                'helper_id' => $helper->id,
                'concern_id' => $concernId,
                'session_type' => 'chat',
                'session_status' => 'helper_assigned',
                'risk_level' => 'high',
                'completion_status' => 'pending',
            ],
        ];

        // Only create the demo sessions once — the timestamps roll forward
        // so repeated seeds would otherwise duplicate them.
        if (Session::where('helper_id', $helper->id)->doesntExist()) {
            foreach ($sessions as $data) {
                $session = Session::create($data);

            // Messages
            if (in_array($session->session_status, ['active', 'completed'])) {
                $messages = $session->session_status === 'active'
                    ? [
                        ['sender' => 'seeker', 'message_text' => 'Hi! Thanks for taking my session. I have been feeling really overwhelmed with school lately.', 'mins_ago' => 18],
                        ['sender' => 'helper', 'message_text' => 'Hi there! Thank you for reaching out — I am really glad you are here. That sounds heavy. Want to tell me a little more about what has been piling up?', 'mins_ago' => 16],
                        ['sender' => 'seeker', 'message_text' => 'It feels like every deadline is at the same time and I cannot catch a break.', 'mins_ago' => 10],
                    ]
                    : [
                        ['sender' => 'seeker', 'message_text' => 'Thank you so much for listening earlier. I feel a lot lighter.', 'mins_ago' => 6000],
                        ['sender' => 'helper', 'message_text' => 'That means a lot. Remember the breathing exercise we talked about — you have got this.', 'mins_ago' => 5980],
                    ];

                foreach ($messages as $message) {
                    Message::firstOrCreate(
                        ['session_id' => $session->id, 'sent_datetime' => now()->subMinutes($message['mins_ago'])],
                        [
                            'sender' => $message['sender'],
                            'message_text' => $message['message_text'],
                            'sent_datetime' => now()->subMinutes($message['mins_ago']),
                        ]
                    );
                }
            }

            // Session report for completed sessions
            if ($session->session_status === 'completed') {
                SessionReport::firstOrCreate(
                    ['session_id' => $session->id],
                    [
                        'help_seeker_condition' => 'Mild anxiety and academic stress.',
                        'session_summary' => 'Explored current stressors and practiced box breathing. Seeker reported feeling calmer at the end.',
                        'personal_reflection' => 'I noticed I held the space well and stayed curious instead of jumping to advice.',
                        'skills_applied' => ['active_listening', 'validation', 'problem_solving'],
                        'referral_recommended' => false,
                        'adviser_reviewed' => true,
                        'reviewed_date' => now()->subDays(5),
                    ]
                );

                CallLog::firstOrCreate(
                    ['session_id' => $session->id],
                    [
                        'recording_consent' => $session->voice_recording_consent ?? false,
                        'call_start' => now()->subDays(3),
                        'call_end' => now()->subDays(3)->addMinutes(30),
                        'duration' => 1800,
                        'review_status' => 'reviewed',
                    ]
                );
            }
            }
        }

        // ── Notifications for the helper ───────────────────────────────
        $notifications = [
            ['title' => 'New case assigned', 'message' => 'A seeker with high risk has been assigned to you. Please review the case.', 'notification_type' => 'assignment', 'type_icon' => 'fa-clipboard-list', 'link' => '/helper/cases', 'minutes_ago' => 22],
            ['title' => 'Adviser feedback received', 'message' => 'Your Q1 competency evaluation is ready. A 92% overall score was recorded.', 'notification_type' => 'evaluation', 'type_icon' => '⭐', 'link' => '/helper/competency', 'minutes_ago' => 150],
            ['title' => 'Session completed', 'message' => 'A seeker ended the session. Please complete your session notes.', 'notification_type' => 'session', 'type_icon' => 'fa-pen-to-square', 'link' => '/helper/notes', 'minutes_ago' => 130],
            ['title' => 'Weekly seminar reminder', 'message' => 'Trauma-informed care - Friday at 4pm.', 'notification_type' => 'reminder', 'type_icon' => 'fa-clock', 'link' => '/helper/calendar', 'minutes_ago' => 2600],
        ];

        foreach ($notifications as $notification) {
            Notification::firstOrCreate(
                ['user_account_id' => $helperUser->id, 'title' => $notification['title']],
                [
                    'user_account_id' => $helperUser->id,
                    'title' => $notification['title'],
                    'message' => $notification['message'],
                    'notification_type' => $notification['notification_type'],
                    'type_icon' => $notification['type_icon'],
                    'link' => $notification['link'],
                    'status' => 'unread',
                    'created_at' => now()->subMinutes($notification['minutes_ago']),
                    'updated_at' => now(),
                ]
            );
        }

        echo "Helper module demo data seeded.\n";
    }
}
