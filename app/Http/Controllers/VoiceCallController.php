<?php

namespace App\Http\Controllers;

use App\Events\VoiceSignal;
use App\Models\{Session, VoiceCall, VoiceSignalPacket};
use App\Services\{VoiceCallService, VoiceConfiguration};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Log};

class VoiceCallController extends Controller
{
    public function __construct(private VoiceCallService $calls) {}

    public function state(Request $request, Session $session)
    {
        $data = $request->validate(['call_id'=>'nullable|uuid','after_signal_id'=>'nullable|integer|min:0']);
        $state = $this->calls->state($request->user(),$session,$data['call_id'] ?? null);
        $state['signaling_available'] = $state['available'];
        $state['signals'] = []; $state['signal_cursor'] = (int) ($data['after_signal_id'] ?? 0);
        if ($state['available'] && $state['call'] && $state['call']['id'] === ($data['call_id'] ?? null)
            && in_array($state['call']['status'], VoiceCall::OPEN, true)) {
            $packets = VoiceSignalPacket::where('call_id', $state['call']['id'])->where('recipient_id', $request->user()->id)
                ->where('expires_at', '>', now())->where('id', '>', $state['signal_cursor'])->orderBy('id')->limit(100)->get();
            $state['signals'] = $packets->map(fn ($packet) => array_merge($packet->payload, ['signal_id'=>$packet->id]))->all();
            if ($packets->isNotEmpty()) $state['signal_cursor'] = $packets->last()->id;
        }
        return response()->json($state)->header('Cache-Control','no-store');
    }

    public function credentials(Request $request, Session $session)
    {
        $this->calls->authorize($request->user(),$session);
        $credential = app(VoiceConfiguration::class)->credential();
        abort_unless($credential,503,'Voice is unavailable. Continue using text chat.');
        return response()->json(['iceServers'=>$credential->payload,'iceTransportPolicy'=>'relay','expires_at'=>$credential->expires_at->toIso8601String()])->header('Cache-Control','no-store');
    }

    public function start(Request $request, Session $session)
    {
        $call = $this->calls->start($request->user(),$session);
        return response()->json(['call'=>$this->calls->present($call,$request->user())]);
    }

    public function action(Request $request, Session $session)
    {
        $data = $request->validate(['call_id'=>'required|uuid','action'=>'required|in:accept,decline,connected,leave,fail']);
        $call = $this->calls->action($request->user(),$session,$data['call_id'],$data['action']);
        return response()->json(['call'=>$this->calls->present($call,$request->user())]);
    }

    public function signal(Request $request, Session $session)
    {
        $this->calls->authorize($request->user(),$session);
        abort_unless(app(VoiceConfiguration::class)->configured(),503);
        $data = $request->validate(['call_id'=>'required|uuid','type'=>'required|in:offer,answer,candidate',
            'sdp'=>'required_if:type,offer,answer|nullable|string|max:7000',
            'candidate'=>'required_if:type,candidate|nullable|array:candidate,sdpMid,sdpMLineIndex,usernameFragment',
            'candidate.candidate'=>'required_if:type,candidate|string|max:1500',
            'candidate.sdpMid'=>'nullable|string|max:50','candidate.sdpMLineIndex'=>'nullable|integer|min:0|max:10','candidate.usernameFragment'=>'nullable|string|max:100']);
        $packet = DB::transaction(function () use ($request,$session,$data) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->calls->authorize($request->user(),$session);
            $call = VoiceCall::where('session_id',$session->id)->whereKey($data['call_id'])->firstOrFail();
            abort_unless(in_array($request->user()->id,[$call->caller_user_id,$call->callee_user_id],true),403);
            abort_unless(in_array($call->status,['connecting','connected'],true) && !$this->calls->stale($call),409,'This call is no longer connected.');
            $caller = $request->user()->id === $call->caller_user_id;
            abort_if(($data['type'] === 'offer' && !$caller) || ($data['type'] === 'answer' && $caller),403);
            if ($data['type'] === 'candidate') abort_unless(preg_match('/\btyp relay\b/', $data['candidate']['candidate']),422,'Only relay candidates are allowed.');
            else {
                abort_if(preg_match('/^m=(?:video|application)\b/m', $data['sdp']),422,'Only live audio is supported.');
                foreach (preg_split('/\r?\n/', $data['sdp']) as $line) {
                    if (str_starts_with($line,'a=candidate:')) abort_unless(preg_match('/\btyp relay\b/',$line),422,'Only relay candidates are allowed.');
                }
            }
            $payload = ['call_id'=>$call->id,'type'=>$data['type']];
            $payload[$data['type'] === 'candidate' ? 'candidate' : 'sdp'] = $data[$data['type'] === 'candidate' ? 'candidate' : 'sdp'];
            if (isset($payload['sdp'])) $payload['sdp'] = rtrim($payload['sdp'], "\r\n")."\r\n";
            return VoiceSignalPacket::create(['call_id'=>$call->id, 'recipient_id'=>$caller ? $call->callee_user_id : $call->caller_user_id,
                'payload'=>$payload, 'expires_at'=>now()->addMinutes(2)]);
        });
        // Publish after releasing the session lock. A failed socket must not discard the fallback packet.
        if (!Cache::has('voice-broadcast-unavailable')) {
            try { event(new VoiceSignal($session->id, $packet->recipient_id, array_merge($packet->payload, ['signal_id'=>$packet->id]))); }
            catch (\Throwable $error) {
                Cache::put('voice-broadcast-unavailable', true, 30);
                Log::warning('Voice realtime delivery unavailable; authenticated signal polling remains active.');
            }
        }
        return response()->json(['sent'=>true])->header('Cache-Control','no-store');
    }
}
