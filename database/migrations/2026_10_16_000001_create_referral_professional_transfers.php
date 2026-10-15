<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_professional_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals');
            $table->foreignId('from_professional_id')->constrained('psychology_professionals');
            $table->foreignId('to_professional_id')->constrained('psychology_professionals');
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['referral_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_professional_transfers');
    }
};
