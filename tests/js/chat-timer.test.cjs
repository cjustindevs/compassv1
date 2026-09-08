const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function room() {
    const timer = { textContent: '' };
    let time = 0;
    const context = vm.createContext({
        document: { addEventListener() {}, getElementById: () => timer },
        performance: { now: () => time },
        clearInterval() {}, setTimeout() {}, window: {},
    });
    vm.runInContext(fs.readFileSync('resources/js/chat.js', 'utf8'), context);
    const app = Object.create(vm.runInContext('ChatApp.prototype', context));
    Object.assign(app, { ended: false, deadlineReached: false, messageInput: {}, sendButton: {}, remainingSeconds: 1, statusReceivedAt: 0 });
    return { app, timer, advance: milliseconds => time += milliseconds };
}

test('deadline locks composer and checks server; send completion cannot unlock it', () => {
    const { app, timer, advance } = room();
    let checks = 0;
    app.checkSessionStatus = () => checks++;
    app.updateSessionTimer();
    assert.equal(timer.textContent, '00:01 remaining');
    advance(1000);
    app.updateSessionTimer();
    assert.equal(checks, 1);
    assert.equal(timer.textContent, '00:00 remaining');
    app.setInputDisabled(false);
    assert.equal(app.messageInput.disabled, true);
    assert.equal(app.sendButton.disabled, true);
});

test('polling and websocket end notices trigger only one overlay and preserve lock', () => {
    const { app } = room();
    let overlays = 0;
    app.showTyping = () => {};
    app.showEndedOverlay = () => overlays++;
    app.handleSessionEnded({ message: '90-minute limit reached' });
    app.handleSessionEnded({ message: 'duplicate websocket notice' });
    app.setInputDisabled(false);
    assert.equal(overlays, 1);
    assert.equal(app.messageInput.disabled, true);
});
