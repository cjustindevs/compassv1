<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Repair installations whose migration ledger and actual schema differ.
        if (! Schema::hasColumn('referrals', 'recommendation_form')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->text('recommendation_form')->nullable();
            });
        }

        if (! Schema::hasTable('supervision_record_versions')) {
            Schema::create('supervision_record_versions', function (Blueprint $table) {
                $table->id();
                $table->string('record_type');
                $table->unsignedBigInteger('record_id');
                $table->unsignedInteger('version');
                $table->foreignId('actor_id')->nullable()->constrained('users');
                $table->text('reason');
                $table->json('snapshot');
                $table->timestamp('created_at');
                $table->unique(['record_type', 'record_id', 'version'], 'supervision_version_unique');
            });
        }
    }

    public function down(): void
    {
        // These structures belong to earlier migrations. Preserve existing
        // recommendations and audit history when rolling back this repair.
    }
};
