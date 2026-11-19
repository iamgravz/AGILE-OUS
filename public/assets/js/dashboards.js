/* Dashboard charts, in the theme's colors. Each canvas carries its own data, and the same numbers are in a table for screen readers. */
(() => {
    'use strict';

    if (typeof Chart === 'undefined') {
        return;
    }

    const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const made = [];

    Chart.defaults.font.family = token('--font-body');

    /** The same color at the given strength, for the flat fill under the line. */
    function tint(hex, alpha) {
        const value = parseInt(hex.replace('#', ''), 16);

        return `rgb(${value >> 16} ${(value >> 8) & 255} ${value & 255} / ${alpha}%)`;
    }

    function options(data, area) {
        const text = token('--color-text-muted');
        const grid = token('--color-border');
        const axisTitle = (words) => ({ display: words !== '', text: words, color: text });
        const count = { beginAtZero: true, title: axisTitle(`Number of ${data.unit}`), ticks: { precision: 0, color: text }, grid: { color: grid } };
        const names = {
            title: axisTitle(area ? 'Month' : ''),
            ticks: {
                color: text,
                // Long names would be cut from the left on a phone. The table has them in full.
                callback(value) {
                    const label = this.getLabelForValue(value);

                    return window.innerWidth < 576 && label.length > 18 ? `${label.slice(0, 17)}…` : label;
                },
            },
            grid: { color: area ? 'transparent' : grid },
        };

        return {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: area ? 'x' : 'y',
            plugins: { legend: { display: false } },
            scales: area ? { x: names, y: count } : { x: count, y: names },
        };
    }

    function dataset(data, area) {
        const color = token('--color-link');

        return area
            ? { label: data.unit, data: data.values, borderColor: color, backgroundColor: tint(color, 14), borderWidth: 2, pointBackgroundColor: color, pointRadius: 3, fill: true, tension: 0.3 }
            : { label: data.unit, data: data.values, backgroundColor: color, borderRadius: 3 };
    }

    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const data = JSON.parse(canvas.dataset.chart);
        const area = canvas.dataset.chartType === 'area';
        const chart = new Chart(canvas, {
            type: area ? 'line' : 'bar',
            data: { labels: data.labels, datasets: [dataset(data, area)] },
            options: options(data, area),
        });

        made.push({ chart, data, area });

        // The range buttons only narrow the months already on the page.
        const card = canvas.closest('.card');

        card?.querySelectorAll('[data-chart-range]').forEach((button) => {
            button.addEventListener('click', () => {
                const months = Number(button.dataset.chartRange);

                chart.data.labels = data.labels.slice(-months);
                chart.data.datasets[0].data = data.values.slice(-months);
                chart.update();

                const heading = card.querySelector('[data-chart-heading]');
                const shown = chart.data.labels;
                const [fromMonth, fromYear] = shown[0].split(' ');
                const [toMonth, toYear] = shown[shown.length - 1].split(' ');

                heading.textContent = `${data.title}, ${fromYear === toYear ? `${fromMonth}–${toMonth} ${toYear}` : `${fromMonth} ${fromYear}–${toMonth} ${toYear}`}`;
                card.querySelectorAll('[data-chart-range]').forEach((other) => {
                    other.setAttribute('aria-pressed', String(other === button));
                });
            });
        });
    });

    document.addEventListener('agile:themechange', () => {
        made.forEach(({ chart, data, area }) => {
            const { labels } = chart.data;
            const values = chart.data.datasets[0].data;

            chart.options = options(data, area);
            chart.data.datasets[0] = { ...dataset(data, area), data: values };
            chart.data.labels = labels;
            chart.update();
        });
    });
})();
