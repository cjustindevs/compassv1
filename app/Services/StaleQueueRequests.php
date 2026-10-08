<?php

namespace App\Services;

use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use Illuminate\Support\Facades\DB;

class StaleQueueRequests
{
    public function expire(): int
    {
        $count = 0;
        QueueRequest::where('request_status', 'waiting')->where('request_date', '<=', now()->subHours(24))
            ->eachById(function ($record) use (&$count) {
                DB::transaction(function () use ($record, &$count) {
                    $queue = QueueRequest::whereKey($record->id)->lockForUpdate()->first();
                    if (! $queue || $queue->request_status !== 'waiting' || $queue->request_date->gt(now()->subHours(24))) {
                        return;
                    }
                    $session = Session::where('queue_request_id', $queue->id)->latest('id')->lockForUpdate()->first();
                    if ($session && ($session->isActive() || $session->helper_accepted_at || ($session->scheduled_start && $session->scheduled_start->isFuture()))) {
                        return;
                    }
                    if ($session && ! $session->requires_immediate_action && ! $session->emergencyAlerts()->whereNotIn('status', ModeratorEmergencyCases::TERMINAL)->exists() && ! IncidentReport::where('session_id', $session->id)->whereIn('status', ['open', 'under_review', 'escalated'])->whereIn('incident_category', ['emergency_flag', 'classification_emergency'])->exists()) {
                        app(SeekerWorkflowService::class)->cancel($session->seeker->user, $session, true);
                    } else {
                        // Queue timeout must never close an emergency review or erase its escalation.
                        $queue->update(['request_status' => 'expired', 'expired_at' => now()]);
                        SupportAudit::record('request_expired', $queue, ['reason' => 'unmatched_for_24_hours', 'emergency_review_preserved' => (bool) $session]);
                        if ($queue->seeker?->user_account_id) {
                            Notification::create([
                                'user_account_id' => $queue->seeker->user_account_id, 'title' => 'Waiting request expired',
                                'message' => $session ? 'Your peer-support queue entry expired. Staff emergency review remains separate; emergency resources are still available.' : 'Your waiting request expired after 24 hours. You can submit a new request.',
                                'notification_type' => 'system', 'link' => $session ? '/emergency' : '/seeker/requests']);
                        }
                    }
                    $count++;
                });
            });

        return $count;
    }
}
