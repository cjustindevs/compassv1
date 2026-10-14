<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_mail_outbox', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('event_key')->unique();
            $t->text('payload');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('available_at');
            $t->timestamp('sent_at')->nullable();
            $t->string('failure_code')->nullable();
            $t->timestamps();
        });
        Schema::table('helper_schedules', function (Blueprint $t) {
            $t->uuid('series_id')->nullable()->index();
            $t->string('duty_notes', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('helper_schedules', fn (Blueprint $t) => $t->dropColumn(['series_id', 'duty_notes']));
        Schema::dropIfExists('staff_mail_outbox');
    }
};
