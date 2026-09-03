<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helper_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helper_id')->constrained('helpers', 'id')->cascadeOnDelete();
            $table->date('date');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_pattern')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_exception')->default(false);
            $table->text('exception_reason')->nullable();
            $table->foreignId('created_by')->constrained('users', 'id')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('advisers', 'id')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['helper_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_schedules');
    }
};
