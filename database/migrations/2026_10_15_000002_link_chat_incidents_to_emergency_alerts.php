<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', fn (Blueprint $table) => $table->foreignId('emergency_alert_id')->nullable()->constrained('emergency_alerts')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('incident_reports', fn (Blueprint $table) => $table->dropConstrainedForeignId('emergency_alert_id'));
    }
};
