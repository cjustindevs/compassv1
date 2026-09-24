<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adviser_helper_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helper_id')->constrained('helpers');
            $table->foreignId('adviser_id')->constrained('advisers');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('reason');
            $table->timestamp('created_at');
        });

        // Enforce "one active (ended_at IS NULL) assignment per helper".
        // PostgreSQL and SQLite support partial (WHERE) indexes natively.
        // MySQL 8.0.13+ has no partial indexes, so the same rule is enforced
        // with a functional unique index on (helper_id, COALESCE(ended_at, ...)):
        // every open assignment collapses to the same sentinel value.
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("CREATE UNIQUE INDEX adviser_helper_active_unique ON adviser_helper_assignments (helper_id, (COALESCE(ended_at, '1970-01-01 00:00:00')))");
        } else {
            DB::statement('CREATE UNIQUE INDEX adviser_helper_active_unique ON adviser_helper_assignments (helper_id) WHERE ended_at IS NULL');
        }
        DB::table('helpers')->whereNotNull('adviser_id')->orderBy('id')->chunkById(100, function ($helpers) {
            foreach ($helpers as $helper) {
                DB::table('adviser_helper_assignments')->insert([
                    'helper_id' => $helper->id, 'adviser_id' => $helper->adviser_id, 'started_at' => null,
                    'reason' => 'Existing supervision imported; original assignment date is unknown.', 'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adviser_helper_assignments');
    }
};
