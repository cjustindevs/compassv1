<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('helpers')->where('is_under_review', true)->orderBy('id')->chunkById(100, function ($helpers) {
            foreach ($helpers as $helper) {
                // Match only the exact automatically generated missed-offer reason.
                // Other supervision or safety restrictions must remain in place.
                if (!preg_match('/^Did not respond to \d+ consecutive session recommendations within the \d+-minute brief\.$/', (string) $helper->review_reason)) continue;
                DB::transaction(function () use ($helper) {
                    $locked = DB::table('helpers')->where('id', $helper->id)->lockForUpdate()->first();
                    if (!$locked->is_under_review || $locked->review_reason !== $helper->review_reason) return;
                    DB::table('audit_logs')->insert([
                        'user_account_id' => null, 'action' => 'helper_missed_offer_restriction_removed',
                        'module' => 'matching', 'description' => 'Missed-offer matching restriction removed by policy update.',
                        'target_type' => 'helpers', 'target_id' => $helper->id, 'outcome' => 'success',
                        'metadata' => json_encode(['previous_reason' => $locked->review_reason, 'non_response_count' => $locked->non_response_count]),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('helpers')->where('id', $helper->id)->update(['is_under_review' => false, 'review_reason' => null, 'updated_at' => now()]);
                });
            }
        });
    }

    public function down(): void
    {
        // Policy rollback must not silently reimpose restrictions on Helpers.
        // Historical missed offers and the removal audit are preserved.
    }
};
