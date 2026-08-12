<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type_icon')->nullable()->after('notification_type'); // emoji or font-awesome icon
            $table->string('link')->nullable()->after('type_icon'); // route to open when clicked
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['type_icon', 'link']);
        });
    }
};