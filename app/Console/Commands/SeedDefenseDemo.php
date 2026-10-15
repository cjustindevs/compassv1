<?php

namespace App\Console\Commands;

use App\Services\DefenseDemoData;
use Illuminate\Console\Command;
use Throwable;

class SeedDefenseDemo extends Command
{
    protected $signature = 'compass:seed-defense {--apply : Commit the one-time additive demo batch}';

    protected $description = 'Preview or add historical defense sample records using existing accounts, without sending notifications';

    public function handle(DefenseDemoData $data): int
    {
        try {
            if (! $this->option('apply')) {
                $this->info('Preview only: '.DefenseDemoData::BATCH);
                $this->line('40 completed sessions, 80 sample messages, 40 reports/ratings, 10 completed referrals/appointments/reviews and 4 resolved emergencies.');
                $this->line('Existing accounts, records, duty, readiness, verification, competency and general consents are unchanged. No email or notification is sent.');
                $this->line($data->existing() ? 'This batch already exists; applying again will skip it.' : 'Nothing written. Use --apply to add this batch.');

                return self::SUCCESS;
            }
            $result = $data->import();
            $this->info($result['created'] ? 'Defense demo batch added: '.DefenseDemoData::BATCH : 'Defense demo batch already exists; skipped.');
            $this->table(['Table', 'Added in this batch'], collect($result['counts'])->map(fn ($count, $table) => [$table, $count])->values()->all());

            return self::SUCCESS;
        } catch (Throwable $error) {
            // SQL exceptions can include bindings. Never send account data or credentials to startup logs.
            $this->error('Defense import could not finish. No partial batch was committed. Check database access, current migrations and existing active role profiles.');

            return self::FAILURE;
        }
    }
}
