<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concern_categories')->insert([
            ['concern_name' => 'Academic Stress', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Family Problems', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Relationship Issues', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Mental Well-being', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Physical Health', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Financial Problems', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Anxiety', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Depression', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Leadership', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Bullying', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Self-esteem', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Career Concerns', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Time Management', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Grief', 'created_at' => now(), 'updated_at' => now()],
            ['concern_name' => 'Others', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('concern_categories')->truncate();
    }
};