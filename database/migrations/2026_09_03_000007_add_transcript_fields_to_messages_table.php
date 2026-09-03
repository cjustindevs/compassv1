<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'transcript')) {
                $table->text('transcript')->nullable()->after('message_text');
            }
            if (! Schema::hasColumn('messages', 'is_transcript')) {
                $table->boolean('is_transcript')->default(false)->after('transcript');
            }
            if (! Schema::hasColumn('messages', 'transcript_generated_at')) {
                $table->timestamp('transcript_generated_at')->nullable()->after('is_transcript');
            }
            if (! Schema::hasColumn('messages', 'transcript_verified_by')) {
                $table->foreignId('transcript_verified_by')->nullable()->after('transcript_generated_at')->constrained('advisers', 'id')->nullOnDelete();
            }
            if (! Schema::hasColumn('messages', 'transcript_verified_at')) {
                $table->timestamp('transcript_verified_at')->nullable()->after('transcript_verified_by');
            }
            if (! Schema::hasColumn('messages', 'audio_url')) {
                $table->string('audio_url')->nullable()->after('transcript_verified_at');
            }
            if (! Schema::hasColumn('messages', 'voice_consent_obtained')) {
                $table->boolean('voice_consent_obtained')->default(false)->after('audio_url');
            }
            if (! Schema::hasColumn('messages', 'voice_consent_obtained_at')) {
                $table->timestamp('voice_consent_obtained_at')->nullable()->after('voice_consent_obtained');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'transcript_verified_by')) {
                $table->dropForeign(['transcript_verified_by']);
            }

            foreach (['transcript', 'is_transcript', 'transcript_generated_at', 'transcript_verified_by', 'transcript_verified_at', 'audio_url', 'voice_consent_obtained', 'voice_consent_obtained_at'] as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
