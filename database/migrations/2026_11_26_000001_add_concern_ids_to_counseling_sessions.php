<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A seeker may pick several concerns. concern_id keeps the first (main)
// concern for matching and reports; concern_ids keeps every selection.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('counseling_sessions', 'concern_ids')) {
            Schema::table('counseling_sessions', fn (Blueprint $table) => $table->json('concern_ids')->nullable()->after('concern_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('counseling_sessions', 'concern_ids')) {
            Schema::table('counseling_sessions', fn (Blueprint $table) => $table->dropColumn('concern_ids'));
        }
    }
};
