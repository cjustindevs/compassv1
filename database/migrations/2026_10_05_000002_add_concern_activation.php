<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('concern_categories',fn(Blueprint $t)=>$t->boolean('is_active')->default(true)->index());}
 public function down():void {Schema::table('concern_categories',fn(Blueprint $t)=>$t->dropColumn('is_active'));}
};
