<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_reports', function (Blueprint $table) {
            // Reflection and summary are submitted independently, so a report
            // may exist with only the personal reflection completed.
            $table->text('session_summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('session_reports', function (Blueprint $table) {
            $table->text('session_summary')->nullable(false)->change();
        });
    }
};
