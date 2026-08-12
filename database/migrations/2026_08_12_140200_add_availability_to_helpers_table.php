<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            $table->enum('status', ['available', 'busy', 'offline'])->default('offline')->after('email');
            $table->unsignedTinyInteger('competency_level')->default(1)->after('status');
            $table->unsignedTinyInteger('max_concurrent_sessions')->default(2)->after('competency_level');
            $table->string('specializations', 500)->nullable()->after('max_concurrent_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            $table->dropColumn(['status', 'competency_level', 'max_concurrent_sessions', 'specializations']);
        });
    }
};