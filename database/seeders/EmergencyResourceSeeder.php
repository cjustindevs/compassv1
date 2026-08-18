<?php

namespace Database\Seeders;

use App\Models\EmergencyResource;
use Illuminate\Database\Seeder;

/**
 * Seeds 24/7 crisis hotlines shown to users during emergencies.
 */
class EmergencyResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            [
                'agency_name' => 'National Center for Mental Health (NCMH) Crisis Hotline',
                'hotline' => '1553',
                'description' => '24/7 free crisis support line for anyone experiencing a mental health emergency.',
            ],
            [
                'agency_name' => 'Hopeline PH',
                'hotline' => '0917 558 4673',
                'description' => 'Crisis support and suicide prevention hotline run by the Natasha Goulbourn Foundation.',
            ],
            [
                'agency_name' => 'Hopeline PH (Globe/TM)',
                'hotline' => '2919',
                'description' => 'Toll-free crisis hotline for Globe and TM subscribers.',
            ],
            [
                'agency_name' => 'In Touch Community Services',
                'hotline' => '(02) 8893 7603',
                'description' => 'Confidential 24/7 crisis line and counseling services.',
            ],
            [
                'agency_name' => 'Tawag Paglaum - Centro Bisaya',
                'hotline' => '0919 144 5030',
                'description' => 'Bisaya-speaking crisis support line for the Visayas region.',
            ],
            [
                'agency_name' => 'Emergency Response (Philippines)',
                'hotline' => '911',
                'description' => 'National emergency hotline for immediate police, fire, and medical assistance.',
            ],
        ];

        foreach ($resources as $resource) {
            EmergencyResource::firstOrCreate(
                ['agency_name' => $resource['agency_name']],
                $resource
            );
        }

        $this->command->info('EmergencyResourceSeeder: ' . count($resources) . ' resources seeded.');
    }
}