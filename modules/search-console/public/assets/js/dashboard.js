/**
 * Dashboard — graphiques Chart.js
 *
 * Attend les variables globales injectées par le template PHP :
 *   window.dashboardData.dailyTrend  — [{data_date, clicks, impressions, ctr, position}, ...]
 *   window.dashboardData.devices     — [{device, clicks, impressions}, ...]
 *   window.dashboardData.countries   — [{country, clicks, impressions}, ...]
 */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.dashboardData || {};

    if (data.dailyTrend && data.dailyTrend.length > 0) {
        renderTrendChart(data.dailyTrend);
    }

    if (data.devices && data.devices.length > 0) {
        renderDeviceChart(data.devices);
    }

    if (data.countries && data.countries.length > 0) {
        renderCountryChart(data.countries);
    }
});

function renderTrendChart(trend) {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;

    const labels = trend.map(r => r.data_date);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Clicks',
                    data: trend.map(r => parseInt(r.clicks)),
                    borderColor: '#1a73e8',
                    backgroundColor: 'rgba(26,115,232,.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y',
                },
                {
                    label: 'Impressions',
                    data: trend.map(r => parseInt(r.impressions)),
                    borderColor: '#34a853',
                    backgroundColor: 'rgba(52,168,83,.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y1',
                },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 15,
                        font: { size: 11 }
                    }
                },
                y: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: 'Clicks' },
                    beginAtZero: true,
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: 'Impressions' },
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                },
            }
        }
    });
}

function renderDeviceChart(devices) {
    const ctx = document.getElementById('deviceChart');
    if (!ctx) return;

    const colors = ['#1a73e8', '#34a853', '#fbbc04', '#ea4335'];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: devices.map(d => d.device),
            datasets: [{
                data: devices.map(d => parseInt(d.clicks)),
                backgroundColor: colors.slice(0, devices.length),
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderCountryChart(countries) {
    const ctx = document.getElementById('countryChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: countries.map(c => c.country),
            datasets: [{
                label: 'Clicks',
                data: countries.map(c => parseInt(c.clicks)),
                backgroundColor: '#1a73e8',
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });
}
