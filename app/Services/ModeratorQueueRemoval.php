<?php

namespace App\Services;

use App\Models\{Helper, Notification, QueueRequest, Session};
use Illuminate\Support\Facades\{Auth, Cache, DB};

class ModeratorQueueRemoval
{
    public function remove(int $id): string
    {
        abort_unless(Auth::user()?->role === 'moderator' && Auth::user()->is_active, 403);

        return DB::transaction(function () use ($id) {
            $queue = QueueRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($queue->request_status, ['waiting', 'assigned'], true), 409, 'This request is no longer in the queue.');
            $session = Session::where('queue_request_id', $queue->id)->lockForUpdate()->latest('id')->first();
            abort_if($session && ($session->helper_accepted_at || !in_array($session->session_status, ['waiting', 'helper_assigned'], true)), 409, 'A started or terminal session cannot be removed from the queue.');
            $helper = $queue->assigned_helper_id ? Helper::whereKey($queue->assigned_helper_id)->lockForUpdate()->first() : null;
            $requeue = $queue->request_status === 'assigned';
            $queue->update([
                'request_status' => $requeue ? 'waiting' : 'cancelled',
                'assigned_helper_id' => null,
                'cancelled_at' => $requeue ? null : now(),
                'matched_date' => null,
                'scheduled_date' => null,
            ]);
            if ($session) {
                SupportAudit::record('moderator_queue_removed', $session, ['previous_helper_id' => $session->helper_id, 'requeued' => $requeue]);
                $session->update($requeue ? [
                    'helper_id' => null, 'session_status' => 'waiting', 'workflow_state' => 'queued',
                    'scheduled_start' => null, 'pre_session_brief_expires_at' => null,
                    'match_status' => null,
                ] : ['session_status' => 'cancelled', 'completion_status' => 'cancelled', 'cancelled_at' => now()]);
            } else {
                SupportAudit::record('moderator_queue_removed', $queue, ['requeued' => false]);
            }
            if ($helper) {
                $helper->syncSessionCounters();
                if (!$helper->activeSessions()->exists() && $helper->status === 'busy') $helper->update(['status' => 'available']);
            }
            foreach (array_filter([$queue->seeker?->user_account_id, $helper?->user_account_id]) as $userId) {
                Notification::create([
                    'user_account_id' => $userId, 'title' => 'Support request updated',
                    'message' => $requeue ? 'The proposed assignment was removed. The request is waiting for another helper.' : 'The waiting support request was cancelled by a moderator.',
                    'notification_type' => 'assignment', 'type_icon' => 'fa-circle-info',
                    'link' => $userId === $helper?->user_account_id ? '/helper/cases' : '/seeker/requests',
                ]);
            }
            Cache::forget('moderator_dashboard_stats');
            return $requeue ? 'Assignment removed. The request is waiting for another helper.' : 'Waiting request cancelled.';
        }, 3);
    }
}
