<?php

namespace App\Console\Commands;

use App\Services\IdentityVaultService;
use Illuminate\Console\Command;

class PurgeExpiredIdentities extends Command
{
    protected $signature = 'identity-vault:purge-expired';
    protected $description = 'Erase expired identity fields while preserving the access audit trail';

    public function handle(IdentityVaultService $vault): int
    {
        $this->info('Expired identity records erased: ' . $vault->purgeExpired());
        return self::SUCCESS;
    }
}
