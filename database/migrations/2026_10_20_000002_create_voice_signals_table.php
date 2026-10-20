<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voice_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('call_id')->constrained('voice_calls')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('payload');
            $table->timestamp('expires_at')->index();
            $table->index(['call_id', 'recipient_id', 'id']);
        });
    }

    public function down(): void { Schema::dropIfExists('voice_signals'); }
};
