<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('supervision_record_versions', function (Blueprint $t) {
            $t->id(); $t->string('record_type'); $t->unsignedBigInteger('record_id');
            $t->unsignedInteger('version'); $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->text('reason'); $t->json('snapshot'); $t->timestamp('created_at');
            $t->unique(['record_type','record_id','version'], 'supervision_version_unique');
        });
        Schema::table('helper_competency_history', function (Blueprint $t) {
            $t->foreignId('report_id')->nullable()->constrained('session_reports');
            $t->string('rubric_version')->nullable(); $t->json('evidence')->nullable();
            $t->unique('report_id');
        });
        Schema::table('adviser_feedback', function (Blueprint $t) { $t->date('follow_up_date')->nullable(); $t->boolean('acknowledgment_required')->default(true); });
        Schema::create('training_recommendations', function (Blueprint $t) {
            $t->id(); $t->foreignId('helper_id')->constrained('helpers'); $t->foreignId('adviser_id')->constrained('advisers');
            $t->foreignId('evaluation_id')->constrained('helper_competency_history');
            $t->string('criterion'); $t->text('reason'); $t->text('activity'); $t->string('priority')->default('normal');
            $t->date('due_date')->nullable(); $t->string('status')->default('assigned');
            $t->timestamp('acknowledged_at')->nullable(); $t->timestamp('completed_at')->nullable();
            $t->timestamp('reviewed_at')->nullable(); $t->text('completion_evidence')->nullable(); $t->text('review_notes')->nullable();
            $t->timestamps(); $t->index(['helper_id','status']);
        });
        Schema::create('emergency_review_actions', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_alert_id')->constrained('emergency_alerts');
            $t->foreignId('actor_id')->constrained('users'); $t->string('action'); $t->text('notes'); $t->timestamp('created_at');
        });
        Schema::table('emergency_alerts', function (Blueprint $t) { $t->timestamp('acknowledged_at')->nullable(); $t->foreignId('acknowledged_by')->nullable()->constrained('users'); });
        Schema::table('referrals', function (Blueprint $t) { $t->timestamp('clarification_requested_at')->nullable(); $t->timestamp('clarification_received_at')->nullable(); $t->text('clarification_question')->nullable(); $t->text('clarification_response')->nullable(); });
        foreach (['self_help_resources','emergency_resources'] as $table) Schema::table($table, function (Blueprint $t) {
            $t->string('visibility')->default('public'); $t->date('review_date')->nullable(); $t->timestamp('archived_at')->nullable();
            $t->foreignId('managed_by')->nullable()->constrained('users');
        });
    }
    public function down(): void {
        foreach (['self_help_resources','emergency_resources'] as $table) Schema::table($table, function (Blueprint $t) { $t->dropConstrainedForeignId('managed_by'); $t->dropColumn(['visibility','review_date','archived_at']); });
        Schema::table('referrals', fn(Blueprint $t)=>$t->dropColumn(['clarification_requested_at','clarification_received_at','clarification_question','clarification_response']));
        Schema::table('emergency_alerts', function (Blueprint $t) { $t->dropConstrainedForeignId('acknowledged_by'); $t->dropColumn('acknowledged_at'); });
        Schema::dropIfExists('emergency_review_actions'); Schema::dropIfExists('training_recommendations');
        Schema::table('adviser_feedback', fn(Blueprint $t)=>$t->dropColumn(['follow_up_date','acknowledgment_required']));
        Schema::table('helper_competency_history', function (Blueprint $t) { $t->dropUnique(['report_id']); $t->dropConstrainedForeignId('report_id'); $t->dropColumn(['rubric_version','evidence']); });
        Schema::dropIfExists('supervision_record_versions');
    }
};
