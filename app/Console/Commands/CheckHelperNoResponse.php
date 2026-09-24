<?php

namespace App\Console\Commands;

use App\Models\Helper;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;
use App\Services\SupportAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckHelperNoResponse extends Command
{
    protected $signature = 'sessions:check-no-response';
    protected $description = 'Escalate active sessions where the helper has not responded within five minutes';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(5);
        $escalated = 0;

        Session::where('session_status', Session::STATUS_ACTIVE)
            ->whereNull('no_response_escalated_at')
            ->whereNotNull('start_time')
            ->where('start_time', '<=', $cutoff)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_helper_message_at')
                    ->orWhere('last_helper_message_at', '<=', $cutoff);
            })
            ->eachById(function (Session $session) use (&$escalated) {
                DB::transaction(function () use ($session, &$escalated) {
                    $session = Session::lockForUpdate()->findOrFail($session->id);
                    if (! $session->isActive() || $session->no_response_escalated_at) {
                        return;
                    }

                    $session->update(['no_response_escalated_at' => now()]);

                    $adviserUserId = $session->helper?->adviser?->user_account_id;
                    $moderatorIds = User::where('role', 'moderator')->where('is_active', true)->pluck('id');

                    foreach ($adviserUserId ? [$adviserUserId] : [] as $userId) {
                        Notification::create([
                            'user_account_id' => $userId,
                            'title' => 'Helper not responding',
                            'message' => 'The helper for session #' . $session->id . ' has not responded for five minutes. Follow up directly.',
                            'notification_type' => 'session',
                            'type_icon' => 'fa-comment-slash',
                            'link' => '/adviser/sessions/' . $session->id,
                        ]);
                    }

                    foreach ($moderatorIds as $moderatorUserId) {
                        Notification::create([
                            'user_account_id' => $moderatorUserId,
                            'title' => 'Helper not responding',
                            'message' => 'Session #' . $session->id . ' has had no helper response for five minutes and may need reassignment.',
                            'notification_type' => 'session',
                            'type_icon' => 'fa-comment-slash',
                            'link' => '/moderator/queue',
                        ]);
                    }

                    SupportAudit::record('helper_no_response_escalated', $session, [
                        'last_helper_message_at' => $session->last_helper_message_at,
                    ]);

                    $escalated++;
                });
            });

        $this->info("Escalated {$escalated} active session(s) with a helper no-response window.");

        return self::SUCCESS;
    }
}