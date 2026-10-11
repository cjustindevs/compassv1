<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seeker_request_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->unique()->constrained('help_seekers')->cascadeOnDelete();
            $table->string('instrument_version');
            $table->string('stage');
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions')->cascadeOnDelete();
            $table->text('payload');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->string('preferred_language')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seeker_request_drafts');
        Schema::table('counseling_sessions', fn (Blueprint $table) => $table->dropColumn('preferred_language'));
    }
};
