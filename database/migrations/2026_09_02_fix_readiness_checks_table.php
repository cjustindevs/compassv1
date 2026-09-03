<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defensive migration: ensure every column the readiness feature reads
     * exists on readiness_checks. Existing installations may be missing some
     * columns if earlier migrations were never run against a given database.
     */
    public function up(): void
    {
        if (! Schema::hasTable('readiness_checks')) {
            return;
        }

        Schema::table('readiness_checks', function (Blueprint $table) {
            if (! Schema::hasColumn('readiness_checks', 'physical_condition')) {
                $table->string('physical_condition')->nullable()->after('stress_level');
            }
            if (! Schema::hasColumn('readiness_checks', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('readiness_checks', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('readiness_checks', 'validated_at')) {
                $table->timestamp('validated_at')->nullable();
            }
            if (! Schema::hasColumn('readiness_checks', 'validated_by')) {
                $table->unsignedBigInteger('validated_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('readiness_checks')) {
            return;
        }

        Schema::table('readiness_checks', function (Blueprint $table) {
            foreach (['physical_condition', 'notes', 'is_active', 'validated_at', 'validated_by'] as $column) {
                if (Schema::hasColumn('readiness_checks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
