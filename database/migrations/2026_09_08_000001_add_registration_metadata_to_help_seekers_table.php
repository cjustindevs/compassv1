<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_seekers', function (Blueprint $table) {
            $table->string('pseudo_id', 50)->nullable()->unique();
            $table->ipAddress('registration_ip')->nullable()->index();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('help_seekers', function (Blueprint $table) {
            $table->dropUnique(['pseudo_id']);
            $table->dropIndex(['registration_ip']);
            $table->dropColumn(['pseudo_id', 'registration_ip', 'is_verified', 'verified_at']);
        });
    }
};
