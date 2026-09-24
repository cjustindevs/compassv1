<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            $table->unsignedTinyInteger('non_response_count')->default(0)->after('review_reason');
        });
    }

    public function down(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            $table->dropColumn('non_response_count');
        });
    }
};