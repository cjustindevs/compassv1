<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'identity_vault';

    public function up(): void
    {
        Schema::connection('identity_vault')->create('idv_access_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->string('pseudo_id', 50)->index();
            $table->string('accessed_by_user_id')->nullable();
            $table->string('accessed_by_role')->nullable();
            $table->text('accessed_by_ip')->nullable();
            $table->text('accessed_by_user_agent')->nullable();
            $table->string('action', 50);
            $table->text('access_reason')->nullable();
            $table->text('access_notes')->nullable();
            $table->string('authorized_by')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->string('access_status', 20);
            $table->string('access_result')->nullable();
            $table->timestamp('accessed_at')->useCurrent();
            $table->timestamps();
            $table->index(['pseudo_id', 'accessed_at']);
            $table->index(['accessed_by_user_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('identity_vault')->dropIfExists('idv_access_logs');
    }
};
