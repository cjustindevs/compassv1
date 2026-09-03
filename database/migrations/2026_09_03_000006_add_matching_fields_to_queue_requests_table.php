<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_requests', 'aging_priority_increases')) {
                $table->unsignedInteger('aging_priority_increases')->default(0)->after('estimated_wait');
            }
            if (! Schema::hasColumn('queue_requests', 'last_priority_increase_at')) {
                $table->timestamp('last_priority_increase_at')->nullable()->after('aging_priority_increases');
            }
            if (! Schema::hasColumn('queue_requests', 'max_wait_reached')) {
                $table->boolean('max_wait_reached')->default(false)->after('last_priority_increase_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_requests', function (Blueprint $table) {
            foreach (['aging_priority_increases', 'last_priority_increase_at', 'max_wait_reached'] as $column) {
                if (Schema::hasColumn('queue_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
