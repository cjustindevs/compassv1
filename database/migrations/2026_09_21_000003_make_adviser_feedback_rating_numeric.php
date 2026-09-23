<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The weighted rubric score is a decimal (e.g. 3.35, 4.7); the column
     * was created as integer, which Postgres rejects.
     */
    public function up(): void
    {
        Schema::table('adviser_feedback', function (Blueprint $table) {
            $table->decimal('competency_rating', 5, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('adviser_feedback', function (Blueprint $table) {
            $table->integer('competency_rating')->nullable()->change();
        });
    }
};