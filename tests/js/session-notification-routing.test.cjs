const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('queue updates announce safely without inserting a competing queue card', () => {
    const handlers = {}, timers = [];
    const channel = {listen(name, fn) {handlers[name] = fn; return channel;}};
    const notice = {classList: {remove() {}}, style: {}, setAttribute() {}, textContent: ''};
    Object.defineProperty(notice, 'innerHTML', {set() {throw Error('A queue update must not insert a second card');}});
    const context = {
        document: {querySelector: () => ({content: '7'}), getElementById: id => id === 'matchingLiveState' ? notice : null,
            addEventListener: (name, fn) => {if (name === 'DOMContentLoaded') fn();}},
        window: {location: {pathname: '/request/matching'}, CompassNoticesEnabled: true, Echo: {private: () => channel}},
        setInterval() {}, setTimeout: fn => timers.push(fn),
    };
    vm.runInNewContext(fs.readFileSync('resources/js/seeker-notifications.js', 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
    handlers.NewHelperAssigned({helper_name: '<unsafe name>'});
    assert.equal(notice.textContent, '<unsafe name> was invited. Waiting for acceptance...');
    handlers.CaseDeclined({});
    assert.match(notice.textContent, /Finding another eligible Helper/);
    assert.equal(timers.length, 2);
});

for (const role of ['seeker', 'helper']) {
    for (const roomId of ['42', null]) {
        test(`${role} workspace completion notices cannot redirect a live room or evaluation page (${roomId})`, () => {
            const handlers = {}, timers = [];
            const channel = {listen(name, fn) {handlers[name] = fn; return channel;}};
            const location = {pathname: role === 'seeker' ? '/session/evaluation' : '/helper/session/42/chat', href: 'unchanged'};
            const context = {
                document: {querySelector: () => ({content: '7'}), getElementById: id => id === 'sessionId' && roomId ? {value: roomId} : null,
                    addEventListener: (name, fn) => {if (name === 'DOMContentLoaded') fn();}},
                window: {location, CompassNoticesEnabled: true, Echo: {private: () => channel}},
                setInterval() {}, setTimeout: fn => timers.push(fn),
            };
            vm.runInNewContext(fs.readFileSync(`resources/js/${role}-notifications.js`, 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
            handlers.SessionEnded({session_id: 11, seeker_redirect: '/session/evaluation?session_id=11', helper_redirect: '/helper/session/11/notes'});
            assert.equal(timers.length, 0);
            assert.equal(location.href, 'unchanged');
        });
    }
}
