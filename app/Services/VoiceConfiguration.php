<?php

namespace App\Services;

use App\Models\VoiceTurnCredential;
use Illuminate\Support\Facades\Schema;

class VoiceConfiguration
{
    public function configured(): bool
    {
        return (bool) config('voice.enabled') && config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'))
            && filled(config('broadcasting.connections.reverb.secret'))
            && filled(config('broadcasting.connections.reverb.app_id'))
            && filled(config('voice.metered_secret'))
            && preg_match('/^[a-z0-9-]+\.metered\.live$/i', (string) config('voice.metered_domain')) === 1;
    }

    public function credential(): ?VoiceTurnCredential
    {
        if (!$this->configured() || !Schema::hasTable('voice_turn_credentials')) return null;
        // Leave enough lifetime for the entire existing 90-minute session limit.
        return VoiceTurnCredential::where('ready_at', '<=', now())
            ->where('expires_at', '>', now()->addMinutes(SessionDurationService::MAX_MINUTES + 5))
            ->latest('id')->first();
    }

    public function ready(): bool { return $this->credential() !== null; }

    public function publicSocket(): array
    {
        return ['key'=>config('broadcasting.connections.reverb.key'), 'host'=>config('voice.ws_host'),
            'port'=>config('voice.ws_port'), 'secure'=>config('voice.ws_scheme') === 'https'];
    }
}
