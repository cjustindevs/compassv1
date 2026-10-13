import test from 'node:test';
import assert from 'node:assert/strict';
import { VoiceCallClient } from '../../resources/js/voice-call-client.mjs';

function room() {
    const elements = {};
    for (const name of ['audio','status','badge','title','duration','start','accept','decline','mute','leave','play']) {
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
    const root={attributes:{},setAttribute(key,value){this.attributes[key]=value;},querySelector:selector=>elements[selector.match(/data-voice-(\w+)/)[1]]};
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
    env.setTimeout=(callback,delay)=>{assert.equal(delay,1000);deadline=callback;return 3;};
    await client.begin(false);
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
