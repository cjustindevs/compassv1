<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Profile
            $table->string('avatar_path')->nullable()->after('password');

            // Privacy
            $table->boolean('show_email')->default(false)->after('avatar_path');
            $table->boolean('allow_data_research')->default(false)->after('show_email');

            // Notification preferences
            $table->boolean('email_notifications')->default(true)->after('allow_data_research');
            $table->boolean('push_notifications')->default(true)->after('email_notifications');
            $table->boolean('session_reminders')->default(true)->after('push_notifications');
            $table->boolean('marketing_emails')->default(false)->after('session_reminders');

            // Session preferences (for future matching)
            $table->string('preferred_language')->default('English')->after('marketing_emails');
            $table->string('preferred_communication_mode')->default('chat')->after('preferred_language'); // chat, voice
            $table->string('preferred_helper_gender')->nullable()->after('preferred_communication_mode');
            $table->string('session_duration_preference')->default('30')->after('preferred_helper_gender'); // minutes

            // Appearance
            $table->string('font_size')->default('medium')->after('session_duration_preference'); // small, medium, large
            $table->boolean('high_contrast')->default(false)->after('font_size');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path',
                'show_email',
                'allow_data_research',
                'email_notifications',
                'push_notifications',
                'session_reminders',
                'marketing_emails',
                'preferred_language',
                'preferred_communication_mode',
                'preferred_helper_gender',
                'session_duration_preference',
                'font_size',
                'high_contrast',
            ]);
        });
    }
};
