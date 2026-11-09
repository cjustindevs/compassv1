// COMPASS chart theme: a pure Chart.js config builder (no DOM), so it is unit-testable.
//
// Spec shape (from components/analytics-chart.blade.php):
//   { type: 'line'|'area'|'bar'|'column'|'stacked'|'donut'|'gauge'|'radar',
//     labels: [...], datasets: [{ label, data: [number|null] }],
//     unit?: ' min' | '%' | '', scale?: [min, max], samples?: [n|null], max?: number }
//
// Categorical order was validated with the dataviz palette checker (light + dark, adjacent
// pairs): COMPASS green, blue, orange, violet, yellow, magenta. Status/risk colors are a
// separate fixed set and always ship with a text label, never color alone.

export const PALETTE = {
    light: ['#078749', '#2a78d6', '#eb6834', '#6250d6', '#eda100', '#e87ba4'],
    dark: ['#1f9a5c', '#3987e5', '#d95926', '#9085e9', '#c98500', '#d55181'],
};

export const STATUS = { good: '#0ca30c', warning: '#fab219', serious: '#ec835a', critical: '#d03b3b', neutral: '#94a3b8' };

// Entity -> semantic color, so a risk or outcome keeps its color whatever else is in the chart.
const SEMANTIC = [
    [/^(low|resolved|completed|evaluated|accepted|successful|matched|ready|yes|helpful|very helpful)\b/i, STATUS.good],
    [/^(moderate|medium|pending|waiting|in progress|scheduled|assigned|somewhat)/i, STATUS.warning],
    [/^(high|escalated|declined|returned|no.?show)/i, STATUS.serious],
    [/^(emergency|critical|crisis|imminent|failed|rejected)/i, STATUS.critical],
    [/^(cancelled|archived|unrecorded|other|not helpful|unknown)/i, STATUS.neutral],
];

export function semanticColor(label) {
    const hit = SEMANTIC.find(([pattern]) => pattern.test(String(label).trim()));
    return hit ? hit[1] : null;
}

// Status labels keep their semantic color; the remaining labels take categorical slots in order,
// skipping the brand green so an unrelated category never reads as a "good" status.
export function semanticColors(labels, palette = PALETTE.light) {
    const mixed = labels.some((label) => semanticColor(label));
    const pool = mixed ? palette.slice(1) : palette;
    let next = 0;
    return labels.map((label) => semanticColor(label) || pool[next++ % pool.length]);
}

export function formatValue(value, unit = '', decimals = null) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    const n = Number(value);
    const d = decimals ?? (Number.isInteger(n) ? 0 : (unit === '%' || Math.abs(n) < 10 ? 1 : 0));
    const text = n.toLocaleString('en-PH', { minimumFractionDigits: d, maximumFractionDigits: d });
    return unit === '%' ? `${text}%` : `${text}${unit}`;
}

function alpha(hex, a) {
    return hex + Math.round(a * 255).toString(16).padStart(2, '0');
}

// Draws the total (donut) or the value (gauge) in the middle of the ring.
export const centerLabel = {
    id: 'compassCenter',
    afterDraw(chart, _args, opts) {
        if (!opts || opts.text == null) return;
        const { ctx, chartArea: { left, right, top, bottom } } = chart;
        const meta = chart.getDatasetMeta(0);
        const arc = meta && meta.data && meta.data[0];
        const x = arc ? arc.x : (left + right) / 2;
        const y = arc ? arc.y : (top + bottom) / 2;
        const gauge = opts.gauge;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.fillStyle = opts.ink;
        ctx.font = `700 ${gauge ? 26 : 24}px Inter, system-ui, sans-serif`;
        ctx.textBaseline = gauge ? 'bottom' : 'middle';
        ctx.fillText(opts.text, x, gauge ? y - 6 : y - 8);
        if (opts.caption) {
            ctx.fillStyle = opts.muted;
            ctx.font = '500 11px Inter, system-ui, sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(opts.caption, x, gauge ? y - 2 : y + 8);
        }
        ctx.restore();
    },
};

export function chartConfig(spec, theme = {}, reducedMotion = false) {
    const mode = theme.dark ? 'dark' : 'light';
    const palette = theme.palette || PALETTE[mode];
    const ink = theme.ink || (theme.dark ? '#e7efe9' : '#163b2d');
    const muted = theme.muted || (theme.dark ? '#a9b8ae' : '#64748b');
    const grid = theme.grid || (theme.dark ? '#2b3a31' : '#e8eeeb');
    const surface = theme.surface || (theme.dark ? '#14201a' : '#ffffff');
    const type = spec.type || 'line';
    const unit = spec.unit || '';
    const scale = Array.isArray(spec.scale) ? spec.scale : null;
    const labels = spec.labels || [];
    const datasets = spec.datasets || [];
    const samples = spec.samples || null;
    const circular = type === 'donut' || type === 'gauge';
    const horizontal = type === 'bar';
    const categorical = horizontal || type === 'column' || type === 'stacked';
    const font = { family: 'Inter, system-ui, -apple-system, Segoe UI, sans-serif', size: 11 };
    const colorFor = (index) => palette[index % palette.length];

    const tooltip = {
        backgroundColor: theme.dark ? '#0d1611' : '#163b2d',
        titleColor: '#ffffff', bodyColor: '#e7efe9', footerColor: '#b9cfc2',
        padding: 10, cornerRadius: 8, boxPadding: 4, usePointStyle: true,
        titleFont: { ...font, weight: '600', size: 12 }, bodyFont: font, footerFont: { ...font, size: 10 },
        callbacks: {
            label(context) {
                const raw = circular ? context.parsed : (horizontal ? context.parsed.x : (type === 'radar' ? context.parsed.r : context.parsed.y));
                const name = circular ? context.label : context.dataset.label;
                if (type === 'gauge') return `${spec.datasets[0].label}: ${formatValue(spec.datasets[0].data[0], unit)}`;
                return `${name}: ${formatValue(raw, unit)}`;
            },
            footer(items) {
                if (!samples || !items.length) return '';
                const n = samples[items[0].dataIndex];
                return n == null ? '' : `${n} record${n === 1 ? '' : 's'} measured`;
            },
        },
    };

    const legend = {
        display: datasets.length > 1 || type === 'donut',
        position: 'bottom',
        labels: { color: muted, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 14, font },
    };

    const animation = reducedMotion ? false : { duration: 450, easing: 'easeOutQuart' };

    if (circular) {
        const values = datasets[0] ? datasets[0].data.map((v) => (v == null ? 0 : Number(v))) : [];
        if (type === 'gauge') {
            const max = spec.max ?? (unit === '%' ? 100 : (scale ? scale[1] : 100));
            const value = Math.max(0, Math.min(values[0] ?? 0, max));
            return {
                type: 'doughnut',
                data: { labels: [datasets[0].label, 'Remaining'], datasets: [{ data: [value, max - value], backgroundColor: [colorFor(0), grid], borderWidth: 0, borderRadius: 6 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, animation, circumference: 180, rotation: -90, cutout: '74%',
                    layout: { padding: { top: 6, bottom: 4 } },
                    plugins: { legend: { display: false }, tooltip: { ...tooltip, filter: (item) => item.dataIndex === 0 }, compassCenter: { text: formatValue(datasets[0].data[0], unit), caption: spec.caption || '', ink, muted, gauge: true } },
                },
                plugins: [centerLabel],
            };
        }
        const colors = semanticColors(labels, palette);
        const total = values.reduce((sum, v) => sum + v, 0);
        return {
            type: 'doughnut',
            data: { labels, datasets: [{ label: datasets[0]?.label || '', data: values, backgroundColor: colors, borderColor: surface, borderWidth: 2, hoverOffset: 6 }] },
            options: {
                responsive: true, maintainAspectRatio: false, animation, cutout: '68%',
                plugins: {
                    legend: { ...legend, position: 'right', labels: { ...legend.labels, generateLabels: (chart) => chart.data.labels.map((label, i) => ({ text: `${label}  ${formatValue(values[i])}`, fillStyle: colors[i], strokeStyle: colors[i], fontColor: ink, hidden: !chart.getDataVisibility(i), index: i, pointStyle: 'circle' })) } },
                    tooltip: { ...tooltip, callbacks: { ...tooltip.callbacks, label: (c) => `${c.label}: ${formatValue(c.parsed, unit)} (${total ? Math.round(c.parsed / total * 100) : 0}%)` } },
                    compassCenter: { text: formatValue(total), caption: spec.caption || 'total', ink, muted },
                },
            },
            plugins: [centerLabel],
        };
    }

    if (type === 'radar') {
        return {
            type: 'radar',
            data: {
                labels,
                datasets: datasets.map((dataset, index) => ({
                    ...dataset, borderColor: colorFor(index), backgroundColor: alpha(colorFor(index), 0.14),
                    borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: colorFor(index), spanGaps: false,
                })),
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation,
                plugins: { legend, tooltip },
                scales: {
                    r: {
                        min: scale ? scale[0] : 0, max: scale ? scale[1] : undefined, beginAtZero: !scale,
                        ticks: { stepSize: 1, color: muted, backdropColor: 'transparent', font, showLabelBackdrop: false },
                        grid: { color: grid }, angleLines: { color: grid }, pointLabels: { color: ink, font: { ...font, size: 11, weight: '500' } },
                    },
                },
            },
        };
    }

    const single = datasets.length === 1;
    const lineLike = type === 'line' || type === 'area';
    const config = {
        type: lineLike ? 'line' : 'bar',
        data: {
            labels,
            datasets: datasets.map((dataset, index) => {
                const color = dataset.color || colorFor(index);
                const perBar = categorical && single && labels.some((label) => semanticColor(label));
                const barColors = Array.isArray(dataset.colors) ? dataset.colors : (perBar ? semanticColors(labels, palette) : color);
                return lineLike
                    ? {
                        ...dataset, borderColor: color, backgroundColor: alpha(color, single ? 0.12 : 0.06), borderWidth: 2,
                        fill: single || type === 'area' ? 'origin' : false, cubicInterpolationMode: 'monotone', spanGaps: false,
                        pointRadius: labels.length > 31 ? 0 : 3, pointHoverRadius: 5, pointHitRadius: 14,
                        pointBackgroundColor: surface, pointBorderColor: color, pointBorderWidth: 2,
                    }
                    : {
                        ...dataset, backgroundColor: barColors, borderColor: surface, borderWidth: type === 'stacked' ? { top: 2 } : 0,
                        borderRadius: type === 'stacked' ? 0 : 4, borderSkipped: 'start', maxBarThickness: horizontal ? 22 : 36, categoryPercentage: 0.72, barPercentage: 0.9,
                    };
            }),
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation,
            indexAxis: horizontal ? 'y' : 'x',
            interaction: { mode: 'index', intersect: false },
            plugins: { legend, tooltip },
            scales: {},
        },
    };
    const valueAxis = {
        beginAtZero: true, stacked: type === 'stacked',
        min: scale ? scale[0] : undefined, max: scale ? scale[1] : (unit === '%' ? 100 : undefined),
        grid: { color: grid, drawTicks: false }, border: { display: false },
        ticks: { color: muted, padding: 8, font, precision: scale || unit ? undefined : 0, stepSize: scale ? 1 : undefined, maxTicksLimit: 6, callback: (v) => formatValue(v, unit === '%' ? '%' : '') },
    };
    if (scale && scale[0] > 0) valueAxis.beginAtZero = false;
    const categoryAxis = {
        stacked: type === 'stacked', grid: { display: false }, border: { color: grid },
        ticks: { color: muted, font, maxRotation: 0, autoSkip: true, maxTicksLimit: horizontal ? undefined : 8, callback(value) { const label = this.getLabelForValue(value); return typeof label === 'string' && label.length > 22 ? label.slice(0, 21) + '…' : label; } },
    };
    config.options.scales = horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis };
    return config;
}
