<?php

namespace App\Services;

use App\Models\AuditLog;
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
        QueueRequest::where('request_status', 'waiting')->where('request_date', '<=', now()->subMinutes(15))->eachById(function ($row) {
            DB::transaction(function () use ($row) {
                $queue = QueueRequest::lockForUpdate()->findOrFail($row->id);
                if ($queue->request_status !== 'waiting' || $queue->assigned_helper_id || $queue->request_date->gt(now()->subMinutes(15))) {
                    return;
                }
                $session = Session::where('queue_request_id', $queue->id)->latest('id')->first();
                if (! $session || $session->session_status !== Session::STATUS_WAITING || $session->helper_id) {
                    return;
                }
                $alert = EmergencyAlert::where('session_id', $session->id)->latest('id')->first();
                if ($alert && in_array($alert->status, ['resolved', 'closed'], true)) {
                    return;
                }
                $auditKey = 'unassigned_request_reminder';
                if (AuditLog::where('action', $auditKey)->where('target_type', $queue->getTable())->where('target_id', $queue->id)->where('created_at', '>=', $queue->request_date)->exists()) {
                    return;
                }
                foreach (User::where('is_active', true)->whereIn('role', ['adviser', 'moderator'])->get() as $user) {
                    $link = $alert ? route($user->role.'.emergency.coordinate', ['emergency_alerts', $alert->id], false)
                        : ($user->role === 'moderator' ? '/moderator/queue' : '/adviser/dashboard');
                    Notification::create(['user_account_id' => $user->id, 'title' => 'Request unassigned for 15 minutes',
                        'message' => 'A support request is still waiting. Review availability and coordinate an assignment.',
                        'notification_type' => $alert ? 'emergency' : 'assignment', 'emergency_alert_id' => $alert?->id, 'link' => $link]);
                }
                SupportAudit::record($auditKey, $queue);
            }, 3);
        });
    }
}
