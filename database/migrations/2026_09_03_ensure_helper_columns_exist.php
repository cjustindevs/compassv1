<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            if (! Schema::hasColumn('helpers', 'availability') && Schema::hasColumn('helpers', 'available')) {
                $table->renameColumn('available', 'availability');
            }

            if (! Schema::hasColumn('helpers', 'is_ready') && Schema::hasColumn('helpers', 'ready')) {
                $table->renameColumn('ready', 'is_ready');
            }

            if (! Schema::hasColumn('helpers', 'status') && Schema::hasColumn('helpers', 'helper_status')) {
                $table->renameColumn('helper_status', 'status');
            }
        });
    }

    public function down(): void
    {
        // Intentionally no-op: this migration normalizes legacy aliases only when
        // canonical columns are missing, and rollback should not corrupt current names.
    }
};
