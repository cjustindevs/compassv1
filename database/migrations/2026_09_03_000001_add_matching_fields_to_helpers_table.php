<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            if (! Schema::hasColumn('helpers', 'competency_score')) {
                $table->decimal('competency_score', 3, 2)->default(2.50)->after('competency_level');
            }
            if (! Schema::hasColumn('helpers', 'competency_risk_level')) {
                $table->unsignedTinyInteger('competency_risk_level')->default(2)->after('competency_score');
            }
            if (! Schema::hasColumn('helpers', 'availability')) {
                $table->enum('availability', ['available', 'unavailable', 'break'])->default('unavailable')->after('status');
            }
            if (! Schema::hasColumn('helpers', 'available_since')) {
                $table->timestamp('available_since')->nullable()->after('availability');
            }
            if (! Schema::hasColumn('helpers', 'break_started_at')) {
                $table->timestamp('break_started_at')->nullable()->after('available_since');
            }
            if (! Schema::hasColumn('helpers', 'is_ready')) {
                $table->boolean('is_ready')->default(false)->after('break_started_at');
            }
            if (! Schema::hasColumn('helpers', 'last_readiness_at')) {
                $table->timestamp('last_readiness_at')->nullable()->after('is_ready');
            }
            if (! Schema::hasColumn('helpers', 'languages')) {
                $table->json('languages')->nullable()->after('preferred_language');
            }
            if (! Schema::hasColumn('helpers', 'active_sessions_count')) {
                $table->unsignedInteger('active_sessions_count')->default(0)->after('max_concurrent_sessions');
            }
            if (! Schema::hasColumn('helpers', 'total_sessions_handled')) {
                $table->unsignedInteger('total_sessions_handled')->default(0)->after('active_sessions_count');
            }
            if (! Schema::hasColumn('helpers', 'current_shift_sessions')) {
                $table->unsignedInteger('current_shift_sessions')->default(0)->after('total_sessions_handled');
            }
            if (! Schema::hasColumn('helpers', 'feedback_count')) {
                $table->unsignedInteger('feedback_count')->default(0)->after('current_shift_sessions');
            }
            if (! Schema::hasColumn('helpers', 'avg_rating')) {
                $table->decimal('avg_rating', 3, 2)->default(0)->after('feedback_count');
            }
            if (! Schema::hasColumn('helpers', 'default_shift_start')) {
                $table->time('default_shift_start')->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('helpers', 'default_shift_end')) {
                $table->time('default_shift_end')->nullable()->after('default_shift_start');
            }
            if (! Schema::hasColumn('helpers', 'is_under_review')) {
                $table->boolean('is_under_review')->default(false)->after('default_shift_end');
            }
            if (! Schema::hasColumn('helpers', 'review_reason')) {
                $table->text('review_reason')->nullable()->after('is_under_review');
            }
            if (! Schema::hasColumn('helpers', 'specialties')) {
                $table->json('specialties')->nullable()->after('specializations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            $columns = [
                'competency_score',
                'competency_risk_level',
                'availability',
                'available_since',
                'break_started_at',
                'is_ready',
                'last_readiness_at',
                'languages',
                'active_sessions_count',
                'total_sessions_handled',
                'current_shift_sessions',
                'feedback_count',
                'avg_rating',
                'default_shift_start',
                'default_shift_end',
                'is_under_review',
                'review_reason',
                'specialties',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('helpers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
