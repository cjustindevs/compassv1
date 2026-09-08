<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'identity_vault';

    public function up(): void
    {
        Schema::connection('identity_vault')->create('idv_identities', function (Blueprint $table) {
            $table->id('identity_id');
            $table->string('pseudo_id', 50)->unique();
            $table->unsignedInteger('identity_version')->default(1);
            $table->string('seeker_alias', 100);
            // Ciphertext exceeds the length of its plaintext; use TEXT for every encrypted field.
            foreach (['real_name', 'phone_number', 'email', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'student_id', 'department', 'year_level'] as $field) {
                $table->text($field)->nullable();
            }
            $table->boolean('identity_released')->default(false);
            $table->timestamp('identity_released_at')->nullable();
            $table->string('identity_released_by')->nullable();
            $table->string('identity_released_reason')->nullable();
            $table->unsignedBigInteger('referral_id')->nullable();
            $table->boolean('referral_consent_obtained')->default(false);
            $table->timestamp('consent_obtained_at')->nullable();
            $table->boolean('emergency_override')->default(false);
            $table->timestamp('emergency_override_at')->nullable();
            $table->string('emergency_override_by')->nullable();
            $table->text('emergency_override_reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('data_expires_at')->index();
            $table->timestamp('data_deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->index(['pseudo_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::connection('identity_vault')->dropIfExists('idv_identities');
    }
};
