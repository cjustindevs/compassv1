import { test } from 'node:test';
import assert from 'node:assert/strict';
import { chartOptions } from '../../resources/js/dashboard-chart-options.mjs';

test('time trends preserve counts and missing points while curves remain monotone', () => {
    const values = [1, 0, null, 9];
    const options = chartOptions({ type: 'line', labels: ['A','B','C','D'], datasets: [{ label: 'Completed', data: values }] }, ['#04a052'], '#64748b', '#e5e7eb');
    assert.deepEqual(options.data.datasets[0].data, values);
    assert.equal(options.data.datasets[0].cubicInterpolationMode, 'monotone');
    assert.equal(options.data.datasets[0].spanGaps, false);
    assert.equal(options.options.scales.y.beginAtZero, true);
    assert.equal(options.options.plugins.tooltip.callbacks.label({ dataset: { label: 'Completed' }, parsed: { y: 9 } }), 'Completed: 9');
});

test('category charts use horizontal bars and tooltip values from their numeric axis', () => {
    const options = chartOptions({ type: 'bar', labels: ['Professional'], datasets: [{ label: 'Accounts', data: [12] }] }, ['#04a052'], '#eee', '#333');
    assert.equal(options.options.indexAxis, 'y');
    assert.equal(options.options.scales.x.beginAtZero, true);
    assert.equal(options.options.plugins.tooltip.callbacks.label({ dataset: { label: 'Accounts' }, parsed: { x: 12, y: 0 } }), 'Accounts: 12');
    assert.equal(options.options.scales.x.ticks.color, '#eee');
});

test('reduced motion disables animation and multiple series keep their legends', () => {
    const options = chartOptions({ type: 'line', labels: ['Day'], datasets: [{ label: 'Received', data: [4] }, { label: 'Completed', data: [2] }] }, ['#04a052', '#3b82f6'], '#64748b', '#e5e7eb', true);
    assert.equal(options.options.animation, false);
    assert.equal(options.options.plugins.legend.display, true);
    assert.equal(options.data.datasets[1].borderColor, '#3b82f6');
    assert.equal(options.data.datasets[0].fill, false);
});
