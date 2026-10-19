const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');

function page(form=null){
    const events={},button={focus(){this.focused=true;}};
    const dialogs=[0,1].map(index=>({open:false,dirty:false,handlers:{},classList:{contains:()=>true},
        querySelector(s){return s.startsWith('form[')?(this.dirty?{}:null):button;},querySelectorAll:selector=>index===0&&selector==='form'&&form?[form]:[],
        addEventListener(name,fn){this.handlers[name]=fn;},showModal(){this.open=true;},close(){this.open=false;this.handlers.close?.();}}));
    let allow=true;
    const window={confirm:()=>allow,addEventListener(){}};
    const document={activeElement:button,querySelectorAll:s=>s==='dialog'?dialogs:dialogs.filter(d=>d.open),addEventListener:(name,fn)=>events[name]=fn};
    vm.runInNewContext(fs.readFileSync('public/js/workflow-dialogs.js','utf8'),{window,document,location:{reload(){}}});
    return {window,dialogs,button,allow:value=>allow=value};
}

test('opening another workflow closes the previous dialog instead of nesting it',()=>{
    const p=page();p.window.CompassDialogs.open(p.dialogs[0],p.button);
    p.window.CompassDialogs.open(p.dialogs[1],p.button);
    assert.equal(p.dialogs[0].open,false);assert.equal(p.dialogs[1].open,true);
});
test('a declined discard confirmation preserves the open form and blocks another dialog',()=>{
    const p=page();p.window.CompassDialogs.open(p.dialogs[0]);p.dialogs[0].dirty=true;p.allow(false);
    assert.equal(p.window.CompassDialogs.open(p.dialogs[1]),false);
    assert.equal(p.window.CompassDialogs.close(p.dialogs[0]),false);
    assert.equal(p.dialogs[0].open,true);assert.equal(p.dialogs[1].open,false);
});
test('Escape respects unsaved entries and closing restores trigger focus',()=>{
    const p=page();p.window.CompassDialogs.open(p.dialogs[0],p.button);p.dialogs[0].dirty=true;p.allow(false);
    let prevented=false;p.dialogs[0].handlers.cancel({preventDefault(){prevented=true;}});assert.equal(prevented,true);
    p.window.CompassDialogs.close(p.dialogs[0],true);assert.equal(p.button.focused,true);
});

test('a cancelled confirmation leaves the form editable and accepted submit cannot run twice',()=>{
    const button={textContent:'Submit'},form={dataset:{},reportValidity:()=>true,querySelector:()=>button,addEventListener(name,fn){this[name]=fn;}};
    page(form);
    form.submit({defaultPrevented:true});assert.equal(form.dataset.submitting,undefined);assert.equal(button.disabled,undefined);
    form.submit({defaultPrevented:false,submitter:button});assert.equal(form.dataset.submitting,'1');assert.equal(button.disabled,true);
    let stopped=false;form.submit({defaultPrevented:false,preventDefault(){stopped=true;}});assert.equal(stopped,true);
});
