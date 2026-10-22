<?php

namespace Tests\Feature;

use App\Events\VoiceSignal;
use App\Models\{ConsentRecord, ConcernCategory, HelpSeeker, Helper, Session, User, VoiceCall, VoiceTurnCredential};
use App\Services\{ConsentService, MeteredTurnService, VoiceConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Event, Http};
use Tests\TestCase;

class VoiceCallTest extends TestCase
{
    use RefreshDatabase;

    private function room(): array
    {
        $this->withoutVite();
        $this->travelTo(now()->startOfSecond());
        config(['voice.enabled'=>true,'voice.metered_domain'=>'compass-test.metered.live','voice.metered_secret'=>'private-master-secret',
            'broadcasting.default'=>'reverb','broadcasting.connections.reverb.key'=>'public-app-key',
            'broadcasting.connections.reverb.secret'=>'private-app-secret','broadcasting.connections.reverb.app_id'=>'compass-test']);
        Event::fake([VoiceSignal::class, \App\Events\SessionEnded::class]);
        $seeker = User::factory()->create(['role'=>'seeker']);
        $profile = HelpSeeker::create(['user_account_id'=>$seeker->id,'generated_alias'=>'PrivateFox42']);
        $helperUser = User::factory()->create(['role'=>'helper']);
        $helper = Helper::create(['user_account_id'=>$helperUser->id,'first_name'=>'Peer','last_name'=>'Helper','email'=>$helperUser->email,'status'=>'busy']);
        $session = Session::create(['seeker_id'=>$profile->id,'helper_id'=>$helper->id,'session_type'=>'voice','session_status'=>'active',
            'workflow_state'=>'session_active','helper_accepted_at'=>now()->subMinute(),'start_time'=>now()->subMinute()]);
        $this->actingAs($seeker);
        foreach (['privacy_policy','informed_consent','voice_participation'] as $purpose) app(ConsentService::class)->decide($seeker,$purpose,'accepted',$purpose==='voice_participation'?$session->id:null);
        VoiceTurnCredential::create(['payload'=>[['urls'=>'turn:relay.metered.ca:443?transport=tcp','username'=>'temporary-user','credential'=>'temporary-password']],
            'ready_at'=>now()->subMinute(),'expires_at'=>now()->addHours(2)]);
        return [$seeker,$helperUser,$session];
    }

    public function test_disabled_feature_and_nonparticipants_cannot_start_or_obtain_credentials(): void
    {
        [$seeker,$helper,$session]=$this->room();
        config(['voice.enabled'=>false]);
        $this->postJson(route('voice.start',$session))->assertStatus(503);
        $this->getJson(route('voice.credentials',$session))->assertStatus(503);
        config(['voice.enabled'=>true]);
        foreach (['seeker','helper','moderator','adviser','professional','admin'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]))->getJson(route('voice.credentials',$session))->assertForbidden();
            $this->postJson(route('voice.start',$session))->assertForbidden();
        }
        $this->assertDatabaseCount('voice_calls',0);
    }

    public function test_call_accept_decline_and_leave_do_not_complete_the_support_session(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $call=$this->postJson(route('voice.start',$session))->assertOk()->json('call.id');
        $this->postJson(route('voice.start',$session))->assertStatus(409);
        $this->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertStatus(409);
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertOk()->assertJsonPath('call.status','connecting');
        $this->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'connected'])->assertOk()->assertJsonPath('call.status','connecting');
        $this->actingAs($seeker)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'connected'])->assertOk()->assertJsonPath('call.status','connected');
        $this->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'leave'])->assertOk()->assertJsonPath('call.status','ended');
        $ended=VoiceCall::findOrFail($call)->ended_at->toIso8601String();
        $this->travel(1)->seconds();
        $this->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'leave'])->assertOk();
        $this->assertSame($ended,VoiceCall::findOrFail($call)->ended_at->toIso8601String());
        $next=$this->postJson(route('voice.start',$session))->assertOk()->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$next,'action'=>'decline'])->assertOk()->assertJsonPath('call.status','declined');
        $this->assertSame('active',$session->fresh()->session_status);
        $this->assertNull($session->fresh()->end_time);
        $this->assertDatabaseCount('call_logs',0);
        $this->assertDatabaseCount('session_reports',0);
    }

    public function test_signaling_is_targeted_audio_only_and_rejects_stale_or_foreign_call_ids(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $call=$this->postJson(route('voice.start',$session))->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertOk();
        $payload=['call_id'=>$call,'type'=>'offer','sdp'=>"v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\n"];
        $this->postJson(route('voice.signal',$session),$payload)->assertForbidden();
        $this->actingAs($seeker)->postJson(route('voice.signal',$session),$payload)->assertOk();
        Event::assertDispatched(VoiceSignal::class,fn($event)=>$event->broadcastOn()->name==='private-voice.'.$session->id.'.'.$helper->id
            && $event->broadcastWith()['call_id']===$call);
        $this->postJson(route('voice.signal',$session),array_replace($payload,['sdp'=>"v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n"]))->assertUnprocessable();
        $this->postJson(route('voice.signal',$session),['call_id'=>$call,'type'=>'candidate','candidate'=>['candidate'=>'candidate:1 1 udp 1 192.168.1.1 1234 typ host']])->assertUnprocessable();
        $this->postJson(route('voice.signal',$session),array_replace($payload,['call_id'=>(string)\Illuminate\Support\Str::uuid()]))->assertNotFound();
        $this->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'leave'])->assertOk();
        $this->postJson(route('voice.signal',$session),$payload)->assertStatus(409);
        $this->assertDatabaseCount('messages',0);
    }

    public function test_credentials_are_private_encrypted_and_only_ready_after_propagation(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $this->getJson(route('voice.credentials',$session))->assertOk()->assertJsonPath('iceTransportPolicy','relay')
            ->assertDontSee('private-master-secret')->assertDontSee('private-app-secret')->assertHeader('Cache-Control','no-store, private');
        $this->assertStringNotContainsString('temporary-password',\Illuminate\Support\Facades\DB::table('voice_turn_credentials')->value('payload'));
        VoiceTurnCredential::query()->delete();
        Http::preventStrayRequests();
        Http::fake(['*/credential?*'=>Http::response(['apiKey'=>'private-generated-key'],200),
            '*/credentials?*'=>Http::response([['urls'=>'stun:relay.metered.ca:80'],['urls'=>'turn:relay.metered.ca:443','username'=>'temporary-user','credential'=>'temporary-password']],200)]);
        $this->assertTrue(app(MeteredTurnService::class)->prepare());
        $this->assertFalse(app(VoiceConfiguration::class)->ready());
        $this->travel(2)->minutes();
        $this->assertTrue(app(VoiceConfiguration::class)->ready());
        $this->getJson(route('voice.credentials',$session))->assertOk()->assertDontSee('private-generated-key')->assertDontSee('stun:');
        $this->assertTrue(app(MeteredTurnService::class)->prepare());
        Http::assertSentCount(2);
    }

    public function test_timeouts_consent_withdrawal_and_session_deadline_stop_calls(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $call=$this->postJson(route('voice.start',$session))->json('call.id');
        $this->travel(46)->seconds();
        $this->getJson(route('voice.state',$session))->assertOk()->assertJsonPath('call.status','failed');
        $this->assertSame('active',$session->fresh()->session_status);
        $next=$this->postJson(route('voice.start',$session))->json('call.id');
        app(ConsentService::class)->decide($seeker,'voice_participation','withdrawn',$session->id);
        $this->getJson(route('voice.state',$session))->assertOk()->assertJsonPath('call.status','ended')->assertJsonPath('available',false);
        $this->getJson(route('voice.credentials',$session))->assertStatus(409);
        app(ConsentService::class)->decide($seeker,'voice_participation','accepted',$session->id);
        $session->update(['start_time'=>now()->subMinutes(90)]);
        $this->getJson(route('voice.state',$session))->assertOk()->assertJsonPath('session.ended',true);
        $this->assertSame('completed',$session->fresh()->session_status);
        $this->assertDatabaseCount('call_logs',0);
    }

    public function test_voice_preferences_require_specific_consent_and_keep_the_existing_queue(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $concern=ConcernCategory::create(['concern_name'=>'Study stress','is_active'=>true]);
        $session->update(['helper_id'=>null,'session_status'=>'screening_completed','start_time'=>null,'helper_accepted_at'=>null,
            'workflow_state'=>'session_preferences_required','concern_id'=>$concern->id,'risk_level'=>'low']);
        $this->post(route('request.preferences.process'),['support_mode'=>'voice','preferred_language'=>'English'])->assertSessionHasErrors('voice_participation');
        $this->post(route('request.preferences.process'),['support_mode'=>'voice','preferred_language'=>'English','voice_participation'=>'1'])->assertRedirect(route('request.matching'));
        $this->assertSame('voice',$session->fresh()->session_type);
        $this->assertSame('voice',$session->fresh()->queue->preferred_session_type);
        $this->assertFalse($session->fresh()->voice_recording_consent);
        $this->assertDatabaseHas('consent_records',['session_id'=>$session->id,'purpose'=>'voice_participation','decision'=>'accepted']);
    }

    public function test_preferences_show_voice_even_when_unavailable_and_enable_it_only_after_preparation(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $session->update(['helper_id'=>null,'session_status'=>'screening_completed','start_time'=>null,'helper_accepted_at'=>null,
            'workflow_state'=>'session_preferences_required','risk_level'=>'low']);
        config(['voice.enabled'=>false]);
        $this->get(route('request.preferences'))->assertOk()->assertSee('Voice call')->assertSee('value="voice"',false)
            ->assertSee('Voice calls are temporarily unavailable.')->assertSee('disabled',false);
        $this->post(route('request.preferences.process'),['support_mode'=>'voice','preferred_language'=>'English','voice_participation'=>'1'])
            ->assertSessionHasErrors('support_mode');
        config(['voice.enabled'=>true]);
        VoiceTurnCredential::query()->update(['ready_at'=>now()->addMinutes(2)]);
        $this->get(route('request.preferences'))->assertOk()->assertSee('Voice calls are getting ready.');
        $this->travel(2)->minutes();
        $this->get(route('request.preferences'))->assertOk()->assertSee('Voice calls are available.')->assertSee('voice_participation');
        $this->artisan('voice:status')->expectsOutput('Voice relay readiness: ready')->assertSuccessful();
    }

    public function test_failed_relay_preparation_logs_only_safe_diagnostics(): void
    {
        $this->room();
        VoiceTurnCredential::query()->delete();
        Http::preventStrayRequests();
        Http::fake(['*/credential?*'=>Http::response(['message'=>'private-master-secret'],401)]);
        \Illuminate\Support\Facades\Log::spy();
        $this->assertFalse(app(MeteredTurnService::class)->prepare());
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with('Voice relay preparation failed.',
            ['step'=>'create_credential','http_status'=>401]);
        $this->assertSame('relay_unavailable',app(VoiceConfiguration::class)->availability());
    }

    public function test_configuration_diagnostics_list_missing_names_without_exposing_values(): void
    {
        $this->room();
        config(['broadcasting.default'=>'log','voice.metered_secret'=>null]);
        $configuration=app(VoiceConfiguration::class);
        $this->assertSame('configuration_missing',$configuration->availability());
        $this->assertSame(['BROADCAST_CONNECTION','METERED_SECRET_KEY'],$configuration->configurationIssues());
        $this->artisan('voice:status')->expectsOutput('Voice relay readiness: configuration_missing')
            ->expectsOutput('Check Render environment entries: BROADCAST_CONNECTION, METERED_SECRET_KEY')->assertSuccessful();
        config(['voice.enabled'=>false]);
        $this->assertSame('disabled',$configuration->availability());
    }

    public function test_only_the_tab_with_a_local_call_can_heartbeat_and_missing_peer_times_out(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $call=$this->postJson(route('voice.start',$session))->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertOk();
        $seen=VoiceCall::findOrFail($call)->callee_seen_at->toIso8601String();
        $this->travel(30)->seconds();
        $this->getJson(route('voice.state',$session))->assertOk()->assertJsonPath('call.status','connecting');
        $this->assertSame($seen,VoiceCall::findOrFail($call)->callee_seen_at->toIso8601String());
        $this->actingAs($seeker)->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))->assertOk();
        $this->travel(16)->seconds();
        $this->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))->assertOk()->assertJsonPath('call.status','failed');
        $this->assertSame('active',$session->fresh()->session_status);
    }

    public function test_existing_session_completion_closes_call_metadata_without_creating_recordings(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $call=$this->postJson(route('voice.start',$session))->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertOk();
        $this->post(route('helper.session.end',$session))->assertRedirect(route('helper.session.notes',$session->id));
        $this->assertDatabaseHas('voice_calls',['id'=>$call,'status'=>'ended','end_reason'=>'session_changed']);
        $this->assertNotNull(VoiceCall::findOrFail($call)->ended_at);
        $this->assertDatabaseCount('call_logs',0);
    }

    public function test_voice_rooms_keep_text_fallback_and_never_accept_audio_uploads(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $this->get(route('session.voice'))->assertOk()->assertSee('data-voice-call',false)->assertDontSee('private-master-secret');
        $this->postJson(route('chat.send'),['session_id'=>$session->id,'message'=>'Typed fallback'])->assertOk();
        $this->postJson(route('chat.send'),['session_id'=>$session->id,'message'=>'audio','is_voice'=>true,'audio_url'=>'https://example.test/audio'])->assertUnprocessable();
        $this->assertDatabaseHas('messages',['session_id'=>$session->id,'message_text'=>'Typed fallback','is_transcript'=>true]);
        $this->assertDatabaseCount('call_logs',0);
    }

    public function test_both_roles_get_a_dedicated_voice_room_with_workflow_actions_and_a_single_bell_slot(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $page=$this->get(route('session.chat'))->assertOk()->assertViewIs('session.voice')
            ->assertSee('data-voice-room',false)->assertSee('id="voiceMessages"',false)
            ->assertSee('data-voice-retry',false)->assertSee('id="referralConsentDialog"',false)
            ->assertSee('id="chatIdentityDialog"',false)->assertSee('data-confirm="End support session?"',false)
            ->assertDontSee($helper->email)->assertDontSee('private-app-secret');
        $this->assertSame(1, substr_count($page->getContent(), 'data-notification-slot'));
        $this->actingAs($helper)->followingRedirects()->get(route('helper.session.chat', $session->id))->assertOk()
            ->assertViewIs('session.voice')->assertSee('PrivateFox42')->assertSee('id="emergencyBtn"',false)
            ->assertSee('id="referralModal"',false)->assertSee('id="emergencyModal"',false);
        $session->update(['session_type'=>'chat']);
        $this->actingAs($seeker)->get(route('session.chat'))->assertViewIs('session.chat')->assertDontSee('data-voice-room',false);
    }

    public function test_private_voice_subscription_authorizes_both_participants_and_rejects_unrelated_users(): void
    {
        [$seeker,$helper,$session]=$this->room();
        // The test app boots with the log broadcaster; register channels on the configured Reverb driver.
        require base_path('routes/channels.php');
        foreach ([$seeker,$helper] as $participant) {
            $this->actingAs($participant)->postJson('/broadcasting/auth', ['socket_id'=>'123.456',
                'channel_name'=>'private-voice.'.$session->id.'.'.$participant->id])->assertOk()->assertJsonStructure(['auth']);
            $this->postJson('/broadcasting/auth', ['socket_id'=>'123.456',
                'channel_name'=>'private-voice.'.$session->id.'.'.($participant->id === $seeker->id ? $helper->id : $seeker->id)])->assertForbidden();
        }
        $outsider=User::factory()->create(['role'=>'seeker']);
        $this->actingAs($outsider)->postJson('/broadcasting/auth', ['socket_id'=>'123.456',
            'channel_name'=>'private-voice.'.$session->id.'.'.$outsider->id])->assertForbidden();
    }

    public function test_authenticated_landing_page_does_not_mount_notification_popups_or_a_bell(): void
    {
        $this->room();
        $this->get(route('home'))->assertOk()->assertDontSee('name="compass-notices"',false)
            ->assertDontSee('js/live-notifications.js')->assertDontSee('js/important-notice.js');
    }

    public function test_offer_answer_and_candidates_are_recovered_through_authenticated_polling_without_websocket_delivery(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $call=$this->postJson(route('voice.start',$session))->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertOk();
        $sdp="v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\na=ice-ufrag:temporary-negotiation\r\n";
        $this->actingAs($seeker)->postJson(route('voice.signal',$session),['call_id'=>$call,'type'=>'offer','sdp'=>$sdp])->assertOk();
        $stored=\Illuminate\Support\Facades\DB::table('voice_signals')->sole();
        $this->assertStringNotContainsString('temporary-negotiation',$stored->payload);
        $this->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))->assertJsonCount(0,'signals');
        $offer=$this->actingAs($helper)->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))
            ->assertOk()->assertJsonPath('signaling_available',true)->assertJsonCount(1,'signals')->assertJsonPath('signals.0.type','offer');
        $this->assertSame($sdp,$offer->json('signals.0.sdp'));
        $this->actingAs(User::factory()->create(['role'=>'seeker']))->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))->assertForbidden();
        $this->actingAs($helper);
        $cursor=$offer->json('signal_cursor');
        $this->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call,'after_signal_id'=>$cursor]))->assertJsonCount(0,'signals');
        $this->postJson(route('voice.signal',$session),['call_id'=>$call,'type'=>'answer','sdp'=>$sdp])->assertOk();
        $this->actingAs($seeker)->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))->assertJsonPath('signals.0.type','answer');
        $this->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'leave'])->assertOk();
        $this->assertDatabaseCount('voice_signals',0);
        $this->assertSame('active',$session->fresh()->session_status);
    }

    public function test_failed_realtime_broadcast_does_not_discard_a_signal_or_expose_its_contents(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $call=$this->postJson(route('voice.start',$session))->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertOk();
        Event::fake()->except([VoiceSignal::class]);
        \Illuminate\Support\Facades\Broadcast::shouldReceive('queue')->once()->andThrow(new \RuntimeException('private broadcaster detail'));
        $this->actingAs($seeker)->postJson(route('voice.signal',$session),['call_id'=>$call,'type'=>'offer',
            'sdp'=>"v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\n"])->assertOk()->assertDontSee('private broadcaster detail');
        $this->actingAs($helper)->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))->assertJsonCount(1,'signals');
        \App\Models\VoiceSignalPacket::query()->update(['expires_at'=>now()->subSecond()]);
        $this->getJson(route('voice.state',['session'=>$session->id,'call_id'=>$call]))->assertJsonCount(0,'signals');
    }

    public function test_completion_pages_place_the_single_bell_in_a_header_outside_the_content_card(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $session->update(['session_status'=>'completed','end_time'=>now(),'duration'=>1]);
        $this->get(route('session.evaluation'))->assertOk()->assertSee('data-evaluation-header',false)->assertSee('data-notification-slot',false);
        $page=$this->get(route('session.thank-you'))->assertOk()->assertSee('class="completion-header"',false)
            ->assertSee('<main class="completion-main">',false)->assertSee('css/session-completion.css',false);
        $this->assertSame(1,substr_count($page->getContent(),'data-notification-slot'));
    }

    public function test_render_uses_its_current_public_hostname_even_when_voice_settings_were_copied_from_an_old_service(): void
    {
        $this->room();
        config(['voice.render_hostname'=>'current-compass.onrender.com','voice.ws_host'=>'old-compass.onrender.com',
            'voice.ws_port'=>8080,'voice.ws_scheme'=>'http']);
        $socket=app(VoiceConfiguration::class)->publicSocket();
        $this->assertSame('current-compass.onrender.com',$socket['host']);
        $this->assertSame(443,$socket['port']);$this->assertTrue($socket['secure']);
        $this->get(route('session.voice'))->assertOk()->assertSee('current-compass.onrender.com')->assertDontSee('old-compass.onrender.com');
        $this->artisan('voice:status')->expectsOutput('Voice signaling host: current-compass.onrender.com.')->assertSuccessful();
        config(['voice.render_hostname'=>null,'voice.ws_host'=>'127.0.0.1']);
        $this->assertSame(['key'=>'public-app-key','host'=>'127.0.0.1','port'=>8080,'secure'=>false],app(VoiceConfiguration::class)->publicSocket());
    }

    public function test_current_render_origin_is_allowed_without_accepting_unrelated_origins(): void
    {
        $repository=\Illuminate\Support\Env::getRepository();
        $previousHost=env('RENDER_EXTERNAL_HOSTNAME');$previousOrigins=env('REVERB_ALLOWED_ORIGINS');
        try {
            $repository->set('RENDER_EXTERNAL_HOSTNAME','current-compass.onrender.com');
            $repository->set('REVERB_ALLOWED_ORIGINS','old-compass.onrender.com');
            $configuration=require config_path('reverb.php');
            $this->assertSame(['old-compass.onrender.com','current-compass.onrender.com'],$configuration['apps']['apps'][0]['allowed_origins']);
        } finally {
            $previousHost === null ? $repository->clear('RENDER_EXTERNAL_HOSTNAME') : $repository->set('RENDER_EXTERNAL_HOSTNAME',$previousHost);
            $previousOrigins === null ? $repository->clear('REVERB_ALLOWED_ORIGINS') : $repository->set('REVERB_ALLOWED_ORIGINS',$previousOrigins);
        }
    }

    public function test_active_text_chat_enables_calls_only_after_seeker_consent_without_changing_session(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $session->update(['session_type'=>'chat']);
        $started=$session->start_time->toIso8601String();
        app(ConsentService::class)->decide($seeker,'voice_participation','withdrawn',$session->id);
        $this->postJson(route('voice.start',$session))->assertStatus(409);
        $this->actingAs($helper)->postJson(route('voice.consent',$session),['voice_participation'=>true])->assertForbidden();
        $this->postJson(route('voice.consent-request',$session))->assertOk();
        $this->postJson(route('voice.consent-request',$session))->assertOk();
        $this->assertSame(1,\App\Models\Notification::where('popup_event_key','voice-consent:'.$session->id)->count());
        $this->actingAs($seeker)->postJson(route('voice.consent',$session),[])->assertUnprocessable();
        $this->postJson(route('voice.consent',$session),['voice_participation'=>true])->assertOk();
        $count=ConsentRecord::count();
        $this->postJson(route('voice.consent',$session),['voice_participation'=>true])->assertOk();
        $this->assertSame($count,ConsentRecord::count());
        $call=$this->postJson(route('voice.start',$session))->assertOk()->json('call.id');
        $this->actingAs($helper)->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'accept'])->assertOk();
        $this->postJson(route('voice.action',$session),['call_id'=>$call,'action'=>'leave'])->assertOk();
        $this->assertSame('chat',$session->fresh()->session_type);
        $this->assertSame('active',$session->fresh()->session_status);
        $this->assertSame($started,$session->fresh()->start_time->toIso8601String());
    }

    public function test_five_minute_toast_claims_are_scoped_to_session_participant_and_deadline(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $this->postJson(route('session.notices.claim',$session),['event'=>'ending_soon'])->assertOk()->assertJsonPath('show',false);
        $session->update(['start_time'=>now()->subMinutes(85),'helper_accepted_at'=>now()->subMinutes(86)]);
        $this->postJson(route('session.notices.claim',$session),['event'=>'ending_soon'])->assertOk()->assertJsonPath('show',true);
        $this->postJson(route('session.notices.claim',$session),['event'=>'ending_soon'])->assertOk()->assertJsonPath('show',false);
        $this->actingAs($helper)->postJson(route('session.notices.claim',$session),['event'=>'ending_soon'])->assertOk()->assertJsonPath('show',true);
        $this->actingAs(User::factory()->create(['role'=>'seeker']))->postJson(route('session.notices.claim',$session),['event'=>'ending_soon'])->assertForbidden();
        $this->travel(6)->minutes();
        $this->actingAs($seeker)->postJson(route('session.notices.claim',$session),['event'=>'ending_soon'])->assertOk()->assertJsonPath('show',false);
    }

    public function test_connected_audio_is_not_reported_as_a_missing_text_response(): void
    {
        [$seeker,$helper,$session]=$this->room();
        $session->update(['start_time'=>now()->subMinutes(8)]);
        VoiceCall::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'session_id'=>$session->id,'caller_user_id'=>$seeker->id,'callee_user_id'=>$helper->id,
            'status'=>'connected','connected_at'=>now()->subMinutes(7),'caller_seen_at'=>now(),'callee_seen_at'=>now()]);
        $this->artisan('sessions:check-no-response')->assertSuccessful();
        $this->assertNull($session->fresh()->no_response_escalated_at);
        $this->actingAs($helper)->getJson(route('voice.state',$session))->assertOk();
        $this->assertTrue($session->fresh()->helper_heartbeat_at->gte(now()->subSecond()));
    }

    public function test_a_finished_audio_call_receives_a_new_response_window_and_reports_actual_elapsed_time(): void
    {
        [$seeker, $helper, $session] = $this->room();
        $session->update(['start_time' => now()->subMinutes(20)]);
        $moderator = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        VoiceCall::create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'session_id' => $session->id,
            'caller_user_id' => $seeker->id, 'callee_user_id' => $helper->id,
            'status' => 'ended', 'connected_at' => now()->subMinutes(18), 'ended_at' => now()->subMinute(),
        ]);

        $this->artisan('sessions:check-no-response')->assertSuccessful();
        $this->assertNull($session->fresh()->no_response_escalated_at);
        $this->travel(15)->minutes();
        $this->artisan('sessions:check-no-response')->assertSuccessful();
        $this->assertNotNull($session->fresh()->no_response_escalated_at);
        $notice = \App\Models\Notification::where('user_account_id', $moderator->id)
            ->where('title', 'Helper not responding')->sole();
        $this->assertStringContainsString('16 minutes when detected', $notice->message);
        $this->artisan('sessions:check-no-response')->assertSuccessful();
        $this->assertSame(1, \App\Models\Notification::where('user_account_id', $moderator->id)
            ->where('title', 'Helper not responding')->count());
    }

}
