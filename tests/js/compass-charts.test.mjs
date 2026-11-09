import { test } from 'node:test';
import assert from 'node:assert/strict';
import { chartConfig, formatValue, semanticColor, PALETTE, STATUS } from '../../resources/js/compass-chart-options.mjs';

test('trends keep missing points as gaps and use monotone curves with the COMPASS palette', () => {
    const values = [1, 0, null, 9, 2, 3, 4, 5, 6, 7];
    const config = chartConfig({ type: 'line', labels: 'ABCDEFGHIJ'.split(''), datasets: [{ label: 'Completed', data: values }] });
    const dataset = config.data.datasets[0];
    assert.deepEqual(dataset.data, values);
    assert.equal(dataset.cubicInterpolationMode, 'monotone');
    assert.equal(dataset.spanGaps, false);
    assert.equal(dataset.borderColor, PALETTE.light[0]);
    assert.equal(config.options.scales.y.beginAtZero, true);
    assert.equal(config.options.plugins.legend.display, false);
    assert.equal(config.options.plugins.tooltip.callbacks.label({ dataset: { label: 'Completed' }, parsed: { y: 9 } }), 'Completed: 9');
});

test('horizontal category bars read values from the x axis and color semantic labels', () => {
    const config = chartConfig({ type: 'bar', labels: ['Low', 'Emergency'], datasets: [{ label: 'Cases', data: [3, 1] }] });
    assert.equal(config.options.indexAxis, 'y');
    assert.deepEqual(config.data.datasets[0].backgroundColor, [STATUS.good, STATUS.critical]);
    assert.equal(config.options.plugins.tooltip.callbacks.label({ dataset: { label: 'Cases' }, parsed: { x: 3, y: 0 } }), 'Cases: 3');
});

test('1-5 scores use a fixed scale and show samples in the tooltip', () => {
    const config = chartConfig({ type: 'line', unit: '', scale: [1, 5], labels: ['Oct', 'Nov'], samples: [2, null], datasets: [{ label: 'Competency', data: [3.8, null] }] });
    assert.equal(config.options.scales.y.min, 1);
    assert.equal(config.options.scales.y.max, 5);
    assert.equal(config.options.scales.y.beginAtZero, false);
    assert.equal(config.options.plugins.tooltip.callbacks.footer([{ dataIndex: 0 }]), '2 records measured');
    assert.equal(config.options.plugins.tooltip.callbacks.footer([{ dataIndex: 1 }]), '');
    const radar = chartConfig({ type: 'radar', scale: [1, 5], labels: ['Empathy'], datasets: [{ label: 'Cohort', data: [4] }] });
    assert.equal(radar.options.scales.r.min, 1);
    assert.equal(radar.options.scales.r.max, 5);
});

test('donuts and gauges draw a centered total or value', () => {
    const donut = chartConfig({ type: 'donut', labels: ['Completed', 'Pending'], datasets: [{ label: 'Cases', data: [6, 2] }] });
    assert.equal(donut.type, 'doughnut');
    assert.equal(donut.options.plugins.compassCenter.text, '8');
    assert.deepEqual(donut.data.datasets[0].backgroundColor, [STATUS.good, STATUS.warning]);
    const gauge = chartConfig({ type: 'gauge', unit: '%', labels: ['Rate'], datasets: [{ label: 'Acceptance rate', data: [72.5] }] });
    assert.equal(gauge.options.circumference, 180);
    assert.deepEqual(gauge.data.datasets[0].data, [72.5, 27.5]);
    assert.equal(gauge.options.plugins.compassCenter.text, '72.5%');
});

test('reduced motion disables animation, dark mode switches palette, multi-series keep legends', () => {
    const config = chartConfig({ type: 'line', labels: ['Mon', 'Tue'], datasets: [{ label: 'Received', data: [4, 3] }, { label: 'Completed', data: [2, 1] }] }, { dark: true }, true);
    assert.equal(config.options.animation, false);
    assert.equal(config.options.plugins.legend.display, true);
    assert.equal(config.data.datasets[1].borderColor, PALETTE.dark[1]);
    assert.equal(config.data.datasets[0].fill, false);
});

test('formatting shows a dash for missing values and keeps units', () => {
    assert.equal(formatValue(null), '—');
    assert.equal(formatValue(4.25, '', 1), '4.3');
    assert.equal(formatValue(12, ' min'), '12 min');
    assert.equal(formatValue(80, '%'), '80%');
    assert.equal(semanticColor('Something else'), null);
});

test('non-status labels beside status colors never take the brand green', async () => {
    const { semanticColors } = await import('../../resources/js/compass-chart-options.mjs');
    assert.deepEqual(semanticColors(['Active', 'Resolved', 'Peer team']), [PALETTE.light[1], STATUS.good, PALETTE.light[2]]);
    assert.deepEqual(semanticColors(['Academic', 'Family']), [PALETTE.light[0], PALETTE.light[1]]);
});

test('category bars stay one hue; only status labels are recolored', () => {
    const config = chartConfig({ type: 'bar', labels: ['Academic Stress', 'Unrecorded', 'Family'], datasets: [{ label: 'Cases', data: [9, 1, 2] }] });
    assert.deepEqual(config.data.datasets[0].backgroundColor, [PALETTE.light[1], STATUS.neutral, PALETTE.light[1]]);
    assert.equal(semanticColor('Awaiting acknowledgment'), STATUS.warning);
});

test('short series are drawn with straight segments', () => {
    const config = chartConfig({ type: 'line', labels: ['Aug', 'Sep', 'Oct'], datasets: [{ label: 'Received', data: [0, 1, 0] }] });
    assert.equal(config.data.datasets[0].cubicInterpolationMode, 'default');
    assert.equal(config.data.datasets[0].tension, 0);
});

test('a single measured period becomes a column and shared status colors are split', () => {
    const one = chartConfig({ type: 'line', scale: [1, 5], labels: ['Sep 2026'], datasets: [{ label: 'Score', data: [5] }] });
    assert.equal(one.type, 'bar');
    assert.equal(one.options.indexAxis, 'x');
    const donut = chartConfig({ type: 'donut', labels: ['Evaluated', 'Completed'], datasets: [{ label: 'Cases', data: [8, 2] }] });
    assert.notEqual(donut.data.datasets[0].backgroundColor[0], donut.data.datasets[0].backgroundColor[1]);
});
