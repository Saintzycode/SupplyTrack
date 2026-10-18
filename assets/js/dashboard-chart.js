/**
 * assets/js/dashboard-chart.js
 * Renders the "Stock by category" horizontal bar chart on the dashboard.
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
    var primary = pick('--primary', '#2563EB');
    var border = pick('--border', '#E2E8F0');
    var muted = pick('--muted', '#64748B');
    var text = pick('--text', '#1E293B');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map(function (r) { return r.category; }),
            datasets: [{
                label: 'Units on hand',
                data: rows.map(function (r) { return r.units; }),
                backgroundColor: primary,
                hoverBackgroundColor: '#1D4ED8',
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 18,
                maxBarThickness: 22
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.parsed.x.toLocaleString() + ' units';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: border },
                    ticks: { color: muted, precision: 0 }
                },
                y: {
                    grid: { display: false },
                    ticks: { color: text, font: { weight: '600' } }
                }
            }
        }
    });
})();
