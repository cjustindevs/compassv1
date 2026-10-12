export function chartOptions(spec, palette, text, grid, reducedMotion = false) {
    const bar = spec.type === 'bar';
    return {
        type: spec.type,
        data: {
            labels: spec.labels,
            datasets: spec.datasets.map((dataset, index) => ({
                ...dataset, borderColor: palette[index % palette.length],
                backgroundColor: bar ? palette[index % palette.length] : palette[index % palette.length] + '16',
                borderWidth: bar ? 0 : 2, borderRadius: bar ? 5 : 0,
                fill: !bar && spec.datasets.length === 1,
                cubicInterpolationMode: 'monotone', spanGaps: false,
                pointRadius: spec.labels.length > 35 ? 2 : 3, pointHoverRadius: 5,
                pointHitRadius: 12, maxBarThickness: 24,
            })),
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: reducedMotion ? false : { duration: 350 },
            indexAxis: bar ? 'y' : 'x', interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: spec.datasets.length > 1, position: 'bottom', labels: { color: text, usePointStyle: true, boxWidth: 7, padding: 16 } },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${Number(bar ? context.parsed.x : context.parsed.y).toLocaleString()}` } },
            },
            scales: {
                x: { beginAtZero: bar, grid: { display: bar, color: grid }, border: { display: false }, ticks: { color: text, precision: 0, maxRotation: 0, maxTicksLimit: 6, font: { size: 11 } } },
                y: { beginAtZero: !bar, grid: { display: !bar, color: grid }, border: { display: false }, ticks: { color: text, precision: 0, font: { size: 11 } } },
            },
        },
    };
}
