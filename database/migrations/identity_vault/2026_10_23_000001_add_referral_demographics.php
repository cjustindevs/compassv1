<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'identity_vault';

    public function up(): void
    {
        foreach (['age','gender'] as $field) {
            if (!Schema::connection('identity_vault')->hasColumn('idv_identities',$field)) {
                Schema::connection('identity_vault')->table('idv_identities',function (Blueprint $table) use ($field) {
                    $table->text($field)->nullable(); // Ciphertext, never an operational demographic column.
                });
            }
        }
    }

    public function down(): void
    {
        Schema::connection('identity_vault')->table('idv_identities',fn (Blueprint $table) => $table->dropColumn(['age','gender']));
    }
};
