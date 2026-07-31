<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adviser_id')->constrained('advisers', 'id')->onDelete('cascade');
            $table->string('report_name');
            $table->string('report_type');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->timestamp('generated_date')->useCurrent();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};