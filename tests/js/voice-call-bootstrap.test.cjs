const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function initialize({invalidConfig=false,clientFails=false,socketFails=false}={}) {
    const elements={config:{textContent:invalidConfig?'invalid JSON':JSON.stringify({socket:{key:'public-key',host:'compass.example',port:443,secure:true}})},title:{},status:{}};
    const root={attributes:{},setAttribute(name,value){this.attributes[name]=value;},querySelector:selector=>elements[selector.match(/data-voice-(\w+)/)[1]]};
    const instances=[];
    class Client {
        constructor(root,config,env){if(clientFails)throw Error('Private initialization details');this.env=env;instances.push(this);}
        connect(echo){this.transport=echo;this.interval=1;}
        transportFailed(message){this.failure=message;}
    }
    const context={document:{readyState:'complete',querySelectorAll:()=>[root],querySelector:()=>({content:'csrf-token'})},
        window:{location:{hostname:'compass.example'},fetch(){}},navigator:{},VoiceCallClient:Client,
        Pusher:class{constructor(){if(socketFails)throw Error('Socket constructor failed');}},Echo:class{},
        setInterval(){},clearInterval(){},setTimeout(){},clearTimeout(){}};
    const source=fs.readFileSync('resources/js/voice-call.js','utf8').replace(/^import .*;\r?\n/gm,'');
    vm.runInNewContext(source,context);
    return {root,elements,instances,context};
}

test('call initialization failures replace the checking screen with a recoverable message',()=>{
    for(const options of [{invalidConfig:true},{clientFails:true}]){
        const r=initialize(options);
        assert.equal(r.root.attributes['data-voice-state'],'unavailable');
        assert.match(r.elements.title.textContent,/could not start/);
        assert.match(r.elements.status.textContent,/Reload/);
        assert.doesNotMatch(r.elements.status.textContent,/Private initialization/);
    }
});

test('a failed socket constructor starts authenticated fallback and still initializes microphone controls',()=>{
    const r=initialize({socketFails:true});
    assert.equal(r.instances.length,1);assert.equal(r.instances[0].transport,null);
    assert.equal(r.instances[0].env.document,r.context.document);
    assert.match(r.instances[0].failure,/secure call connection/);
});
