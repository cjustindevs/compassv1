const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
// public/js is ESM by package type, so load the browser script in a sandbox with a CommonJS module object.
const sandbox={module:{exports:{}},Math};
vm.runInNewContext(fs.readFileSync('public/js/calm-music.js','utf8'),sandbox);
const {plan,CHORD_SECONDS}=sandbox.module.exports;
const events=(from,count,seed)=>JSON.parse(JSON.stringify(plan(from,count,seed)));

test('each cycle holds one four-note chord for CHORD_SECONDS',()=>{
    const pads=events(0,4,1).filter(e=>e.kind==='pad');
    assert.equal(pads.length,16);
    assert.deepEqual([...new Set(pads.map(p=>p.time))],[0,CHORD_SECONDS,2*CHORD_SECONDS,3*CHORD_SECONDS]);
    pads.forEach(p=>assert.ok(p.duration>=CHORD_SECONDS,'chords overlap slightly so there is no gap'));
});

test('the chord progression repeats every four cycles',()=>{
    const chordAt=(cycle)=>events(cycle,1,1).filter(e=>e.kind==='pad').map(e=>Math.round(e.freq)).join(',');
    assert.equal(chordAt(0),chordAt(4));
    assert.notEqual(chordAt(0),chordAt(1));
});

test('bell notes stay inside their cycle and on the C major pentatonic scale',()=>{
    const pentatonic=new Set([0,2,4,7,9]);
    for(let seed=1;seed<20;seed++){
        events(3,1,seed).filter(e=>e.kind==='bell').forEach(b=>{
            assert.ok(b.time>=3*CHORD_SECONDS && b.time<4*CHORD_SECONDS);
            const midi=Math.round(69+12*Math.log2(b.freq/440));
            assert.ok(pentatonic.has(((midi%12)+12)%12),`midi ${midi} not pentatonic`);
        });
    }
});

test('every note is soft-range and has a positive length',()=>{
    events(0,8,5).forEach(e=>{
        assert.ok(e.freq>80 && e.freq<1100,`freq ${e.freq}`);
        assert.ok(e.duration>0);
    });
});

test('the same seed gives the same music; bells are sparse',()=>{
    assert.deepEqual(events(0,4,42),events(0,4,42));
    const bells=events(0,40,9).filter(e=>e.kind==='bell').length;
    assert.ok(bells>=40 && bells<=160,`bells ${bells}`);
});
