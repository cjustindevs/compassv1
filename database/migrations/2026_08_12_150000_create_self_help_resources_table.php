<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_help_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category'); // meditation, exercise, article, tool, video
            $table->text('content')->nullable();
            $table->string('icon')->nullable(); // emoji or font-awesome icon
            $table->string('duration')->nullable(); // '5 min', '10 min'
            $table->string('difficulty')->default('beginner'); // beginner, intermediate, advanced
            $table->json('tags')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->integer('views_count')->default(0);
            $table->integer('saved_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_help_resources');
    }
};