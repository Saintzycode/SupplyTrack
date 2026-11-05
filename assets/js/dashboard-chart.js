/**
 * assets/js/dashboard-chart.js
 * Renders the "Stock by category" vertical bar chart on the dashboard.
 * Data is server-rendered into #stockByCategoryData as JSON.
 */
(function () {
    var dataEl = document.getElementById('stockByCategoryData');
    var canvas = document.getElementById('stockByCategory');
    if (!dataEl || !canvas || typeof Chart === 'undefined') return;

    var rows;
    try { rows = JSON.parse(dataEl.textContent); } catch (e) { return; }
    if (!rows || !rows.length) return;

    var css = getComputedStyle(document.documentElement);
    var pick = function (name, fallback) {
        var v = (css.getPropertyValue(name) || '').trim();
        return v || fallback;
    };
    // One flat solid colour per category (matches --chart-1..8 in style.css, cycles if >8)
    var palette = [1, 2, 3, 4, 5, 6, 7, 8].map(function (n) {
        return pick('--chart-' + n, ['#059669', '#2563EB', '#D97706', '#0E7490', '#7C3AED', '#DB2777', '#475569', '#65A30D'][n - 1]);
    });
    var border = pick('--border', '#E6E8EA');
    var muted = pick('--muted', '#475569');
    var text = pick('--text', '#0F172A');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map(function (r) { return r.category; }),
            datasets: [{
                label: 'Units on hand',
                data: rows.map(function (r) { return r.units; }),
                backgroundColor: rows.map(function (_, i) { return palette[i % palette.length]; }),
                hoverBackgroundColor: rows.map(function (_, i) { return palette[i % palette.length]; }),
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 22,
                maxBarThickness: 38
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (ctx) {
                            return rows[ctx[0].dataIndex] ? rows[ctx[0].dataIndex].category : '';
                        },
                        label: function (ctx) {
                            return ' ' + ctx.parsed.y.toLocaleString() + ' units';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: border },
                    ticks: { color: muted, precision: 0 }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: text,
                        font: { weight: '600' },
                        maxRotation: 45,
                        autoSkip: false
                    }
                }
            }
        }
    });
})();
