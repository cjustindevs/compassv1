<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('recovery_email')->nullable();
            $table->string('recovery_email_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['recovery_email_hash']);
            $table->dropColumn(['recovery_email', 'recovery_email_hash']);
        });
    }
};
