<?php

namespace App\Console\Commands;

use App\Services\IdentityVaultService;
use Illuminate\Console\Command;

class VerifyIdentityVault extends Command
{
    protected $signature = 'identity-vault:verify';
    protected $description = 'Check vault schema, key, encrypted storage round-trip, and access auditing using synthetic data';

    public function handle(IdentityVaultService $vault): int
    {
        if (! $vault->isAvailable()) {
            $this->error('Vault connection, schema, or dedicated encryption key is unavailable.');
            return self::FAILURE;
        }
        try {
            $vault->verifyStorage();
        } catch (\Throwable) {
            $this->error('Encrypted storage or access auditing verification failed.');
            return self::FAILURE;
        }
        $this->info('Vault available. Encryption round-trip and audit writes verified; synthetic identity removed.');
        return self::SUCCESS;
    }
}
