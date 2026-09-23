<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('helper_competency_history',fn(Blueprint $t)=>$t->decimal('overall_score',5,2)->nullable()->change());
        Schema::table('adviser_feedback',fn(Blueprint $t)=>$t->decimal('competency_rating',5,2)->nullable()->change());
    }
    public function down(): void {
        Schema::table('helper_competency_history',fn(Blueprint $t)=>$t->decimal('overall_score',3,1)->nullable()->change());
        Schema::table('adviser_feedback',fn(Blueprint $t)=>$t->decimal('competency_rating',5,1)->nullable()->change());
    }
};
