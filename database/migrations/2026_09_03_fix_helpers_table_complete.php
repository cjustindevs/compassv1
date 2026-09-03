<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            if (! Schema::hasColumn('helpers', 'status')) {
                $table->enum('status', ['available', 'busy', 'offline'])->default('offline');
            }

            if (! Schema::hasColumn('helpers', 'availability')) {
                $table->enum('availability', ['available', 'unavailable', 'break'])->default('unavailable');
            }

            if (! Schema::hasColumn('helpers', 'available_since')) {
                $table->timestamp('available_since')->nullable();
            }

            if (! Schema::hasColumn('helpers', 'break_started_at')) {
                $table->timestamp('break_started_at')->nullable();
            }

            if (! Schema::hasColumn('helpers', 'is_ready')) {
                $table->boolean('is_ready')->default(false);
            }

            if (! Schema::hasColumn('helpers', 'last_readiness_at')) {
                $table->timestamp('last_readiness_at')->nullable();
            }

            if (! Schema::hasColumn('helpers', 'competency_score')) {
                $table->decimal('competency_score', 3, 2)->default(2.50);
            }

            if (! Schema::hasColumn('helpers', 'competency_level')) {
                $table->unsignedTinyInteger('competency_level')->default(1);
            }

            if (! Schema::hasColumn('helpers', 'competency_risk_level')) {
                $table->unsignedTinyInteger('competency_risk_level')->default(2);
            }

            if (! Schema::hasColumn('helpers', 'max_concurrent_sessions')) {
                $table->unsignedTinyInteger('max_concurrent_sessions')->default(2);
            }

            if (! Schema::hasColumn('helpers', 'active_sessions_count')) {
                $table->unsignedInteger('active_sessions_count')->default(0);
            }

            if (! Schema::hasColumn('helpers', 'total_sessions_handled')) {
                $table->unsignedInteger('total_sessions_handled')->default(0);
            }

            if (! Schema::hasColumn('helpers', 'current_shift_sessions')) {
                $table->unsignedInteger('current_shift_sessions')->default(0);
            }

            if (! Schema::hasColumn('helpers', 'feedback_count')) {
                $table->unsignedInteger('feedback_count')->default(0);
            }

            if (! Schema::hasColumn('helpers', 'avg_rating')) {
                $table->decimal('avg_rating', 3, 2)->default(0);
            }

            if (! Schema::hasColumn('helpers', 'is_under_review')) {
                $table->boolean('is_under_review')->default(false);
            }

            if (! Schema::hasColumn('helpers', 'review_reason')) {
                $table->text('review_reason')->nullable();
            }

            if (! Schema::hasColumn('helpers', 'languages')) {
                $table->json('languages')->nullable();
            }

            if (! Schema::hasColumn('helpers', 'specialties')) {
                $table->json('specialties')->nullable();
            }

            if (! Schema::hasColumn('helpers', 'default_shift_start')) {
                $table->time('default_shift_start')->nullable();
            }

            if (! Schema::hasColumn('helpers', 'default_shift_end')) {
                $table->time('default_shift_end')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            $columns = [
                'availability',
                'available_since',
                'break_started_at',
                'is_ready',
                'last_readiness_at',
                'competency_score',
                'competency_risk_level',
                'active_sessions_count',
                'total_sessions_handled',
                'current_shift_sessions',
                'feedback_count',
                'avg_rating',
                'is_under_review',
                'review_reason',
                'languages',
                'specialties',
                'default_shift_start',
                'default_shift_end',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('helpers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
