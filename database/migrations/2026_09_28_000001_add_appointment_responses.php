<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('referral_appointments', function(Blueprint $t) { $t->string('meeting_format')->nullable(); $t->string('seeker_response')->nullable(); $t->timestamp('responded_at')->nullable(); $t->timestamp('reminded_at')->nullable(); }); }
 public function down(): void { Schema::table('referral_appointments', fn(Blueprint $t) => $t->dropColumn(['meeting_format','seeker_response','responded_at','reminded_at'])); }
};
