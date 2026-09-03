<?php

namespace App\Events;

use App\Models\HelpSeeker;
use App\Models\Session;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyRiskDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(public HelpSeeker $seeker, public Session $session, public array $classification) {}
}
