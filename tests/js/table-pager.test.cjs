const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
// public/js is ESM by package type, so load the browser script in a sandbox with a CommonJS module object.
const sandbox={module:{exports:{}}};
vm.runInNewContext(fs.readFileSync('public/js/table-pager.js','utf8'),sandbox);
const select=(rows,opts)=>JSON.parse(JSON.stringify(sandbox.module.exports.selectRows(rows,opts)));

const row=(i,helper,status,date)=>({search:`r-${i} ${helper}`.toLowerCase(),attrs:{helper,status},date});
const rows=Array.from({length:23},(_,i)=>row(i+1,i%2?'Ana Cruz':'Ben Diaz',i%3?'completed':'evaluated',`2026-11-${String(1+i).padStart(2,'0')}`));

test('defaults to 10 rows on page 1',()=>{
    const r=select(rows,{});
    assert.equal(r.matched,23);assert.equal(r.pages,3);assert.equal(r.perPage,10);
    assert.deepEqual(r.visible,[0,1,2,3,4,5,6,7,8,9]);
});

test('only 10, 15, 25 and 50 are allowed page sizes',()=>{
    assert.equal(select(rows,{perPage:'25'}).perPage,25);
    assert.equal(select(rows,{perPage:'7'}).perPage,10);
    assert.equal(select(rows,{perPage:'50'}).pages,1);
});

test('page is clamped to the available pages',()=>{
    assert.deepEqual(select(rows,{page:3}).visible,[20,21,22]);
    assert.equal(select(rows,{page:9}).page,3);
    assert.equal(select(rows,{page:0}).page,1);
    assert.equal(select([],{page:2}).page,1);
    assert.equal(select([],{}).pages,1);
});

test('search, filters and date range combine; "all" and empty mean no filter',()=>{
    assert.equal(select(rows,{q:'  ANA  '}).matched,11);
    assert.equal(select(rows,{filters:{helper:'Ana Cruz',status:'all'}}).matched,11);
    assert.equal(select(rows,{filters:{helper:'Ana Cruz',status:'evaluated'}}).matched,4);
    assert.deepEqual(select(rows,{from:'2026-11-21',to:'2026-11-22'}).visible,[20,21]);
    assert.equal(select(rows,{from:'2026-12-01'}).matched,0);
});

test('rows without a date are left out only when a date range is set',()=>{
    const undated=[{search:'x',attrs:{},date:''}];
    assert.equal(select(undated,{}).matched,1);
    assert.equal(select(undated,{from:'2026-01-01'}).matched,0);
});
