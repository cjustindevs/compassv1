const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');

function page(storage=new Map(),user=7,blocked=false){
    const store={getItem:key=>{if(blocked)throw Error();return storage.get(key)||null;},setItem:(key,value)=>{if(blocked)throw Error();storage.set(key,value);}};
    const context={window:{},localStorage:store,sessionStorage:store,document:{querySelector:()=>({content:JSON.stringify({user})})}};
    vm.runInNewContext(fs.readFileSync('public/js/notification-presentation.js','utf8'),context);
    return context.window.CompassPopupPresentation;
}

test('same user shares presented IDs across navigation and tabs without hiding a new event',()=>{
    const storage=new Map(),one=page(storage),two=page(storage);
    assert.equal(one.claim('notification:3'),true);
    assert.equal(two.claim('notification:3'),false);
    assert.equal(page(storage).claim('notification:3'),false);
    assert.equal(two.claim('notification:4'),true);
    assert.equal(page(storage,8).claim('notification:3'),true);
});

test('emergency feed and emergency dialog share a case key across duplicate notices',()=>{
    const api=page();
    const feed=api.key({id:2,type:'emergency',popup_key:'emergency:alert:6'});
    const dialog=api.key({id:9,type:'emergency',case_key:'alert:6'});
    assert.equal(feed,dialog);assert.equal(api.claim(feed),true);assert.equal(api.claim(dialog),false);
    assert.equal(api.claim(api.key({id:10,type:'emergency',case_key:'alert:7'})),true);
});

test('blocked or malformed storage still deduplicates within the page',()=>{
    const api=page(new Map(),7,true);assert.equal(api.claim('notification:3'),true);assert.equal(api.claim('notification:3'),false);
    const corrupt=page(new Map([['compass-popup-seen:7','not json']]));assert.equal(corrupt.claim('notification:3'),true);
});

test('fresh browsers and hostnames ask the server before presenting the same recipient event',async()=>{
    const delivered=new Set(),requests=[];
    function device(){
        const context={window:{},document:{querySelector:selector=>({content:selector.includes('csrf')?'csrf':JSON.stringify({user:7})})},
            fetch:async(url,options)=>{requests.push({url,options});const first=!delivered.has(url);delivered.add(url);return {ok:true,json:async()=>({present:first})};}};
        vm.runInNewContext(fs.readFileSync('public/js/notification-presentation.js','utf8'),context);
        return context.window.CompassPopupPresentation;
    }
    const notice={id:42,popup_key:'new-referral:42'};
    assert.equal(await device().claimRemote(notice),true);
    assert.equal(await device().claimRemote(notice),false);
    assert.equal(requests[0].url,'/notifications/42/present');
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'],'csrf');
});

test('failed popup claims can retry and unsafe presentation URLs are never fetched',async()=>{
    let requests=0,fail=true;
    const context={window:{},document:{querySelector:()=>({content:JSON.stringify({user:7})})},fetch:async()=>{
        requests++;if(fail)throw Error('offline');return {ok:true,json:async()=>({present:true})};}};
    vm.runInNewContext(fs.readFileSync('public/js/notification-presentation.js','utf8'),context);
    const api=context.window.CompassPopupPresentation,notice={id:4};
    assert.equal(await api.claimRemote(notice),false);fail=false;
    assert.equal(await api.claimRemote(notice),true);assert.equal(requests,2);
    assert.equal(await api.claimRemote({id:5,present_url:'//other.test/present'}),false);assert.equal(requests,2);
});
