const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
// public/js is ESM by package type, so load the browser script in a sandbox with a CommonJS module object.
const sandbox={module:{exports:{}},JSON,Promise,Map,Error,Math,Number};
vm.runInNewContext(fs.readFileSync('public/js/voice-listen.js','utf8'),sandbox);
const {ListenSession}=sandbox.module.exports;

function harness(states){
    const posts=[],peers=[],tracks=[],statuses=[],ended=[];
    class Peer{
        constructor(config){this.config=config;this.remoteDescription=null;this.candidates=[];this.connectionState='new';peers.push(this);}
        async setRemoteDescription(d){this.remoteDescription=d;}
        async createAnswer(){return {type:'answer',sdp:'v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\na=recvonly\r\n'};}
        async setLocalDescription(d){this.localDescription=d;}
        async addIceCandidate(c){this.candidates.push(c);}
        close(){this.closed=true;}
    }
    const env={csrf:'csrf',RTCPeerConnection:Peer,setTimeout:()=>1,clearTimeout(){},
        fetch:async(url,options)=>{
            if(options?.method==='POST'){posts.push({url,body:JSON.parse(options.body)});
                const data=url.endsWith('/listen')?{listen_id:4,iceServers:[{urls:'turn:relay.example:443'}]}:{ok:true};
                return {ok:true,status:200,json:async()=>data};}
            return {ok:true,status:200,json:async()=>states.shift()||{active:true,signals:[],cursor:0}};
        }};
    const session=new ListenSession(env,'/adviser/live/9/listen',{onTrack:(from)=>tracks.push(from),onStatus:(t,s)=>statuses.push(s),onEnd:(t)=>ended.push(t)});
    return {session,posts,peers,tracks,statuses,ended};
}

test('start sends the purpose and reason, then answers each participant receive-only through the relay',async()=>{
    const h=harness([{active:true,cursor:3,signals:[
        {type:'candidate',from:'helper',candidate:{candidate:'early'}},
        {type:'offer',from:'seeker',sdp:'seeker-offer'},
        {type:'offer',from:'helper',sdp:'helper-offer'},
    ]}]);
    await h.session.start('quality_assurance','Checking a first call for quality.');
    assert.deepEqual(h.posts[0],{url:'/adviser/live/9/listen',body:{purpose:'quality_assurance',reason:'Checking a first call for quality.'}});
    assert.equal(h.peers.length,2);
    assert.equal(h.peers[0].config.iceTransportPolicy,'relay');
    const answers=h.posts.filter(p=>p.body.type==='answer');
    assert.deepEqual(answers.map(a=>[a.url,a.body.to]),[['/adviser/live/9/listen/4/signal','seeker'],['/adviser/live/9/listen/4/signal','helper']]);
    assert.match(answers[0].body.sdp,/a=recvonly/);
    assert.equal(h.session.cursor,3);
});

test('a candidate that arrives before its offer is ignored; later ones apply to the right participant',async()=>{
    const h=harness([{active:true,cursor:2,signals:[{type:'offer',from:'helper',sdp:'o'},{type:'candidate',from:'helper',candidate:{candidate:'c1'}}]}]);
    await h.session.start('quality_assurance','Checking a first call for quality.');
    assert.deepEqual(h.peers[0].candidates,[{candidate:'c1'}]);
});

test('listening stops cleanly when the call ends or the Adviser stops',async()=>{
    const ended=harness([{active:false,signals:[]}]);
    await ended.session.start('quality_assurance','Checking a first call for quality.');
    assert.equal(ended.session.active,false);
    assert.deepEqual(ended.ended,['The call ended, so listening stopped.']);

    const h=harness([{active:true,cursor:1,signals:[{type:'offer',from:'seeker',sdp:'o'}]}]);
    await h.session.start('quality_assurance','Checking a first call for quality.');
    await h.session.stop();
    assert.equal(h.peers[0].closed,true);
    assert.equal(h.posts.at(-1).url,'/adviser/live/9/listen/4/stop');
    assert.deepEqual(h.ended,['You stopped listening.']);
});
