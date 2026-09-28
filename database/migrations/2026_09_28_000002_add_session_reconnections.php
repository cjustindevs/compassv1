<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $t) {
            $t->timestamp('helper_heartbeat_at')->nullable();
        });
        Schema::create('session_reconnections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('session_id')->constrained('counseling_sessions');
            $t->foreignId('original_helper_id')->constrained('helpers');
            $t->foreignId('offered_helper_id')->nullable()->constrained('helpers');
            $t->foreignId('continuation_id')->nullable()->constrained('counseling_sessions');
            $t->string('status')->default('interrupted');
            $t->timestamp('detected_at');
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('offered_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reconnections');
        Schema::table('counseling_sessions', fn (Blueprint $t) => $t->dropColumn('helper_heartbeat_at'));
    }
};
