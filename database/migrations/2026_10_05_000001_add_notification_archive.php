<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('notifications',function(Blueprint $table){$table->timestamp('archived_at')->nullable()->index();$table->timestamp('popup_dismissed_at')->nullable();});}
 public function down():void {Schema::table('notifications',fn(Blueprint $table)=>$table->dropColumn(['archived_at','popup_dismissed_at']));}
};
