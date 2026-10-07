<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->foreignId('support_emergency_alert_id')->nullable()->constrained('emergency_alerts')->restrictOnDelete();
        });
    }
    public function down(): void {
        Schema::table('counseling_sessions', fn (Blueprint $table) => $table->dropConstrainedForeignId('support_emergency_alert_id'));
    }
};
