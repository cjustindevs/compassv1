<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track whether the helper completed or skipped the guided
     * breathing exercise before submitting the readiness check.
     */
    public function up(): void
    {
        Schema::table('readiness_checks', function (Blueprint $table) {
            $table->string('breathing_exercise')->nullable()->after('assessment_date');
        });
    }

    public function down(): void
    {
        Schema::table('readiness_checks', function (Blueprint $table) {
            $table->dropColumn('breathing_exercise');
        });
    }
};