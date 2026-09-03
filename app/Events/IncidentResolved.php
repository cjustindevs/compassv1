<?php

namespace App\Events;

use App\Models\IncidentReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(public IncidentReport $incident) {}
}
