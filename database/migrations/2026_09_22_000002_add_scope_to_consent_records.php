<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_records', function (Blueprint $table) {
            $table->string('scope', 100)->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('consent_records', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};