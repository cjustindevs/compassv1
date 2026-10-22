import test from 'node:test';
import assert from 'node:assert/strict';
import { VoiceCallClient } from '../../resources/js/voice-call-client.mjs';

function room() {
    const elements = {};
    for (const name of ['audio','status','badge','title','duration','start','accept','decline','mute','leave','play','retry','microphone','microphone-status',
        'detail-microphone','detail-server','detail-signaling','detail-audio','detail-error']) {
        elements[name]={hidden:false,disabled:false,textContent:'',attributes:{},handlers:{},
            addEventListener(event,handler){this.handlers[event]=handler;},setAttribute(key,value){this.attributes[key]=value;},async play(){}};
    }
    const track={kind:'audio',enabled:true,stopped:false,stop(){this.stopped=true;}};
    const stream={getTracks:()=>[track],getAudioTracks:()=>[track]};
    const requests=[];
    const callbacks={};
    let state={available:true,session:{ended:false},call:null};
    class Peer {
        constructor(configuration){this.configuration=configuration;this.connectionState='new';this.candidates=[];}
        addTrack(){}
        async createOffer(){return {type:'offer',sdp:'audio-offer'};}
        async createAnswer(){return {type:'answer',sdp:'audio-answer'};}
        async setLocalDescription(description){this.localDescription=description;}
        async setRemoteDescription(description){this.remoteDescription=description;}
        async addIceCandidate(candidate){this.candidates.push(candidate);}
        close(){this.closed=true;}
    }
    const config=Object.fromEntries(['state','credentials','start','action','signal'].map(key=>[key,'/'+key]));
    const env={window:{addEventListener:(event,handler)=>{callbacks[event]=handler;}},navigator:{mediaDevices:{getUserMedia:async()=>stream}},
        RTCPeerConnection:Peer,MediaStream:class{},csrf:'csrf',setInterval:()=>1,clearInterval(){},setTimeout:()=>2,clearTimeout(){},
        fetch:async(url,options)=>{
            const body=options.body ? JSON.parse(options.body) : null;
            requests.push({url,body,options});
            let data={};
            if(url.startsWith('/state')) data=state;
            if(url==='/credentials') data={iceServers:[{urls:'turn:example.test:443',username:'temporary',credential:'temporary'}]};
            if(url==='/start') data={call:{id:'current-call',is_caller:true,status:'ringing'}};
            if(url==='/action') data={call:{id:body.call_id,is_caller:true,status:body.action==='accept'?'connecting':'ended'}};
            return {ok:true,status:200,json:async()=>data};
        }};
    const root={attributes:{},setAttribute(key,value){this.attributes[key]=value;},querySelector:selector=>elements[selector.match(/data-voice-([\w-]+)/)[1]]};
    const client=new VoiceCallClient(root,config,env);
    client.available=true;client.signalReady=true;
    return {client,env,track,elements,requests,callbacks,setState:value=>{state=value;}};
}

test('outgoing call requires relay-only audio and leaving releases microphone without ending support',async()=>{
    const {client,track,requests}=room();
    await client.begin(false);
    const peer=client.pc;
    assert.equal(peer.configuration.iceTransportPolicy,'relay');
    assert.equal(client.call.status,'ringing');
    client.mute();assert.equal(track.enabled,false);
    client.mute();assert.equal(track.enabled,true);
    await client.leave();
    assert.equal(track.stopped,true);assert.equal(peer.closed,true);
    assert.equal(client.ended,false);
    assert.deepEqual(requests.at(-1).body,{call_id:'current-call',action:'leave'});
    assert.equal(requests.some(request=>request.url.includes('session/end')),false);
});

function connectRoom() {
    const r=room(), channels=[], timers=new Map(), events={};let serial=0, reconnects=0;
    r.client.signalReady=false;
    r.env.setTimeout=(fn,delay)=>{const id=++serial;timers.set(id,{fn,delay});return id;};
    r.env.clearTimeout=id=>timers.delete(id);
    const echo={connector:{pusher:{connection:{bind:(event,fn)=>{events[event]=fn;}},connect(){reconnects++;}}},
        private(name){const handlers={};channels.push({name,handlers});return {subscribed(fn){handlers.ready=fn;return this;},error(fn){handlers.error=fn;return this;},listen(){return this;}};},
        leave(){},disconnect(){}};
    r.client.connect(echo);
    return {...r,channels,timers,events,reconnects:()=>reconnects,flush:()=>new Promise(resolve=>setImmediate(resolve))};
}

test('initial private subscription times out and polling cannot erase authorization failures',async()=>{
    const r=connectRoom();await r.flush();
    const deadline=r.client.transportTimer;r.events.disconnected();assert.equal(r.client.transportTimer,deadline);
    [...r.timers.values()].find(timer=>timer.delay===20000).fn();
    assert.match(r.elements.status.textContent,/took too long/);
    await r.client.poll();assert.match(r.elements.status.textContent,/took too long/);
    assert.equal(r.elements.retry.hidden,false);assert.equal(r.elements.start.disabled,true);
    r.channels[0].handlers.error({status:403});
    await r.client.poll();assert.match(r.elements.status.textContent,/authorize/);
    r.events.disconnected();await r.client.poll();assert.match(r.elements.status.textContent,/authorize/);
    r.client.destroy();
});

test('retry ignores a stale authorization callback and enables Call only after the new private subscription succeeds',async()=>{
    const r=connectRoom();await r.flush();r.channels[0].handlers.error({status:403});
    r.client.retryConnection();await r.flush();assert.equal(r.reconnects(),1);
    r.channels[0].handlers.ready();assert.equal(r.client.signalReady,false);
    r.channels.at(-1).handlers.ready();await r.flush();
    assert.equal(r.client.signalReady,true);assert.equal(r.elements.retry.hidden,true);
    assert.equal(r.elements.start.disabled,false);assert.equal(r.elements.badge.textContent,'Ready');
    r.client.destroy();
});

test('a slow state response cannot cancel microphone preparation or overwrite an ended session',async()=>{
    const r=room();let resolveState, resolveMicrophone;
    const fetch=r.env.fetch, microphone=r.env.navigator.mediaDevices.getUserMedia;
    r.env.fetch=(url,options)=>url.startsWith('/state')?new Promise(resolve=>{resolveState=resolve;}):fetch(url,options);
    r.env.navigator.mediaDevices.getUserMedia=()=>new Promise(resolve=>{resolveMicrophone=resolve;});
    const poll=r.client.poll(), begin=r.client.begin(false);
    resolveState({ok:true,json:async()=>({available:true,session:{ended:false},call:null})});await poll;
    resolveMicrophone(await microphone());await begin;
    assert.equal(r.client.call.status,'ringing');assert.equal(r.track.stopped,false);
    const late=r.client.poll();r.client.endLocally();
    resolveState({ok:true,json:async()=>({available:true,session:{ended:false},call:null})});await late;
    assert.equal(r.client.ended,true);assert.match(r.elements.status.textContent,/support session has ended/);
});

test('a stalled response body is aborted and produces a retryable error',async()=>{
    const r=room();let abort;
    r.env.AbortController=AbortController;
    r.env.setTimeout=(fn,delay)=>{assert.equal(delay,15000);abort=fn;return 9;};
    r.env.fetch=async(url,options)=>({ok:true,json:()=>new Promise((resolve,reject)=>{
        options.signal.addEventListener('abort',()=>{const e=new Error();e.name='AbortError';reject(e);});
    })});
    const request=r.client.request('state');await Promise.resolve();abort();
    await assert.rejects(request,/did not respond/);
});

test('authenticated signal polling enables Call and recovers an answer when no WebSocket exists',async()=>{
    const r=room();r.client.signalReady=false;
    r.setState({available:true,signaling_available:true,session:{ended:false},call:null});
    await r.client.poll();assert.equal(r.elements.start.disabled,false);
    await r.client.begin(false);
    r.setState({available:true,signaling_available:true,session:{ended:false},call:{...r.client.call,status:'connecting'},signals:[],signal_cursor:0});
    await r.client.poll();assert.equal(r.requests.filter(request=>request.body?.type==='offer').length,1);
    r.setState({available:true,signaling_available:true,session:{ended:false},call:{...r.client.call,status:'connecting'},
        signals:[{signal_id:7,call_id:r.client.call.id,type:'answer',sdp:'recovered-answer'}],signal_cursor:7});
    await r.client.poll();assert.equal(r.client.pc.remoteDescription.sdp,'recovered-answer');assert.equal(r.client.signalCursor,7);
    assert.equal(r.elements.duration.hidden,true);
});

test('WebSocket and polling deliveries of one signal are applied once and in sequence',async()=>{
    const r=room();await r.client.begin(false);const applied=[];
    r.client.pc.setRemoteDescription=async description=>{await new Promise(resolve=>setImmediate(resolve));applied.push(description.type);r.client.pc.remoteDescription=description;};
    r.client.pc.addIceCandidate=async()=>{applied.push('candidate');};
    const answer={signal_id:8,call_id:r.client.call.id,type:'answer',sdp:'audio-answer'};
    const candidate={signal_id:9,call_id:r.client.call.id,type:'candidate',candidate:{candidate:'relay'}};
    await Promise.all([r.client.enqueueSignal(answer),r.client.enqueueSignal(candidate),r.client.enqueueSignal(answer)]);
    assert.deepEqual(applied,['answer','candidate']);
});

test('ending audio detaches stale callbacks and releases both local and received tracks',async()=>{
    const r=room();await r.client.begin(false);const peer=r.client.pc;
    let receiverStopped=false,transceiverStopped=false;
    peer.getTransceivers=()=>[{stop(){transceiverStopped=true;throw Error('Already closed');}}];
    r.elements.audio.srcObject={getTracks:()=>[{stop(){receiverStopped=true;}}]};
    r.client.stopMedia();
    assert.equal(receiverStopped,true);assert.equal(transceiverStopped,true);
    assert.equal(peer.ontrack,null);assert.equal(peer.onicecandidate,null);assert.equal(peer.closed,true);
});

test('microphone refusal and failed credential delivery never leave capture running',async()=>{
    const denied=room();
    denied.env.navigator.mediaDevices.getUserMedia=async()=>{const error=new Error('Denied');error.name='NotAllowedError';throw error;};
    await denied.client.begin(false);
    assert.match(denied.elements.status.textContent,/Microphone access was denied/);
    assert.equal(denied.requests.length,0);
    const unavailable=room();
    unavailable.env.fetch=async()=>({ok:false,status:503,json:async()=>({message:'Relay unavailable'})});
    await unavailable.client.begin(false);
    assert.equal(unavailable.track.stopped,true);
    assert.equal(unavailable.client.pc,null);
});

test('early ICE is queued, stale call signals ignored, and Connected waits for actual transport',async()=>{
    const {client,elements,requests}=room();
    await client.begin(false);
    await client.receive({call_id:'old-call',type:'offer',sdp:'stale'});
    assert.equal(client.pc.remoteDescription,undefined);
    await client.receive({call_id:'current-call',type:'candidate',candidate:{candidate:'relay'}});
    assert.equal(client.pc.candidates.length,0);
    await client.receive({call_id:'current-call',type:'answer',sdp:'audio-answer'});
    assert.equal(client.pc.candidates.length,1);
    assert.doesNotMatch(elements.status.textContent,/Connected/);
    client.pc.connectionState='connected';client.pc.onconnectionstatechange();
    assert.match(elements.status.textContent,/Connected/);
    assert.equal(requests.at(-1).body.action,'connected');
});

test('server session deadline and page exit close audio instead of attempting a reconnect',async()=>{
    const deadline=room();await deadline.client.begin(false);const peer=deadline.client.pc;
    deadline.setState({available:false,session:{ended:true},call:null});
    await deadline.client.poll();
    assert.equal(deadline.track.stopped,true);assert.equal(peer.closed,true);assert.equal(deadline.client.ended,true);
    const exiting=room();await exiting.client.begin(false);exiting.callbacks.pagehide();
    assert.equal(exiting.track.stopped,true);assert.equal(exiting.client.ended,true);
    assert.equal(exiting.requests.at(-1).options.keepalive,true);
});

test('the last server deadline stops capture even if later state requests fail',async()=>{
    const {client,env,track,setState}=room();
    let deadline;
    await client.begin(false);
    env.setTimeout=(callback,delay)=>{assert.equal(delay,1000);deadline=callback;return 3;};
    setState({available:true,session:{ended:false,remaining_seconds:1},call:client.call});
    await client.poll();
    env.fetch=async()=>{throw new Error('Network unavailable');};
    await client.poll();
    assert.equal(client.ended,false);
    deadline();
    assert.equal(track.stopped,true);
    assert.equal(client.ended,true);
});

test('call UI distinguishes ringing, connecting and actual on-call audio with elapsed time',async()=>{
    const {client,env,elements,setState}=room();
    let timestamp=100000;
    env.now=()=>timestamp;
    await client.begin(false);
    assert.equal(elements.badge.textContent,'Ringing');
    assert.equal(elements.leave.textContent,'Cancel call');
    assert.equal(elements.duration.hidden,true);
    setState({available:true,session:{ended:false},call:{...client.call,status:'connected'}});
    await client.poll();
    assert.equal(elements.badge.textContent,'Connecting');
    assert.equal(elements.duration.hidden,true);
    client.pc.connectionState='connected';client.pc.onconnectionstatechange();
    assert.equal(elements.badge.textContent,'On call');
    assert.equal(elements.duration.textContent,'00:00');
    timestamp+=65000;client.updateDuration();
    assert.equal(elements.duration.textContent,'01:05');
    client.pc.connectionState='disconnected';client.pc.onconnectionstatechange();
    assert.equal(elements.badge.textContent,'Reconnecting');
    client.pc.connectionState='connected';client.pc.onconnectionstatechange();
    assert.equal(elements.badge.textContent,'On call');
    assert.equal(elements.duration.textContent,'01:05');
    await client.leave();
    assert.equal(elements.badge.textContent,'Call ended');
    assert.equal(elements.duration.hidden,true);
});

test('incoming call UI requires explicit acceptance and keeps the microphone off while ringing',async()=>{
    const {client,elements,setState,requests}=room();
    setState({available:true,session:{ended:false},call:{id:'incoming-call',status:'ringing',is_caller:false}});
    await client.poll();
    assert.equal(elements.badge.textContent,'Incoming call');
    assert.equal(elements.accept.hidden,false);
    assert.equal(elements.decline.hidden,false);
    assert.equal(client.stream,null);
    assert.equal(requests.some(request=>request.url==='/credentials'),false);
    await client.begin(true);
    assert.equal(elements.badge.textContent,'Connecting');
    assert.equal(elements.accept.hidden,true);
    assert.equal(elements.duration.hidden,true);
});

test('Check microphone requests capture from the click without signaling readiness and releases it without placing a call',async()=>{
    const r=room();r.client.available=false;r.client.signalReady=false;r.client.httpReady=false;r.client.render();
    let requested=false;
    r.env.navigator.mediaDevices.getUserMedia=constraints=>{
        requested=true;assert.equal(constraints.video,false);
        assert.equal(r.requests.length,0);return Promise.resolve({getTracks:()=>[r.track]});
    };
    assert.equal(r.elements.microphone.disabled,false);
    const check=r.client.checkMicrophone();assert.equal(requested,true);await check;
    assert.equal(r.track.stopped,true);assert.equal(r.client.stream,null);assert.equal(r.client.call,null);
    assert.equal(r.requests.length,0);assert.match(r.elements['microphone-status'].textContent,/Microphone allowed/);
});

test('a silent Safari permission request times out, avoids a duplicate request, and stops a late granted stream',async()=>{
    const r=room();let grant,timeout,requests=0;
    r.env.navigator.userAgent='Mozilla/5.0 (iPhone) Version/18.0 Mobile Safari/604.1';
    r.env.navigator.mediaDevices.getUserMedia=()=>{requests++;return new Promise(resolve=>{grant=resolve;});};
    r.env.setTimeout=(fn,delay)=>{assert.equal(delay,20000);timeout=fn;return 4;};
    const attempt=r.client.begin(false);
    assert.equal(requests,1);assert.equal(r.client.busy,true);
    timeout();await attempt;
    assert.equal(r.client.busy,false);assert.equal(r.client.pc,null);assert.equal(r.requests.length,0);
    assert.match(r.elements.status.textContent,/20 seconds/);assert.match(r.elements.status.textContent,/In Safari/);
    assert.equal(r.elements.start.disabled,false);
    await r.client.checkMicrophone();assert.equal(requests,1);
    grant({getTracks:()=>[r.track]});await new Promise(resolve=>setImmediate(resolve));
    assert.equal(r.track.stopped,true);assert.equal(r.client.microphonePending,false);
    assert.equal(r.client.call,null);
});

test('ending a session while Safari waits for permission stops late capture and preserves the ended screen',async()=>{
    const r=room();let grant;
    r.env.navigator.mediaDevices.getUserMedia=()=>new Promise(resolve=>{grant=resolve;});
    const attempt=r.client.begin(false);r.client.endLocally();
    grant({getTracks:()=>[r.track]});await attempt;
    assert.equal(r.track.stopped,true);assert.equal(r.client.pc,null);assert.equal(r.requests.length,0);
    assert.equal(r.elements.badge.textContent,'Call ended');assert.match(r.elements.status.textContent,/support session has ended/);
});

test('blocked Safari permission, missing hardware, and page policy produce distinct actionable microphone errors',async()=>{
    const denied=room();denied.env.navigator.userAgent='Version/18.0 Safari/604.1';
    denied.env.navigator.mediaDevices.getUserMedia=async()=>{const error=new Error('Denied');error.name='NotAllowedError';throw error;};
    await denied.client.checkMicrophone();assert.match(denied.elements['microphone-status'].textContent,/Website Settings/);
    assert.match(denied.elements['detail-error'].textContent,/denied or blocked/);
    const missing=room();missing.env.navigator.mediaDevices.getUserMedia=async()=>{const error=new Error();error.name='NotFoundError';throw error;};
    await missing.client.begin(false);assert.match(missing.elements.status.textContent,/No microphone was found/);
    const blocked=room();let captured=false;
    blocked.env.document={permissionsPolicy:{features:()=>['microphone'],allowsFeature:()=>false}};
    blocked.env.navigator.mediaDevices.getUserMedia=async()=>{captured=true;};
    await blocked.client.begin(false);assert.equal(captured,false);assert.match(blocked.elements.status.textContent,/embedded page/);
    assert.equal(blocked.requests.length,0);
    const unsupported=room();unsupported.env.document={featurePolicy:{allowsFeature:()=>false}};
    await unsupported.client.checkMicrophone();assert.equal(unsupported.track.stopped,true);
    assert.match(unsupported.elements['microphone-status'].textContent,/Microphone allowed/);
});

test('audio negotiation has a local deadline even when the server keeps reporting Connecting',async()=>{
    const r=room(),timers=new Map();let serial=0;
    r.env.setTimeout=(fn,delay)=>{const id=++serial;timers.set(id,{fn,delay});return id;};
    r.env.clearTimeout=id=>timers.delete(id);
    await r.client.begin(false);await r.client.offer();
    const oldCall={...r.client.call,status:'connecting'};
    const deadline=[...timers.values()].find(timer=>timer.delay===45000);
    assert.ok(deadline);deadline.fn();await new Promise(resolve=>setImmediate(resolve));
    assert.equal(r.client.pc,null);assert.equal(r.track.stopped,true);
    assert.equal(r.requests.at(-1).body.action,'fail');assert.match(r.elements.status.textContent,/45 seconds/);
    r.setState({available:true,signaling_available:true,session:{ended:false},call:oldCall});
    await r.client.poll();assert.equal(r.elements.badge.textContent,'Call failed');assert.equal(r.elements.start.hidden,false);
    assert.match(r.elements.status.textContent,/45 seconds/);
});

test('successful audio and manual leaving clear the local connection deadline',async()=>{
    for (const finish of ['connected','leave']) {
        const r=room(),timers=new Map();let serial=0;
        r.env.setTimeout=(fn,delay)=>{const id=++serial;timers.set(id,{fn,delay});return id;};r.env.clearTimeout=id=>timers.delete(id);
        await r.client.begin(false);await r.client.offer();
        assert.ok([...timers.values()].some(timer=>timer.delay===45000));
        if (finish==='connected') { r.client.pc.connectionState='connected';r.client.pc.onconnectionstatechange(); }
        else await r.client.leave();
        assert.equal([...timers.values()].some(timer=>timer.delay===45000),false);
    }
});

test('a timer setup failure cannot start capture or strand microphone setup as pending',async()=>{
    const r=room();let captured=false;
    r.env.setTimeout=()=>{throw new TypeError('Illegal invocation');};
    r.env.navigator.mediaDevices.getUserMedia=async()=>{captured=true;return {getTracks:()=>[r.track]};};
    await r.client.checkMicrophone();
    assert.equal(captured,false);assert.equal(r.client.microphonePending,false);assert.equal(r.client.busy,false);
    assert.equal(r.requests.length,0);
});

test('availability polling starts before socket setup and survives a synchronous subscription failure without duplicate intervals',async()=>{
    const r=room();let intervals=0;
    r.client.signalReady=false;r.env.setInterval=()=>++intervals;
    r.setState({available:true,signaling_available:true,session:{ended:false},call:null});
    assert.throws(()=>r.client.connect({private(){throw Error('Socket subscription failed');}}),/Socket subscription failed/);
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(r.client.httpReady,true);assert.equal(r.elements.start.disabled,false);
    assert.equal(r.elements.badge.textContent,'Ready');assert.equal(intervals,2);
    r.client.connect(null);assert.equal(intervals,2);
});


test('an incoming call opens chat controls once and subsequent polling respects dismissal',async()=>{
    const r=room(), toggle={setAttribute(){}};
    r.client.root.dataset={chat:'true'};r.client.root.hidden=true;
    r.env.document={querySelector:()=>toggle};
    r.setState({available:true,signaling_available:true,session:{ended:false},call:{id:'incoming-one',is_caller:false,status:'ringing'}});
    await r.client.poll();assert.equal(r.client.root.hidden,false);
    r.client.root.hidden=true;
    await r.client.poll();assert.equal(r.client.root.hidden,true);
    r.setState({available:true,signaling_available:true,session:{ended:false},call:{id:'incoming-two',is_caller:false,status:'ringing'}});
    await r.client.poll();assert.equal(r.client.root.hidden,false);
});
