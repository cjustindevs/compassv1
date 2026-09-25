<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('referral_appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referral_id')->constrained('referrals');
            $t->foreignId('professional_id')->constrained('psychology_professionals');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('starts_at'); $t->timestamp('ends_at');
            $t->text('meeting_details'); $t->string('status')->default('scheduled');
            $t->foreignId('replaces_id')->nullable()->constrained('referral_appointments');
            $t->timestamps(); $t->index(['professional_id','status','starts_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('referral_appointments'); }
};
