<?php

namespace App\Services;

use App\Models\{ConsentRecord, Session, User, VoiceCall, VoiceSignalPacket};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;

class VoiceCallService
{
    public function authorize(User $user, Session $session): void
    {
        Gate::forUser($user)->authorize('participate', $session);
        abort_unless($session->helper_accepted_at && $session->isActive() && $session->start_time, 409, 'Only an accepted, active session can use voice.');
        app(SessionDurationService::class)->expire($session);
        abort_if($session->isCompleted(), 409, 'This session has ended.');
        abort_unless($session->session_type === 'voice', 409, 'This request uses text chat.');
        abort_unless($session->seeker?->user?->is_active && $session->helper?->user?->is_active, 409, 'The other participant is unavailable.');
        abort_unless(app(ConsentService::class)->isFull($session->seeker), 409, 'Current support consent is required.');
        $consent = ConsentRecord::where('seeker_id',$session->seeker_id)->where('session_id',$session->id)
            ->where('purpose','voice_participation')->latest('id')->first();
        abort_unless($consent && $consent->version === ConsentService::VERSION && $consent->consent_given && !$consent->withdrawn && $consent->decision === 'accepted', 409, 'Voice participation consent is required.');
    }

    public function stale(VoiceCall $call): bool
    {
        if (!in_array($call->status, VoiceCall::OPEN, true)) return false;
        if ($call->status === 'ringing') return $call->created_at->lte(now()->subSeconds(config('voice.ring_seconds')));
        if (!$call->connected_at && $call->accepted_at?->lte(now()->subSeconds(config('voice.connection_seconds')))) return true;
        return ($call->caller_seen_at && $call->caller_seen_at->lte(now()->subSeconds(config('voice.heartbeat_seconds'))))
            || ($call->callee_seen_at && $call->callee_seen_at->lte(now()->subSeconds(config('voice.heartbeat_seconds'))));
    }

    private function finish(VoiceCall $call, string $status, string $reason): VoiceCall
    {
        if (in_array($call->status, VoiceCall::OPEN, true)) {
            $call->update(['status'=>$status, 'end_reason'=>$reason, 'ended_at'=>now()]);
            VoiceSignalPacket::where('call_id', $call->id)->delete();
            SupportAudit::record('voice_call_'.$status, Session::findOrFail($call->session_id), ['call_id'=>$call->id,'reason'=>$reason]);
        }
        return $call;
    }

    public function closeForSession(Session $session): void
    {
        foreach (VoiceCall::where('session_id',$session->id)->whereIn('status',VoiceCall::OPEN)->get() as $call) {
            $this->finish($call,'ended','session_changed');
        }
    }

    public function state(User $user, Session $session, ?string $activeCall = null): array
    {
        Gate::forUser($user)->authorize('participate', $session);
        app(SessionDurationService::class)->expire($session);
        return DB::transaction(function () use ($user, $session, $activeCall) {
            Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $call = VoiceCall::where('session_id',$session->id)->latest('created_at')->latest('id')->first();
            $allowed = true;
            try { $this->authorize($user, $session->fresh()); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $allowed = false; }
            if ($call && in_array($call->status,VoiceCall::OPEN,true)) {
                if (!$allowed || !config('voice.enabled')) $this->finish($call,'ended','session_unavailable');
                elseif ($this->stale($call)) $this->finish($call,'failed','connection_timeout');
                elseif ($activeCall === $call->id) $call->update([$call->caller_user_id === $user->id ? 'caller_seen_at' : 'callee_seen_at'=>now()]);
            }
            return ['available'=>$allowed && app(VoiceConfiguration::class)->ready(),
                'session'=>app(SessionDurationService::class)->state($session->fresh()),
                'call'=>$call ? $this->present($call,$user) : null];
        });
    }

    public function start(User $user, Session $session): VoiceCall
    {
        $this->authorize($user,$session);
        abort_unless(app(VoiceConfiguration::class)->ready(),503,'Voice is unavailable. You can continue using text chat.');
        return DB::transaction(function () use ($user,$session) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->authorize($user,$session);
            $open = VoiceCall::where('session_id',$session->id)->whereIn('status',VoiceCall::OPEN)->first();
            if ($open && $this->stale($open)) { $this->finish($open,'failed','connection_timeout'); $open=null; }
            abort_if($open !== null,409,'A call is already in progress.');
            $peerId = $user->role === 'helper' ? $session->seeker->user_account_id : $session->helper->user_account_id;
            $call = VoiceCall::create(['id'=>(string)Str::orderedUuid(),'session_id'=>$session->id,'caller_user_id'=>$user->id,'callee_user_id'=>$peerId,
                'status'=>'ringing','caller_seen_at'=>now(),'callee_seen_at'=>now()]);
            SupportAudit::record('voice_call_requested',$session,['call_id'=>$call->id]);
            return $call;
        });
    }

    public function action(User $user, Session $session, string $id, string $action): VoiceCall
    {
        Gate::forUser($user)->authorize('participate',$session);
        return DB::transaction(function () use ($user,$session,$id,$action) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $call = VoiceCall::where('session_id',$session->id)->whereKey($id)->firstOrFail();
            abort_unless(in_array($user->id,[$call->caller_user_id,$call->callee_user_id],true),403);
            if ($action === 'leave') return $this->finish($call,'ended','participant_left');
            if ($action === 'fail') return $this->finish($call,'failed','connection_failed');
            if ($this->stale($call)) $this->finish($call,'failed','connection_timeout');
            $this->authorize($user,$session);
            abort_unless(config('voice.enabled'),503,'Voice is unavailable.');
            if ($action === 'decline') {
                abort_unless($user->id === $call->callee_user_id && $call->status === 'ringing',409);
                return $this->finish($call,'declined','participant_declined');
            }
            if ($action === 'accept') {
                abort_unless($user->id === $call->callee_user_id && $call->status === 'ringing',409);
                $call->update(['status'=>'connecting','accepted_at'=>now(),'caller_seen_at'=>now(),'callee_seen_at'=>now()]);
            } elseif ($action === 'connected') {
                abort_unless(in_array($call->status,['connecting','connected'],true),409);
                $call->update([$user->id === $call->caller_user_id ? 'caller_connected_at' : 'callee_connected_at'=>now()]);
                if ($call->caller_connected_at && $call->callee_connected_at) $call->update(['status'=>'connected','connected_at'=>$call->connected_at ?? now()]);
            }
            return $call;
        });
    }

    public function present(VoiceCall $call, User $user): array
    {
        return ['id'=>$call->id,'status'=>$call->status,'is_caller'=>$user->id === $call->caller_user_id,
            'created_at'=>$call->created_at->toIso8601String(),'connected_at'=>$call->connected_at?->toIso8601String(), 'end_reason'=>$call->end_reason];
    }
}
