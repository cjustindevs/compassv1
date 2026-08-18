<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use Illuminate\Database\Seeder;

class CalendarEventSeeder extends Seeder
{
    public function run(): void
    {
        $adviserUser = \App\Models\User::where('email', 'adviser@compass.edu.ph')->first();

        $events = [
            [
                'title' => 'Monthly Helper Supervision',
                'description' => 'Group supervision session for all active helpers.',
                'event_date' => now()->addDays(3)->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '11:30',
                'event_type' => 'meeting',
                'color' => '#F59E0B',
            ],
            [
                'title' => 'Peer Support Training – Module 3',
                'description' => 'Active listening and crisis de-escalation refresher.',
                'event_date' => now()->addDays(10)->format('Y-m-d'),
                'start_time' => '14:00',
                'end_time' => '16:00',
                'event_type' => 'training',
                'color' => '#8B5CF6',
            ],
            [
                'title' => 'Weekly Adviser Sync',
                'description' => 'Check-in on evaluation queue and referrals.',
                'event_date' => now()->addWeek()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '09:45',
                'event_type' => 'meeting',
                'color' => '#F59E0B',
            ],
            [
                'title' => 'Mental Health Awareness Workshop',
                'description' => 'Open workshop for the campus community.',
                'event_date' => now()->addDays(17)->format('Y-m-d'),
                'start_time' => '13:00',
                'end_time' => '15:00',
                'event_type' => 'training',
                'color' => '#3B82F6',
            ],
        ];

        foreach ($events as $event) {
            CalendarEvent::firstOrCreate(
                [
                    'title' => $event['title'],
                    'event_date' => $event['event_date'],
                ],
                $event + ['created_by' => $adviserUser?->id]
            );
        }
    }
}
