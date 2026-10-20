<?php

namespace App\Services;

use App\Models\VoiceTurnCredential;
use Illuminate\Support\Facades\Schema;

class VoiceConfiguration
{
    public function configured(): bool
    {
        return $this->configurationIssues() === [];
    }

    public function configurationIssues(): array
    {
        $issues = [];
        if (!config('voice.enabled')) $issues[] = 'VOICE_CALLS_ENABLED';
        if (config('broadcasting.default') !== 'reverb') $issues[] = 'BROADCAST_CONNECTION';
        foreach (['REVERB_APP_KEY'=>'broadcasting.connections.reverb.key',
            'REVERB_APP_SECRET'=>'broadcasting.connections.reverb.secret',
            'REVERB_APP_ID'=>'broadcasting.connections.reverb.app_id', 'METERED_SECRET_KEY'=>'voice.metered_secret'] as $name => $key) {
            if (blank(config($key))) $issues[] = $name;
        }
        if (preg_match('/^[a-z0-9-]+\.metered\.live$/i', (string) config('voice.metered_domain')) !== 1) $issues[] = 'METERED_DOMAIN';
        return $issues;
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

    public function availability(): string
    {
        if (!config('voice.enabled')) return 'disabled';
        if (!$this->configured()) return 'configuration_missing';
        if (!Schema::hasTable('voice_turn_credentials')) return 'migration_missing';
        if ($this->ready()) return 'ready';
        if (VoiceTurnCredential::where('ready_at', '>', now())
            ->where('expires_at', '>', now()->addMinutes(SessionDurationService::MAX_MINUTES + 5))->exists()) return 'preparing';
        return 'relay_unavailable';
    }

    public function publicSocket(): array
    {
        $renderHost = config('voice.render_hostname');
        if (is_string($renderHost) && preg_match('/^[a-z0-9-]+\.onrender\.com$/i', $renderHost)) {
            // This Docker deployment proxies Reverb through the current service's public port.
            // Copied environments must never send its browsers to another Render instance.
            return ['key'=>config('broadcasting.connections.reverb.key'), 'host'=>$renderHost, 'port'=>443, 'secure'=>true];
        }
        return ['key'=>config('broadcasting.connections.reverb.key'), 'host'=>config('voice.ws_host'),
            'port'=>config('voice.ws_port'), 'secure'=>config('voice.ws_scheme') === 'https'];
    }
}
