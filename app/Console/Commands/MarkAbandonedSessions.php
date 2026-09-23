<?php

namespace App\Console\Commands;

use App\Models\Session;
use Illuminate\Console\Command;

class MarkAbandonedSessions extends Command
{
    protected $signature = 'sessions:mark-abandoned';
    protected $description = 'Mark stale pending sessions as cancelled (older than 24 hours)';

    public function handle(): int
    {
        $sessions=Session::whereIn('session_status',Session::PENDING_STATUSES)->where('created_date','<',now()->subHours(24))->get();
        foreach($sessions as $session) app(\App\Services\SeekerWorkflowService::class)->cancel($session->seeker->user,$session,true);
        $count=$sessions->count();

        $this->info("Marked {$count} abandoned sessions as cancelled.");

        return self::SUCCESS;
    }
}
