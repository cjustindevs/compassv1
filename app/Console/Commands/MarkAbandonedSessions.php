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
        $count = Session::abandoned()->markAbandoned();

        $this->info("Marked {$count} abandoned sessions as cancelled.");

        return self::SUCCESS;
    }
}
