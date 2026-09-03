<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helper_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helper_id')->constrained('helpers', 'id')->cascadeOnDelete();
            $table->string('category');
            $table->unsignedTinyInteger('proficiency_level')->default(1);
            $table->unsignedInteger('sessions_handled')->default(0);
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->boolean('verified_by_adviser')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('advisers', 'id')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['helper_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_specialties');
    }
};
