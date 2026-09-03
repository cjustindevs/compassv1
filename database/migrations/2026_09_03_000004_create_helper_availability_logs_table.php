<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helper_availability_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helper_id')->constrained('helpers', 'id')->cascadeOnDelete();
            $table->enum('previous_status', ['available', 'unavailable', 'break']);
            $table->enum('new_status', ['available', 'unavailable', 'break']);
            $table->timestamp('changed_at');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users', 'id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_availability_logs');
    }
};
