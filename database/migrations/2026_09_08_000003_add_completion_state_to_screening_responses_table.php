<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_responses', function (Blueprint $table) {
            $table->boolean('is_active')->default(false);
            $table->boolean('is_complete')->default(false);
            $table->index(['seeker_id', 'is_active', 'is_complete'], 'screening_current_completion_index');
        });
    }

    public function down(): void
    {
        Schema::table('screening_responses', function (Blueprint $table) {
            $table->dropIndex('screening_current_completion_index');
            $table->dropColumn(['is_active', 'is_complete']);
        });
    }
};
