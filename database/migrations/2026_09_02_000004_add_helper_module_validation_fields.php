<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readiness_checks', function (Blueprint $table) {
            $table->timestamp('valid_until')->nullable()->after('assessment_date');
        });

        Schema::table('session_reports', function (Blueprint $table) {
            $table->text('observations')->nullable()->after('session_summary');
            $table->text('actions_taken')->nullable()->after('observations');
            $table->enum('risk_level_assessed', ['low', 'moderate', 'high', 'emergency'])->nullable()->after('actions_taken');
            $table->timestamp('documented_at')->nullable()->after('created_date');
            $table->boolean('documentation_late')->default(false)->after('documented_at');
        });
    }

    public function down(): void
    {
        Schema::table('session_reports', function (Blueprint $table) {
            $table->dropColumn(['observations', 'actions_taken', 'risk_level_assessed', 'documented_at', 'documentation_late']);
        });

        Schema::table('readiness_checks', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });
    }
};
