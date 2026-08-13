<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_reports', function (Blueprint $table) {
            $table->json('skills_applied')->nullable()->after('personal_reflection');
        });

        Schema::table('helpers', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('specializations');
            $table->string('phone', 30)->nullable()->after('bio');
            $table->string('preferred_language', 50)->default('English')->after('phone');
            $table->string('timezone', 60)->nullable()->after('preferred_language');
        });
    }

    public function down(): void
    {
        Schema::table('session_reports', function (Blueprint $table) {
            $table->dropColumn('skills_applied');
        });

        Schema::table('helpers', function (Blueprint $table) {
            $table->dropColumn(['bio', 'phone', 'preferred_language', 'timezone']);
        });
    }
};