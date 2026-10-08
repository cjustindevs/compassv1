<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('emergency_alerts')->whereIn('status', ['resolved', 'closed'])->orderBy('id')->chunkById(100, function ($alerts) {
            foreach ($alerts as $alert) {
                if (! $alert->session_id || DB::table('emergency_alerts')->where('session_id', $alert->session_id)->whereNotIn('status', ['resolved', 'closed'])->exists()) continue;
                DB::table('incident_reports')->where('session_id', $alert->session_id)
                    ->whereIn('incident_category', ['emergency_flag', 'classification_emergency'])
                    ->whereIn('status', ['open', 'under_review', 'escalated'])
                    ->update(['status' => 'resolved', 'resolved_at' => $alert->resolved_at ?? $alert->updated_at,
                        'resolution_summary' => $alert->resolution_notes, 'updated_at' => now()]);
            }
        });
    }
    public function down(): void
    {
        // Do not reopen resolved safety records on rollback.
    }
};
