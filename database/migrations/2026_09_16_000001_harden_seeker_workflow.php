<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['Stress','Family','Relationships','Academic','Health','Others'] as $name) {
            if (!\Illuminate\Support\Facades\DB::table('concern_categories')->where('concern_name',$name)->exists())
                \Illuminate\Support\Facades\DB::table('concern_categories')->insert(['concern_name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
        }
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Preserve existing risk checks; PostgreSQL cannot ALTER TYPE with an inline CHECK.
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE counseling_sessions ALTER COLUMN risk_level DROP NOT NULL, ALTER COLUMN risk_level DROP DEFAULT');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE screening_responses ALTER COLUMN risk_level DROP NOT NULL');
        }
        Schema::table('referrals', fn(Blueprint $t)=>$t->unsignedBigInteger('helper_id')->nullable()->change());
        Schema::table('counseling_sessions', function (Blueprint $t) {
            if (Schema::getConnection()->getDriverName() !== 'pgsql') $t->enum('risk_level',['low','moderate','high','emergency'])->nullable()->default(null)->change();
            $t->string('workflow_state')->nullable()->index();
            $t->timestamp('helper_accepted_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamp('warning_sent_at')->nullable();
            $t->timestamp('peer_support_approved_at')->nullable();
            $t->foreignId('review_adviser_id')->nullable()->constrained('advisers');
        });
        Schema::table('screening_responses', function (Blueprint $t) {
            if (Schema::getConnection()->getDriverName() !== 'pgsql') $t->enum('risk_level',['low','moderate','high','emergency'])->nullable()->change();
            $t->string('instrument_version')->nullable();
            $t->string('rule_code')->nullable();
            $t->string('review_status')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('evidence_source')->nullable();
        });
        Schema::table('consent_records', function (Blueprint $t) {
            $t->string('purpose')->nullable()->index();
            $t->string('decision')->nullable();
            $t->foreignId('session_id')->nullable()->constrained('counseling_sessions');
            $t->foreignId('referral_id')->nullable()->constrained('referrals');
        });
        Schema::table('queue_requests', function (Blueprint $t) {
            $t->unsignedInteger('wait_urgency')->default(0);
            foreach (['queued_at','matching_started_at','helper_proposed_at','helper_accepted_at','helper_declined_at','assigned_at','cancelled_at','expired_at','completed_at'] as $column) $t->timestamp($column)->nullable();
        });
        Schema::table('help_seeker_evaluations', function (Blueprint $t) {
            $t->json('answers')->nullable();
            $t->string('instrument_version')->nullable();
            $t->timestamp('submitted_at')->nullable();
        });
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->string('target_type')->nullable(); $t->unsignedBigInteger('target_id')->nullable();
            $t->string('outcome')->default('success'); $t->json('metadata')->nullable();
        });
        Schema::table('emergency_alerts', function (Blueprint $t) {
            $t->foreignId('screening_id')->nullable()->constrained('screening_responses');
            $t->string('rule_code')->nullable(); $t->json('resource_ids')->nullable();
            $t->string('notification_result')->nullable();
        });
        Schema::create('request_status_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('session_id')->constrained('counseling_sessions');
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('from_state')->nullable(); $t->string('to_state');
            $t->timestamp('occurred_at');
        });
        Schema::create('helper_conflicts', function (Blueprint $t) {
            $t->id(); $t->foreignId('helper_id')->constrained('helpers');
            $t->foreignId('seeker_id')->constrained('help_seekers');
            $t->foreignId('reported_by')->constrained('users');
            $t->timestamp('created_at'); $t->unique(['helper_id','seeker_id']);
        });
    }
    public function down(): void {
        // Preserve referrals without a helper on rollback; narrowing this column would destroy evidence.

        Schema::dropIfExists('helper_conflicts'); Schema::dropIfExists('request_status_events');
        Schema::table('emergency_alerts', fn (Blueprint $t) => $t->dropConstrainedForeignId('screening_id'));
        Schema::table('emergency_alerts', fn (Blueprint $t) => $t->dropColumn(['rule_code','resource_ids','notification_result']));
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropColumn(['target_type','target_id','outcome','metadata']));
        Schema::table('help_seeker_evaluations', fn (Blueprint $t) => $t->dropColumn(['answers','instrument_version','submitted_at']));
        Schema::table('queue_requests', fn (Blueprint $t) => $t->dropColumn(['wait_urgency','queued_at','matching_started_at','helper_proposed_at','helper_accepted_at','helper_declined_at','assigned_at','cancelled_at','expired_at','completed_at']));
        Schema::table('consent_records', function (Blueprint $t) { $t->dropConstrainedForeignId('session_id'); $t->dropConstrainedForeignId('referral_id'); $t->dropColumn(['purpose','decision']); });
        Schema::table('screening_responses', function (Blueprint $t) { $t->dropConstrainedForeignId('actor_id'); $t->dropColumn(['instrument_version','rule_code','review_status','evidence_source']); });
        Schema::table('counseling_sessions', function (Blueprint $t) { $t->dropConstrainedForeignId('review_adviser_id'); $t->dropColumn(['workflow_state','helper_accepted_at','submitted_at','cancelled_at','expired_at','warning_sent_at','peer_support_approved_at']); });
    }
};
