<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('popup_presented_at')->nullable()->index();
            $table->string('popup_event_key', 191)->nullable();
        });
        // Keep existing unread history intact, but never replay it on a new device/domain.
        DB::table('notifications')->update(['popup_presented_at' => now()]);
        Schema::create('notification_popup_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_account_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 191);
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->timestamp('presented_at');
            $table->unique(['user_account_id', 'event_key']);
        });
        // Also silence future duplicate reminders for historical events, including on a new hostname.
        DB::table('notifications')->orderBy('id')->chunkById(500, function ($rows) {
            $deliveries = [];
            foreach ($rows as $row) {
                $key = $row->popup_event_key;
                if (!$key && $row->notification_type === 'emergency' && $row->emergency_alert_id) $key = 'emergency:alert:'.$row->emergency_alert_id;
                $deliveries[] = ['user_account_id'=>$row->user_account_id, 'event_key'=>$key ?: 'notification:'.$row->id,
                    'notification_id'=>$row->id, 'presented_at'=>now()];
            }
            DB::table('notification_popup_deliveries')->insertOrIgnore($deliveries);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_popup_deliveries');
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['popup_presented_at']);
            $table->dropColumn(['popup_presented_at', 'popup_event_key']);
        });
    }
};
