<?php

namespace App\Services;

class MailDeliveryFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Email delivery failed: '.$reason);
    }
}
