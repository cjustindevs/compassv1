const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');

function page(){
    const select={value:'3',options:[],handlers:{},replaceChildren(...nodes){this.options=nodes;},append(node){this.options.push(node);},addEventListener:(name,fn)=>select.handlers[name]=fn};
    const search={value:'',addEventListener:(name,fn)=>search[name]=fn},submit={},note={},list={items:[],replaceChildren(){this.items=[];},append(node){this.items.push(node);}};
    const form={dataset:{helperOptions:'/emergency/helpers'},querySelector:s=>({'[name=helper_id]':select,'[data-helper-search]':search,'[type=submit]':submit,'[data-helper-reason]':note,'[data-unavailable-list]':list}[s])};
    let data={helpers:[{id:3,label:'Ready Helper | Ready | 2/2 remaining'},{id:4,label:'Other Helper | Ready'}],unavailable:[],reason:'Eligible Helpers'};
    const timers=[],document={hidden:false,activeElement:null,querySelectorAll:()=>[form],createElement:()=>({}),addEventListener(){}};
    vm.runInNewContext(fs.readFileSync('public/js/helper-candidates.js','utf8'),{document,window:{addEventListener(){}},navigator:{onLine:true},
        Option:function(label,value){this.label=label;this.value=value;},setInterval:fn=>timers.push(fn),fetch:async()=>({ok:true,json:async()=>data})});
    return{select,search,submit,note,list,document,form,data:value=>data=value,poll:()=>timers[0](),flush:()=>new Promise(resolve=>setImmediate(resolve))};
}
test('live emergency candidates preserve eligible selection and can be searched',async()=>{
    const p=page();await p.flush();assert.equal(p.select.value,'3');
    p.search.value='Other';p.search.input();assert.equal(p.select.options.length,2);assert.equal(p.select.value,'');
});
test('readiness changes remove a candidate and show unavailable reasons as text',async()=>{
    const p=page();await p.flush();p.data({helpers:[],unavailable:[{name:'<img onerror=x>',reason:'Off duty',readiness_passed:true}],reason:'No eligible Helper'});
    await p.poll();assert.equal(p.submit.disabled,true);assert.equal(p.select.value,'');
    assert.match(p.list.items[0].textContent,/Readiness passed/);assert.equal(p.list.items[0].innerHTML,undefined);
});
test('focused selection and submitting forms are not overwritten by polling',async()=>{
    const p=page();await p.flush();p.document.activeElement=p.select;p.data({helpers:[],unavailable:[],reason:'Changed'});await p.poll();assert.equal(p.select.value,'3');
    p.document.activeElement=null;p.form.dataset.submitting='1';await p.poll();assert.equal(p.select.value,'3');
    delete p.form.dataset.submitting;await p.poll();assert.equal(p.submit.disabled,true);
});
