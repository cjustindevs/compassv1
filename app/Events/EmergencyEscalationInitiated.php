<?php

namespace App\Events;

use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Session;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyEscalationInitiated
{
    use Dispatchable, SerializesModels;

    public function __construct(public EmergencyAlert $alert, public HelpSeeker $seeker, public Session $session) {}
}
