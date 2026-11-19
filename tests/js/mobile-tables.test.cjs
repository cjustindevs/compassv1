const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
// public/js is ESM by package type, so load the browser script in a sandbox with a CommonJS module object.
const sandbox={module:{exports:{}}};
vm.runInNewContext(fs.readFileSync('public/js/mobile-tables.js','utf8'),sandbox);
const plan=(headers,spans)=>JSON.parse(JSON.stringify(sandbox.module.exports.planLabels(headers,spans)));

test('each cell gets its column title',()=>{
    assert.deepEqual(plan(['ID','Seeker','Concern','Status'],[1,1,1,1]),[{label:'ID'},{label:'Seeker'},{label:'Concern'},{label:'Status'}]);
});

test('a cell spanning the whole row (empty state) is shown full width',()=>{
    assert.deepEqual(plan(['Date','Duty hours','Status'],[3]),[{full:true}]);
});

test('an untitled column is treated as the actions column',()=>{
    assert.deepEqual(plan(['Case','Helper','Submitted',''],[1,1,1,1]),[{label:'Case'},{label:'Helper'},{label:'Submitted'},{actions:true}]);
});

test('colspans shift later cells to the right titles',()=>{
    assert.deepEqual(plan(['A','B','C','D'],[2,1,1]),[{label:'A'},{label:'C'},{label:'D'}]);
});
