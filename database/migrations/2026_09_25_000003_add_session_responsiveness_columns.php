<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('counseling_sessions', 'last_helper_message_at')) {
                $table->timestamp('last_helper_message_at')->nullable()->after('warning_sent_at');
            }
            if (! Schema::hasColumn('counseling_sessions', 'no_response_escalated_at')) {
                $table->timestamp('no_response_escalated_at')->nullable()->after('last_helper_message_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            foreach (['last_helper_message_at', 'no_response_escalated_at'] as $column) {
                if (Schema::hasColumn('counseling_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};