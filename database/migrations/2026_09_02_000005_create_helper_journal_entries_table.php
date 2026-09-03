<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A private journal for helpers to jot down thoughts/feelings while
     * they are not ready to take sessions (or anytime they want).
     */
    public function up(): void
    {
        Schema::create('helper_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('helper_id');
            $table->string('mood')->nullable();
            $table->text('content');
            $table->timestamps();

            $table->foreign('helper_id')->references('id')->on('helpers')->onDelete('cascade');
            $table->index('helper_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_journal_entries');
    }
};
