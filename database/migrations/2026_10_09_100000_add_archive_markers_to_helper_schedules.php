<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->unsignedBigInteger('archived_by')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('helper_schedules', fn (Blueprint $table) => $table->dropColumn(['archived_at', 'archived_by']));
    }
};
