<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('counseling_sessions', 'matching_details')) {
                $table->json('matching_details')->nullable()->after('matched_by');
            }
            if (! Schema::hasColumn('counseling_sessions', 'transcript_verified')) {
                $table->boolean('transcript_verified')->default(false)->after('abandoned_at');
            }
            if (! Schema::hasColumn('counseling_sessions', 'transcript_verified_by')) {
                $table->foreignId('transcript_verified_by')->nullable()->after('transcript_verified')->constrained('advisers', 'id')->nullOnDelete();
            }
            if (! Schema::hasColumn('counseling_sessions', 'transcript_verified_at')) {
                $table->timestamp('transcript_verified_at')->nullable()->after('transcript_verified_by');
            }
            if (! Schema::hasColumn('counseling_sessions', 'transcript_generated_at')) {
                $table->timestamp('transcript_generated_at')->nullable()->after('transcript_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('counseling_sessions', 'transcript_verified_by')) {
                $table->dropForeign(['transcript_verified_by']);
            }

            foreach (['matching_details', 'transcript_verified', 'transcript_verified_by', 'transcript_verified_at', 'transcript_generated_at'] as $column) {
                if (Schema::hasColumn('counseling_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
