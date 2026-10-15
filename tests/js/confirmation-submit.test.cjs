const {test}=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const vm=require('node:vm');

function page(){
    const source=readFileSync('resources/js/toast.js','utf8');
    const start=source.indexOf('function initConfirmDelegation()');
    const end=source.indexOf('function convertFlashAlerts()',start);
    const handlers={},payloads=[],questions=[];let answer;
    const fields=[{name:'_token',value:'csrf'}];
    const form={dataset:{confirm:'Confirm decision'},closest(){return this;},appendChild(input){fields.push(input);},
        submit(){payloads.push(Object.fromEntries(fields.map(field=>[field.name,field.value])));}};
    const document={addEventListener:(name,fn)=>handlers[name]=fn,createElement(){const input={remove(){fields.splice(fields.indexOf(input),1);}};return input;}};
    vm.runInNewContext(source.slice(start,end)+'\ninitConfirmDelegation();',{
        document,uiIcon:()=>'',confirmAction:options=>{questions.push(options);return new Promise(resolve=>answer=resolve);},
    });
    return {form,payloads,questions,fields,answer:value=>answer(value),submit:button=>handlers.submit({target:form,submitter:button,preventDefault(){}})};
}

for(const choice of ['0','1']) test(`confirmation preserves named submitter value ${choice}`,async()=>{
    const room=page(),pending=room.submit({name:'consent_given',value:choice});
    assert.equal(room.payloads.length,0);room.answer(true);await pending;
    assert.equal(room.payloads[0].consent_given,choice);assert.equal(room.fields.length,1);
});

test('cancelling confirmation submits nothing and allows a later different choice',async()=>{
    const room=page();let pending=room.submit({name:'consent_given',value:'0'});room.answer(false);await pending;
    assert.equal(room.payloads.length,0);assert.equal(room.fields.length,1);
    pending=room.submit({name:'consent_given',value:'1'});room.answer(true);await pending;
    assert.equal(room.payloads[0].consent_given,'1');
});

test('separate hidden decision fields and keyboard submits work without a named button',async()=>{
    const room=page();room.fields.push({name:'consent_given',value:'0'});
    const pending=room.submit(null);room.answer(true);await pending;
    assert.equal(room.payloads[0].consent_given,'0');
});

test('repeated clicks cannot replace an open confirmation decision or submit twice',async()=>{
    const room=page();const pending=room.submit({name:'consent_given',value:'0'});
    await room.submit({name:'consent_given',value:'1'});assert.equal(room.questions.length,1);
    room.answer(true);await pending;assert.equal(room.payloads.length,1);assert.equal(room.payloads[0].consent_given,'0');
});
