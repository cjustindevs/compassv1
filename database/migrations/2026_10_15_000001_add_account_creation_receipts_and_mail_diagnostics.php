<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_creation_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->timestamp('created_at');
        });
        Schema::table('staff_mail_outbox', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_mail_outbox', fn (Blueprint $table) => $table->dropColumn('provider_message_id'));
        Schema::dropIfExists('account_creation_receipts');
    }
};
