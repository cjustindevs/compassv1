<?php

namespace App\Services;

use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnassignedRequestReminders
{
    public function run(): void
    {
        QueueRequest::where('request_status', 'waiting')->whereRaw('COALESCE(queued_at, request_date) <= ?', [now()->subMinutes(15)])->eachById(function ($row) {
            DB::transaction(function () use ($row) {
                $queue = QueueRequest::lockForUpdate()->findOrFail($row->id);
                if ($queue->request_status !== 'waiting' || $queue->assigned_helper_id || ($queue->queued_at ?? $queue->request_date)->gt(now()->subMinutes(15))) {
                    return;
                }
                $session = Session::where('queue_request_id', $queue->id)->latest('id')->first();
                if (! $session || $session->session_status !== Session::STATUS_WAITING || $session->helper_id) {
                    return;
                }
                $alert = EmergencyAlert::where('session_id', $session->id)->whereNotIn('status', ['resolved', 'closed'])->whereNull('archived_at')->latest('id')->first();
                $eventKey = 'queue-wait:'.$queue->id;
                $created = false;
                foreach (User::where('is_active', true)->whereIn('role', ['adviser', 'moderator'])->get() as $user) {
                    // The queue lock serializes scheduler retries. Include archived/read notices
                    // so dismissing a reminder never creates another popup for this request.
                    if (Notification::withoutGlobalScopes()->where('user_account_id', $user->id)->where('popup_event_key', $eventKey)->exists()) continue;
                    $link = $user->role === 'moderator' ? '/moderator/queue?queue_id='.$queue->id
                        : ($alert ? route('adviser.emergency.coordinate', ['emergency_alerts', $alert->id], false) : '/adviser/dashboard');
                    Notification::create(['user_account_id' => $user->id, 'title' => 'Request unassigned for 15 minutes',
                        'message' => 'Review Helper availability and assign support.',
                        'notification_type' => 'assignment', 'popup_event_key' => $eventKey, 'link' => $link]);
                    $created = true;
                }
                if ($created) SupportAudit::record('unassigned_request_reminder', $queue);
            }, 3);
        });
    }
}
