<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('emergency_alerts',function(Blueprint $t){$t->string('review_decision')->nullable();$t->text('rejection_reason')->nullable();$t->foreignId('rejected_by')->nullable()->constrained('users');$t->timestamp('rejected_at')->nullable();});}
 public function down():void {Schema::table('emergency_alerts',function(Blueprint $t){$t->dropForeign(['rejected_by']);$t->dropColumn(['review_decision','rejection_reason','rejected_by','rejected_at']);});}
};
