<?php

namespace App\Console\Commands;

use App\Models\HelpSeeker;
use App\Services\IdentityVaultService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateLegacyIdentity extends Command
{
    protected $signature = 'identity-vault:migrate-legacy';
    protected $description = 'Encrypt and verify legacy seeker identity before removing it from operational records';

    public function handle(IdentityVaultService $vault): int
    {
        $count = 0;
        try {
            // Check both vault tables before changing any operational record.
            DB::connection('identity_vault')->table('idv_identities')->count();
            DB::connection('identity_vault')->table('idv_access_logs')->count();
            HelpSeeker::orderBy('id')->chunkById(100, function ($seekers) use ($vault, &$count) {
                foreach ($seekers as $seeker) {
                    DB::transaction(function () use ($seeker, $vault, &$count) {
                        $seeker = HelpSeeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
                        $user = $seeker->user()->lockForUpdate()->first();
                        $legacy = DB::table('identity_vault')->where('seeker_id', $seeker->id)->lockForUpdate()->first();
                        $realEmail = $user && ! str_ends_with(strtolower($user->email), '@compass.local') ? $user->email : null;
                        $realName = $user && $user->name !== $seeker->generated_alias ? $user->name : null;
                        $data = ['real_name' => $legacy?->real_name ?: $realName, 'email' => $legacy?->email ?: $realEmail,
                            'phone_number' => $legacy?->phone_number, 'address' => $legacy?->address];
                        if (! array_filter($data)) return;
                        // Differing legacy/account values require reconciliation, never silent deletion.
                        if (($legacy?->email && $realEmail && $legacy->email !== $realEmail)
                            || ($legacy?->real_name && $realName && $legacy->real_name !== $realName)) {
                            throw new \RuntimeException('Legacy identity conflict.');
                        }
                        if (! $seeker->pseudo_id) $seeker->update(['pseudo_id' => 'PS-' . Str::upper(Str::random(12))]);
                        $vault->importLegacy($seeker, $data);
                        // Import has decrypted and compared every field and committed its audit.
                        if ($user) $user->update(['name' => $seeker->generated_alias, 'email' => strtolower($seeker->generated_alias) . '@compass.local']);
                        if ($legacy) DB::table('identity_vault')->where('seeker_id', $seeker->id)->delete();
                        $count++;
                    });
                }
            });
        } catch (\Throwable) {
            $this->error('Migration stopped safely. Check vault connectivity or reconcile conflicting legacy records. Unverified source identity was retained.');
            return self::FAILURE;
        }
        $this->info('Seeker identities encrypted, verified, and removed from operational identity fields: ' . $count);
        return self::SUCCESS;
    }
}
