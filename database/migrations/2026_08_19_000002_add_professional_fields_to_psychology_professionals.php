<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends psychology_professionals with the clinical license, contact
     * and availability fields used by the professional module.
     */
    public function up(): void
    {
        Schema::table('psychology_professionals', function (Blueprint $table) {
            $table->string('license_number')->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_available')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('psychology_professionals', function (Blueprint $table) {
            $table->dropColumn(['license_number', 'phone', 'is_available']);
        });
    }
};
