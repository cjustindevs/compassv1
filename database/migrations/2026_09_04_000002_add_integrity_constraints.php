<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'help_seekers',
            'helpers',
            'advisers',
            'moderators',
            'psychology_professionals',
            'system_administrators',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'user_account_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->unique('user_account_id', $tableName . '_user_account_id_unique');
                });
            }
        }

        foreach (['session_reports', 'help_seeker_evaluations', 'call_logs'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'session_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->unique('session_id', $tableName . '_session_id_unique');
                });
            }
        }

        if (Schema::hasTable('identity_vault') && Schema::hasColumn('identity_vault', 'seeker_id')) {
            Schema::table('identity_vault', function (Blueprint $table) {
                $table->unique('seeker_id', 'identity_vault_seeker_id_unique');
            });
        }

        if (Schema::hasTable('readiness_checks') && Schema::hasColumn('readiness_checks', 'validated_by')) {
            Schema::table('readiness_checks', function (Blueprint $table) {
                $table->foreign('validated_by', 'readiness_checks_validated_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('readiness_checks') && Schema::hasColumn('readiness_checks', 'validated_by')) {
            Schema::table('readiness_checks', function (Blueprint $table) {
                $table->dropForeign('readiness_checks_validated_by_foreign');
            });
        }

        if (Schema::hasTable('identity_vault')) {
            Schema::table('identity_vault', function (Blueprint $table) {
                $table->dropUnique('identity_vault_seeker_id_unique');
            });
        }

        foreach (['session_reports', 'help_seeker_evaluations', 'call_logs'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropUnique($tableName . '_session_id_unique');
                });
            }
        }

        foreach ([
            'help_seekers',
            'helpers',
            'advisers',
            'moderators',
            'psychology_professionals',
            'system_administrators',
        ] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropUnique($tableName . '_user_account_id_unique');
                });
            }
        }
    }
};
