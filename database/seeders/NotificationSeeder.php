<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'seeker')->get();

        if ($users->isEmpty()) {
            return;
        }

        $samples = [
            ['title' => 'Welcome to COMPASS', 'message' => 'Your account is ready. Explore self-help tools or request your first session anytime.', 'notification_type' => 'system', 'type_icon' => 'fa-bell', 'link' => '/selfhelp'],
            ['title' => 'Helper available', 'message' => 'A helper is available to support you. Start a request when you are ready.', 'notification_type' => 'session', 'type_icon' => 'fa-handshake', 'link' => '/request/screening'],
            ['title' => 'Evaluation reminder', 'message' => 'Please complete your session evaluation so we can keep improving.', 'notification_type' => 'reminder', 'type_icon' => 'fa-pen-to-square', 'link' => '/session/history'],
            ['title' => 'New self-help resources', 'message' => 'New breathing exercises and articles were added. Take five minutes for yourself.', 'notification_type' => 'update', 'type_icon' => 'fa-star', 'link' => '/selfhelp'],
            ['title' => 'Session reminder', 'message' => 'Your session starts in 15 minutes. Make sure you are in a quiet space.', 'notification_type' => 'reminder', 'type_icon' => 'fa-clock', 'link' => '/session/chat'],
            ['title' => 'COMPASS update', 'message' => 'COMPASS has been updated with new self-help tools and notification controls.', 'notification_type' => 'update', 'type_icon' => 'fa-bell', 'link' => '/settings'],
        ];

        foreach ($users as $user) {
            foreach ($samples as $sample) {
                Notification::create(array_merge($sample, [
                    'user_account_id' => $user->id,
                    'status' => 'unread',
                    'read_at' => null,
                    'created_at' => now()->subMinutes(rand(10, 600)),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}