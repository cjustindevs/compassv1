<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('queue_requests', function (Blueprint $table) {
            $table->timestamp('first_processed_at')->nullable()->index();
            $table->boolean('is_test_record')->default(false);
            $table->unsignedBigInteger('duplicate_of_id')->nullable();
        });
        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->boolean('is_test_record')->default(false);
            $table->unsignedBigInteger('duplicate_of_id')->nullable();
        });
        Schema::create('queue_assignment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('queue_request_id')->index();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('helper_id')->index();
            $table->string('method', 40);
            $table->boolean('emergency_coordination');
            $table->json('eligibility_evidence');
            $table->timestamp('offered_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('outcome', 30)->default('offered');
        });
        Schema::create('helper_service_intervals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('helper_id')->index();
            $table->unsignedBigInteger('helper_schedule_id');
            $table->unsignedBigInteger('readiness_check_id');
            $table->timestamp('started_at')->index();
            $table->timestamp('valid_until');
            $table->timestamp('ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_service_intervals');
        Schema::dropIfExists('queue_assignment_attempts');
        Schema::table('queue_requests', fn (Blueprint $table) => $table->dropColumn(['first_processed_at', 'is_test_record', 'duplicate_of_id']));
        Schema::table('counseling_sessions', fn (Blueprint $table) => $table->dropColumn(['is_test_record', 'duplicate_of_id']));
    }
};
