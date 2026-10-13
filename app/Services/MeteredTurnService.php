<?php

namespace App\Services;

use App\Models\VoiceTurnCredential;
use Illuminate\Support\Facades\{Cache, Http, Log};

class MeteredTurnService
{
    public function prepare(): bool
    {
        if (!app(VoiceConfiguration::class)->configured()) return false;
        return Cache::lock('voice-turn-prepare', 40)->get(function () {
            if (VoiceTurnCredential::where('created_at','>',now()->subMinutes(15))->where('expires_at','>',now()->addMinutes(100))->exists()) return true;
            $base = 'https://'.config('voice.metered_domain').'/api/v1/turn/';
            try {
                $created = Http::acceptJson()->connectTimeout(5)->timeout(10)->post($base.'credential?'.http_build_query(['secretKey'=>config('voice.metered_secret')]),
                    ['expiryInSeconds'=>7200,'label'=>'compass-voice-'.now()->format('YmdHi')]);
                if (!$created->successful() || !is_string($created->json('apiKey'))) {
                    Log::warning('Voice relay preparation failed.', ['step'=>'create_credential','http_status'=>$created->status()]);
                    return false;
                }
                $response = Http::acceptJson()->connectTimeout(5)->timeout(10)->get($base.'credentials', ['apiKey'=>$created->json('apiKey')]);
                if (!$response->successful() || !is_array($response->json())) {
                    Log::warning('Voice relay preparation failed.', ['step'=>'retrieve_credentials','http_status'=>$response->status()]);
                    return false;
                }
                $servers = [];
                foreach ($response->json() as $server) {
                    if (!is_array($server) || !is_string($server['urls'] ?? null)
                        || !preg_match('/^turns?:[a-z0-9.-]+(?::\d+)?(?:\?transport=(?:tcp|udp))?$/i', $server['urls'])
                        || !is_string($server['username'] ?? null) || !is_string($server['credential'] ?? null)) continue;
                    $servers[] = array_intersect_key($server, array_flip(['urls','username','credential']));
                }
                if (!$servers) {
                    Log::warning('Voice relay preparation failed.', ['step'=>'validate_relay_servers']);
                    return false;
                }
                // Metered documents up to two minutes of propagation for new credentials.
                VoiceTurnCredential::create(['payload'=>$servers,'ready_at'=>now()->addMinutes(2),'expires_at'=>now()->addSeconds(7200)]);
                VoiceTurnCredential::where('expires_at','<',now())->delete();
                return true;
            } catch (\Throwable $e) {
                // Provider exception messages can include secret query parameters. Never log them.
                Log::warning('Voice relay preparation failed.', ['step'=>'request_or_storage_error']);
                return false;
            }
        }) ?? false;
    }
}
