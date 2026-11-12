const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
// public/js is ESM by package type, so load the browser script in a sandbox with a CommonJS module object.
const sandbox={module:{exports:{}}};
vm.runInNewContext(fs.readFileSync('public/js/eval-list-filter.js','utf8'),sandbox);
const filterRows=(rows,opts)=>Array.from(sandbox.module.exports.filterRows(rows,opts));

const rows=[
    {risk:'low',time:100,alias:'Calm River',search:'calm river ana cruz anxiety'},
    {risk:'emergency',time:200,alias:'Bright Owl',search:'bright owl ben diaz self-harm'},
    {risk:'moderate',time:300,alias:'Amber Fox',search:'amber fox cara lim grief'},
    {risk:'high',time:400,alias:'Zesty Bee',search:'zesty bee ana cruz abuse'},
];

test('empty query returns all in original order',()=>{
    assert.deepEqual(filterRows(rows,{q:'',risk:'all',sort:'original'}),[0,1,2,3]);
    assert.deepEqual(filterRows(rows,{}),[0,1,2,3]);
});
test('search matches alias, helper and concern case-insensitively',()=>{
    assert.deepEqual(filterRows(rows,{q:'AMBER'}),[2]);
    assert.deepEqual(filterRows(rows,{q:'  Ana Cruz '}),[0,3]);
    assert.deepEqual(filterRows(rows,{q:'GRIEF'}),[2]);
    assert.deepEqual(filterRows(rows,{q:'nothing'}),[]);
});
test('risk high keeps high and emergency',()=>{
    assert.deepEqual(filterRows(rows,{risk:'high'}),[1,3]);
    assert.deepEqual(filterRows(rows,{risk:'moderate'}),[2]);
    assert.deepEqual(filterRows(rows,{risk:'low'}),[0]);
});
test('sort orders',()=>{
    assert.deepEqual(filterRows(rows,{sort:'newest'}),[3,2,1,0]);
    assert.deepEqual(filterRows(rows,{sort:'oldest'}),[0,1,2,3]);
    assert.deepEqual(filterRows(rows,{sort:'risk'}),[1,3,2,0]);
    assert.deepEqual(filterRows(rows,{sort:'alias'}),[2,1,0,3]);
});
test('risk sort breaks ties by newest',()=>{
    const r=[{risk:'high',time:1,alias:'a',search:''},{risk:'high',time:9,alias:'b',search:''}];
    assert.deepEqual(filterRows(r,{sort:'risk'}),[1,0]);
});
