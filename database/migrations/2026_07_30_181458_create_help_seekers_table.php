<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_seekers', function (Blueprint $table) {
            $table->id(); // Primary key 'id' (Laravel default)
            $table->foreignId('user_account_id')->constrained('users', 'id')->onDelete('cascade');
            $table->string('generated_alias')->unique();
            $table->integer('age')->nullable();
            $table->string('gender')->nullable();
            $table->timestamp('account_created')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_seekers');
    }
};