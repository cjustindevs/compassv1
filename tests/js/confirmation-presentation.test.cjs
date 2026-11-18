const {test}=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const vm=require('node:vm');

const source=readFileSync('resources/js/toast.js','utf8');
const start=source.indexOf('function confirmPresentation(');
const end=source.indexOf('function confirmAction(',start);
const sandbox={};
vm.runInNewContext(source.slice(start,end)+'\nthis.confirmPresentation=confirmPresentation;',sandbox);
const present=options=>JSON.parse(JSON.stringify(sandbox.confirmPresentation(options)));

test('log out and end session are red with a matching icon',()=>{
    assert.deepEqual(present({confirmText:'Log out',confirmClass:'bg-red-600'}),{tone:'danger',icon:'logout'});
    assert.deepEqual(present({confirmText:'End session'}),{tone:'danger',icon:'stop-circle'});
    assert.deepEqual(present({confirmText:'Delete account'}),{tone:'danger',icon:'trash'});
    assert.deepEqual(present({confirmText:'Archive'}),{tone:'danger',icon:'archive'});
    assert.deepEqual(present({confirmText:'Decline referral'}),{tone:'danger',icon:'ban'});
});

test('positive actions are green',()=>{
    assert.deepEqual(present({confirmText:'Agree to referral',confirmClass:'bg-green-700 hover:bg-green-800'}),{tone:'primary',icon:'check-circle'});
    assert.deepEqual(present({confirmText:'Restore'}),{tone:'primary',icon:'refresh'});
    assert.deepEqual(present({confirmText:'Assign and notify'}),{tone:'primary',icon:'check-circle'});
});

test('an explicit tone or icon wins; unknown text falls back to a warning look',()=>{
    assert.deepEqual(present({confirmText:'Continue',tone:'warning'}),{tone:'warning',icon:'alert-circle'});
    assert.deepEqual(present({confirmText:'Raise and notify',iconName:'bell'}),{tone:'danger',icon:'bell'});
    assert.deepEqual(present({}),{tone:'danger',icon:'warning'});
});
