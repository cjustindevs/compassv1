<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'identity_vault';

    public function up(): void
    {
        Schema::connection('identity_vault')->create('idv_release_records', function (Blueprint $table) {
            $table->id('release_id');
            $table->string('pseudo_id', 50)->index();
            $table->unsignedBigInteger('identity_id');
            $table->unsignedInteger('identity_version')->default(1);
            $table->foreign('identity_id')->references('identity_id')->on('idv_identities');
            // Main-database IDs are opaque references, deliberately without cross-database FKs.
            $table->unsignedBigInteger('referral_id')->nullable()->index();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('released_to_user_id');
            $table->string('released_to_role');
            $table->string('authorized_by_user_id');
            $table->string('authorized_by_role');
            $table->timestamp('authorized_at');
            $table->string('release_reason', 50);
            $table->text('release_notes')->nullable();
            $table->json('information_released');
            $table->boolean('consent_obtained')->default(false);
            $table->timestamp('consent_obtained_at')->nullable();
            $table->string('consent_method')->nullable();
            $table->boolean('recipient_acknowledged')->default(false);
            $table->timestamp('recipient_acknowledged_at')->nullable();
            $table->text('recipient_acknowledgement_notes')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('released_at')->useCurrent();
            $table->timestamps();
            $table->index(['pseudo_id', 'released_at']);
            $table->index(['released_to_user_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('identity_vault')->dropIfExists('idv_release_records');
    }
};
