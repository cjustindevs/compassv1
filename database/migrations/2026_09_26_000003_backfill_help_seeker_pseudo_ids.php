<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Help seekers registered before the Identity Vault existed have no
 * pseudo_id, and the vault keys every identity record by it. Without a
 * backfill those seekers receive a 503 the moment a referral is approved and
 * they try to submit their contact details.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('help_seekers', 'pseudo_id')) {
            return;
        }

        // Materialise the ids first. Chunking while rewriting the column that
        // the WHERE clause filters on would shift the offset and silently skip
        // rows part-way through the backfill.
        $ids = DB::table('help_seekers')
            ->where(fn ($query) => $query->whereNull('pseudo_id')->orWhere('pseudo_id', ''))
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::table('help_seekers')
                ->where('id', $id)
                ->update(['pseudo_id' => $this->uniquePseudoId()]);
        }
    }

    /**
     * pseudo_id carries a unique index, so a generated value that already
     * exists must be discarded rather than aborting the whole backfill.
     */
    private function uniquePseudoId(): string
    {
        do {
            $candidate = 'PS-'.Str::upper(Str::random(12));
        } while (DB::table('help_seekers')->where('pseudo_id', $candidate)->exists());

        return $candidate;
    }

    public function down(): void
    {
        // Generated identifiers are the only key to the stored identity data,
        // so they are never removed on rollback.
    }
};
