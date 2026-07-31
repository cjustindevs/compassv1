<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_vault', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->constrained('help_seekers', 'id')->onDelete('cascade');
            $table->string('real_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->timestamp('released_date')->nullable();
            $table->string('released_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('advisers', 'id')->onDelete('set null');
            $table->boolean('emergency_override')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_vault');
    }
};