<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Canonical 3-state theme: light | dark | system
            $table->string('theme_preference')->default('light')->after('dark_mode');
            // Opt-out of non-essential animations
            $table->boolean('reduced_motion')->default(false)->after('high_contrast');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theme_preference', 'reduced_motion']);
        });
    }
};
