// On récupère les séries injectées par PHP
const series = (typeof window !== 'undefined' && window.cruxSeries) ? window.cruxSeries : {
    ttfb: [], lcp: [], cls: [], inp: []
};
const seriesP75 = (typeof window !== 'undefined' && window.cruxSeriesP75) ? window.cruxSeriesP75 : {
    ttfb: [], lcp: [], cls: [], inp: []
};

// 1) Construire la liste globale de dates (union de toutes les séries)
function computeGlobalLabels(series, seriesP75) {
    const set = new Set();
    ['ttfb','lcp','cls','inp'].forEach(code => {
        (series[code] || []).forEach(s => (s.dates || []).forEach(d => set.add(d)));
        (seriesP75[code] || []).forEach(s => (s.dates || []).forEach(d => set.add(d)));
    });
    return Array.from(set).sort();
}

// 2) Aligner les 3 catégories (good/ni/poor) sur l'axe global
function alignSeriesToGlobal(series, globalLabels) {
    const aligned = {};
    ['ttfb','lcp','cls','inp'].forEach(code => {
        aligned[code] = (series[code] || []).map(s => {
            const mapGood = {}, mapNI = {}, mapPoor = {};
            (s.dates || []).forEach((d, i) => {
                mapGood[d] = s.good[i];
                mapNI[d]   = s.ni[i];
                mapPoor[d] = s.poor[i];
            });
            return {
                label: s.label,
                good: globalLabels.map(d => Object.prototype.hasOwnProperty.call(mapGood, d) ? mapGood[d] : null),
                ni:   globalLabels.map(d => Object.prototype.hasOwnProperty.call(mapNI, d) ? mapNI[d] : null),
                poor: globalLabels.map(d => Object.prototype.hasOwnProperty.call(mapPoor, d) ? mapPoor[d] : null),
            };
        });
    });
    return aligned;
}

// 3) Aligner P75
function alignP75ToGlobal(seriesP75, globalLabels) {
    const aligned = {};
    ['ttfb','lcp','cls','inp'].forEach(code => {
        aligned[code] = (seriesP75[code] || []).map(s => {
            const map = {};
            (s.dates || []).forEach((d, i) => { map[d] = s.values[i]; });
            return {
                label: s.label,
                data: globalLabels.map(d => Object.prototype.hasOwnProperty.call(map, d) ? map[d] : null)
            };
        });
    });
    return aligned;
}


const globalLabels = computeGlobalLabels(series, seriesP75);
const alignedData  = alignSeriesToGlobal(series, globalLabels);
const alignedP75   = alignP75ToGlobal(seriesP75, globalLabels);

let currentStartIndex = 0;
let currentEndIndex   = globalLabels.length > 0 ? globalLabels.length - 1 : 0;

const charts = { ttfb: null, lcp: null, cls: null, inp: null };

// Navigation Types chart (déclaré ici pour éviter le temporal dead zone)
let navTypesChart = null;
let currentNavUrlIdx = 0;

// Visibilité des couches — P75 par défaut, Distribution uniquement Good
const layerVisibility = {
    ttfb: { good: false, ni: false, poor: false, p75: true },
    lcp:  { good: false, ni: false, poor: false, p75: true },
    cls:  { good: false, ni: false, poor: false, p75: true },
    inp:  { good: false, ni: false, poor: false, p75: true },
};

// Couleurs des 3 catégories CWV
const categoryColors = {
    good: '#22c55e',
    ni:   '#f97316',
    poor: '#ef4444',
};


const categoryLabels = {
    good: 'Bon',
    ni:   'À améliorer',
    poor: 'Mauvais',
};

// Styles de ligne pour distinguer les catégories (même couleur par URL)
const catDashPatterns = {
    good: [],
    ni:   [6, 3],
    poor: [2, 2],
};

// Seuils CWV par métrique (injectés depuis PHP, fallback hardcodé)
const cwvThresholds = (typeof window !== 'undefined' && window.cruxThresholds) ? window.cruxThresholds : {
    ttfb: { good: 800,  poor: 1800 },
    lcp:  { good: 2500, poor: 4000 },
    cls:  { good: 0.1,  poor: 0.25 },
    inp:  { good: 200,  poor: 500  },
};

// Config P75 par métrique
const p75Config = {
    ttfb: { yTitle: 'P75 (ms)',  suffix: ' ms', decimals: 0 },
    lcp:  { yTitle: 'P75 (ms)',  suffix: ' ms', decimals: 0 },
    cls:  { yTitle: 'P75',       suffix: '',     decimals: 3 },
    inp:  { yTitle: 'P75 (ms)',  suffix: ' ms', decimals: 0 },
};

// Couleurs distinctes pour les courbes P75
const p75Colors = [
    '#a855f7', '#14b8a6', '#f43f5e',
    '#eab308', '#6366f1', '#22d3ee', '#ec4899'
];

function buildDatasets(metricCode) {
    const catSeries = alignedData[metricCode] || [];
    const p75Series = alignedP75[metricCode] || [];
    const vis = layerVisibility[metricCode];
    const datasets = [];

    // Datasets des 3 catégories
    // 1 URL  → couleur par catégorie (vert/orange/rouge), trait plein
    // N URLs → couleur par URL, style de ligne par catégorie
    const isSingleUrl = catSeries.length === 1;
    catSeries.forEach((s, urlIdx) => {
        ['good', 'ni', 'poor'].forEach(cat => {
            if (!vis[cat]) return;
            const catLbl = categoryLabels[cat];
            const color  = isSingleUrl
                ? categoryColors[cat]
                : p75Colors[urlIdx % p75Colors.length];
            const dash   = isSingleUrl ? [] : catDashPatterns[cat];
            datasets.push({
                label:           s.label + ' – ' + catLbl,
                data:            s[cat].slice(currentStartIndex, currentEndIndex + 1),
                yAxisID:         'yPct',
                fill:            false,
                tension:         0.25,
                borderWidth:     2,
                borderDash:      dash,
                borderColor:     color,
                backgroundColor: color,
                _layer:          cat,
                _seriesIdx:      urlIdx,
            });
        });
    });

    // Datasets P75 (axe Y droit)
    if (vis.p75) {
        p75Series.forEach((s, i) => {
            datasets.push({
                label: s.label,
                data: s.data.slice(currentStartIndex, currentEndIndex + 1),
                yAxisID: 'yP75',
                fill: false,
                tension: 0.25,
                borderWidth: 2,
                borderDash: [6, 3],
                borderColor: p75Colors[i % p75Colors.length],
                backgroundColor: p75Colors[i % p75Colors.length],
                _layer: 'p75',
                _seriesIdx: i,
            });
        });

        // Lignes de seuil CWV
        const thresholds = cwvThresholds[metricCode];
        if (thresholds) {
            const len = currentEndIndex - currentStartIndex + 1;
            datasets.push({
                label: 'Seuil Bon',
                data: Array(len).fill(thresholds.good),
                yAxisID: 'yP75',
                borderColor: categoryColors.good,
                backgroundColor: categoryColors.good,
                borderWidth: 1.5,
                borderDash: [5, 5],
                pointRadius: 0,
                fill: false,
                tension: 0,
                _layer: 'threshold',
            });
            datasets.push({
                label: 'Seuil Mauvais',
                data: Array(len).fill(thresholds.poor),
                yAxisID: 'yP75',
                borderColor: categoryColors.poor,
                backgroundColor: categoryColors.poor,
                borderWidth: 1.5,
                borderDash: [5, 5],
                pointRadius: 0,
                fill: false,
                tension: 0,
                _layer: 'threshold',
            });
        }
    }

    return datasets;
}

// ─── Tooltip externe Chart.js pour le mode P75 ─────────────────────────────

// Calcule la plage de collecte glissante (28 j) à partir de la date de fin
function collectionPeriodRange(endDateStr) {
    const end   = new Date(endDateStr);
    const start = new Date(end);
    start.setDate(start.getDate() - 27);
    const fmt = d => d.toISOString().slice(0, 10);
    return fmt(start) + ' → ' + fmt(end);
}

function getOrCreateChartTooltip() {
    let el = document.getElementById('cwv-chart-tooltip');
    if (!el) {
        el = document.createElement('div');
        el.id = 'cwv-chart-tooltip';
        el.className = 'cwv-chart-tooltip';
        document.body.appendChild(el);
    }
    return el;
}

function cwvTooltipHandler(context) {
    const { chart, tooltip } = context;
    const el = getOrCreateChartTooltip();

    if (tooltip.opacity === 0) {
        el.style.opacity = '0';
        return;
    }

    const metricCode = chart._metricCode;
    const info = cwvMetricInfo[metricCode];
    if (!info || !tooltip.dataPoints || !tooltip.dataPoints.length) {
        el.style.opacity = '0';
        return;
    }

    const dataIndex  = tooltip.dataPoints[0].dataIndex;
    const actualIdx  = currentStartIndex + dataIndex;
    const date       = globalLabels[actualIdx] || '';
    const catData    = alignedData[metricCode] || [];
    const p75Data    = alignedP75[metricCode]  || [];
    const cfg        = p75Config[metricCode];

    const goodW = Math.round(info.good / info.max * 100);
    const niW   = Math.round((info.poor - info.good) / info.max * 100);
    const poorW = 100 - goodW - niW;
    const fmt   = v => (v !== null && v !== undefined) ? String(Number(v).toFixed(1)).replace('.', ',') : '–';

    const urlParts = [];
    p75Data.forEach((s, i) => {
        const p75Val = s.data[actualIdx];
        if (p75Val === null || p75Val === undefined) return;

        const cat    = catData[i];
        const good   = cat?.good[actualIdx];
        const ni     = cat?.ni[actualIdx];
        const poor   = cat?.poor[actualIdx];
        const p75Pos = Math.min(Math.round(p75Val / info.max * 100), 97);
        const color  = p75Colors[i % p75Colors.length];

        const parts = [
            '<div class="cwv-tt-block">',
            '  <div class="cwv-tt-url-row">',
            `    <span class="cwv-tt-dot" style="background:${color}"></span>`,
            `    <span class="cwv-tt-label">${s.label}</span>`,
            `    <span class="cwv-tt-val">${formatP75(metricCode, p75Val)}</span>`,
            '  </div>',
        ];

        if (good !== null && good !== undefined) {
            const histoH    = 58;
            const bins      = buildHistogramBins(metricCode, good, ni, poor, i, p75Val);
            const maxHVal   = Math.max(...bins.map(b => b.heightValue));
            const catColors = { good: 'rgba(34,197,94,0.85)', ni: 'rgba(249,115,22,0.85)', poor: 'rgba(239,68,68,0.85)' };
            const barsHTML  = bins.map(b => {
                const w = (b.actualWidth / info.max * 100).toFixed(3);
                const h = Math.max(1, Math.round(b.heightValue / maxHVal * histoH));
                return `<div class="cwv-tt-hbin" style="width:${w}%;height:${h}px;background:${catColors[b.cat]}"></div>`;
            }).join('');
            parts.push(
                `<div class="cwv-tt-histo" style="height:${histoH}px">`,
                barsHTML,
                `<div class="cwv-tt-p75-line" style="left:${p75Pos}%"></div>`,
                '</div>',
                '<div class="cwv-tt-axis">',
                info.labels.map(l => `<span>${l}</span>`).join(''),
                '</div>',
                '<div class="cwv-tt-pcts">',
                `<span style="color:#22c55e">${fmt(good)} %</span>`,
                `<span style="color:#f97316">${fmt(ni)} %</span>`,
                `<span style="color:#ef4444">${fmt(poor)} %</span>`,
                '</div>'
            );
        }

        parts.push('</div>');
        urlParts.push(parts.join(''));
    });
    const urlBlocks = urlParts.join('');

    const periodRange = date ? collectionPeriodRange(date) : '';

    el.innerHTML = `
    <div class="cwv-tt-header">
      <span class="cwv-tt-metric-name">${info.name}</span>
      <span class="cwv-tt-date">${periodRange}</span>
    </div>
    ${urlBlocks}`;

    // Positionnement : reste dans le viewport
    const rect  = chart.canvas.getBoundingClientRect();
    const ttW   = 260;
    const ttH   = el.offsetHeight || 280;
    let left    = rect.left + tooltip.caretX + 14;
    if (left + ttW > window.innerWidth - 8) left = rect.left + tooltip.caretX - ttW - 14;
    if (left < 8) left = 8;
    let top     = rect.top + tooltip.caretY - ttH / 2;
    if (top + ttH > window.innerHeight - 8) top = window.innerHeight - ttH - 8;
    if (top < 8) top = 8;
    el.style.left    = left + 'px';
    el.style.top     = top + 'px';
    el.style.opacity = '1';
}

function buildChart(canvasId, metricCode) {
    const catSeries = alignedData[metricCode] || [];
    const p75Series = alignedP75[metricCode] || [];
    if ((!catSeries.length && !p75Series.length) || !globalLabels.length) return;

    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const vis = layerVisibility[metricCode];
    const cfg = p75Config[metricCode];
    const labels = globalLabels.slice(currentStartIndex, currentEndIndex + 1);
    const datasets = buildDatasets(metricCode);

    const hasCatVisible = vis.good || vis.ni || vis.poor;

    // Calcul de la plage P75 (données + seuils CWV) pour synchroniser les deux axes
    let rawMin = Infinity, rawMax = -Infinity;
    p75Series.forEach(s => {
        s.data.slice(currentStartIndex, currentEndIndex + 1).forEach(v => {
            if (v != null && !isNaN(v)) {
                rawMin = Math.min(rawMin, v);
                rawMax = Math.max(rawMax, v);
            }
        });
    });
    // Inclure les seuils dans la plage pour qu'ils soient toujours visibles
    const thresholdsForRange = cwvThresholds[metricCode];
    if (thresholdsForRange) {
        rawMin = rawMin !== Infinity ? Math.min(rawMin, thresholdsForRange.good) : thresholdsForRange.good;
        rawMax = rawMax !== -Infinity ? Math.max(rawMax, thresholdsForRange.poor) : thresholdsForRange.poor;
    }
    let p75Min, p75Max;
    if (rawMin !== Infinity) {
        const range = rawMax - rawMin || rawMax * 0.1 || 10;
        const pad   = range * 0.1;
        p75Min = Math.max(0, Math.floor(rawMin - pad));
        p75Max = Math.ceil(rawMax + pad);
    }

    const p75Ticks = {
        callback: val => cfg.decimals > 0 ? Number(val).toFixed(cfg.decimals) : Math.round(val)
    };

    const scales = {
        x: {
            type: 'category',
            title: { display: true, text: 'Fin de période de collecte (28 jours glissants)' },
            ticks: { maxRotation: 60, minRotation: 45 }
        },
        // Distribution : axe gauche + miroir droit (0–100 %)
        yPct: {
            type: 'linear',
            position: 'left',
            display: hasCatVisible,
            min: 0, max: 100,
            title: { display: true, text: '% de sessions' },
            grid: { drawOnChartArea: hasCatVisible }
        },
        yPctRight: {
            type: 'linear',
            position: 'right',
            display: hasCatVisible,
            min: 0, max: 100,
            title: { display: false },
            grid: { drawOnChartArea: false }
        },
        // P75 : axe droit + miroir gauche (plage identique)
        yP75: {
            type: 'linear',
            position: 'right',
            display: vis.p75,
            min: p75Min, max: p75Max,
            title: { display: true, text: cfg.yTitle },
            ticks: p75Ticks,
            grid: { drawOnChartArea: vis.p75 }
        },
        yP75Left: {
            type: 'linear',
            position: 'left',
            display: vis.p75,
            min: p75Min, max: p75Max,
            title: { display: false },
            ticks: p75Ticks,
            grid: { drawOnChartArea: false }
        }
    };

    const chart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            aspectRatio: 2.2,
            interaction: { mode: 'index', intersect: false },
            scales,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        filter: item => item.text !== 'Seuil Bon' && item.text !== 'Seuil Mauvais'
                    }
                },
                tooltip: vis.p75 ? {
                    enabled:  false,
                    external: cwvTooltipHandler,
                } : {
                    callbacks: {
                        title: function(items) {
                            const dateStr = items[0]?.label || '';
                            return dateStr ? 'Période : ' + collectionPeriodRange(dateStr) : dateStr;
                        },
                        label: function(ctx) {
                            const lbl = ctx.dataset.label || '';
                            if (ctx.parsed.y == null) return lbl + ' : N/A';
                            if (ctx.dataset._layer === 'threshold') return null;
                            return lbl + ' : ' + ctx.parsed.y.toFixed(1) + '%';
                        }
                    }
                }
            }
        }
    });

    chart._metricCode  = metricCode;
    charts[metricCode] = chart;
}

function rebuildChart(metricCode) {
    const ttEl = document.getElementById('cwv-chart-tooltip');
    if (ttEl) ttEl.style.opacity = '0';
    if (charts[metricCode]) {
        charts[metricCode].destroy();
        charts[metricCode] = null;
    }
    const canvasMap = { ttfb: 'ttfbChart', lcp: 'lcpChart', cls: 'clsChart', inp: 'inpChart' };
    buildChart(canvasMap[metricCode], metricCode);
}

function updateSubtitles() {
    const n = currentEndIndex - currentStartIndex + 1;
    const from = globalLabels[currentStartIndex] || '';
    const to   = globalLabels[currentEndIndex]   || '';

    const cwvSub = document.getElementById('cwvSubtitle');
    if (cwvSub) {
        cwvSub.textContent = n + ' période' + (n > 1 ? 's' : '') + ' · ' + from + ' → ' + to;
    }

    const navSub = document.getElementById('navSubtitle');
    if (navSub) {
        navSub.textContent = n + ' période' + (n > 1 ? 's' : '') + ' · ' + from + ' → ' + to;
    }
}

function updateChartsRange(start, end) {
    if (!globalLabels.length) return;
    currentStartIndex = start;
    currentEndIndex   = end;

    const visibleLabels = globalLabels.slice(start, end + 1);

    Object.entries(charts).forEach(([code, chart]) => {
        if (!chart) return;
        chart.data.labels = visibleLabels;
        chart.data.datasets = buildDatasets(code);
        chart.update();
    });

    // Mise à jour du chart Navigation Types
    if (navTypesChart) {
        rebuildNavTypesChart();
    }

    const rangeLabel = document.getElementById('rangeGlobalLabel');
    if (rangeLabel) {
        rangeLabel.textContent = 'Plage affichée : ' + (globalLabels[start] || '–') + ' → ' + (globalLabels[end] || '–');
    }

    updateSubtitles();
}

// Toggle visibilité d'une couche
function toggleLayer(metricCode, layer) {
    layerVisibility[metricCode][layer] = !layerVisibility[metricCode][layer];
    rebuildChart(metricCode);
}

// Construire les graphiques
['ttfb', 'lcp', 'cls', 'inp'].forEach(code => {
    const hasSeries = (series[code] && series[code].length) || (seriesP75[code] && seriesP75[code].length);
    if (hasSeries) {
        buildChart(code + 'Chart', code);
    }
});

// Toggle Distribution | P75 (exclusif)
document.querySelectorAll('.mode-toggle').forEach(group => {
    const metricCode = group.dataset.metric;
    const buttons = group.querySelectorAll('button');
    const catToggle = document.querySelector(`.metric-toggle[data-metric="${metricCode}"]`);
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.dataset.mode;
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (mode === 'p75') {
                layerVisibility[metricCode].p75  = true;
                layerVisibility[metricCode].good = false;
                layerVisibility[metricCode].ni   = false;
                layerVisibility[metricCode].poor = false;
                if (catToggle) catToggle.style.display = 'none';
            } else {
                const isSingle = (alignedData[metricCode] || []).length === 1;
                layerVisibility[metricCode].p75  = false;
                layerVisibility[metricCode].good = true;
                layerVisibility[metricCode].ni   = isSingle;
                layerVisibility[metricCode].poor = isSingle;
                // Remettre les boutons dans leur état cohérent
                if (catToggle) {
                    catToggle.querySelectorAll('button').forEach(b => {
                        b.classList.toggle('active', isSingle || b.dataset.view === 'good');
                    });
                    catToggle.style.display = '';
                }
            }
            rebuildChart(metricCode);
        });
    });
});

// Toggle individuel des catégories (Bon / À améliorer / Mauvais)
// - 1 URL  : toggle indépendant (plusieurs catégories visibles simultanément)
// - N URLs : radio exclusif (une seule catégorie à la fois, N courbes max)
document.querySelectorAll('.metric-toggle').forEach(group => {
    const metricCode = group.dataset.metric;
    group.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
            const layer    = btn.dataset.view;
            const isSingle = (alignedData[metricCode] || []).length === 1;

            if (isSingle) {
                toggleLayer(metricCode, layer);
                btn.classList.toggle('active');
            } else {
                // Radio : désélectionne tout, active uniquement le bouton cliqué
                group.querySelectorAll('button').forEach(b => {
                    layerVisibility[metricCode][b.dataset.view] = false;
                    b.classList.remove('active');
                });
                layerVisibility[metricCode][layer] = true;
                btn.classList.add('active');
                rebuildChart(metricCode);
            }
        });
    });
});

// Bascule les 4 cartes KPI sur l'URL sélectionnée (multi-URL)
function switchKpiUrl(idx) {
    const data = window.cruxKpiData;
    if (!data || !data[idx]) return;
    const metrics = data[idx].metrics;

    Object.keys(metrics).forEach(m => {
        const d        = metrics[m];
        const valEl    = document.getElementById('kpi-val-'    + m);
        const lblEl    = document.getElementById('kpi-lbl-'    + m);
        const markerEl = document.getElementById('kpi-marker-' + m);
        if (valEl)    { valEl.textContent    = d.valFmt;           valEl.style.color = d.color; }
        if (lblEl)    { lblEl.textContent    = d.label ? '● ' + d.label : '–'; lblEl.style.color = d.color; }
        if (markerEl) { markerEl.style.left  = d.pos + '%'; }
    });

    // Pills : mettre à jour l'état actif
    document.querySelectorAll('.kpi-url-btn').forEach((btn, i) => {
        btn.classList.toggle('active',  i === idx);
    });
}

// Navigation vers un onglet métrique depuis les badges du slider
function activateMetricTab(code) {
    const btn = document.getElementById('tab-btn-' + code);
    if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    document.getElementById('metricsCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Chart.js ne rend pas les canvas masqués : on redimensionne à l'activation de l'onglet
document.addEventListener('shown.bs.tab', e => {
    const code = e.target.dataset.bsTarget?.replace('#tab-', '');
    if (code && charts[code]) charts[code].resize();

    // Resize all charts when switching to URL tab (they may have been built while hidden)
    if (e.target.id === 'config-tab-url') {
        Object.values(charts).forEach(c => { if (c) c.resize(); });
        if (navTypesChart) navTypesChart.resize();
    }
});

// Export PNG
function exportChart(metricCode) {
    const chart = charts[metricCode];
    if (!chart) return;
    const a = document.createElement('a');
    a.href = chart.toBase64Image('image/png', 1);
    a.download = 'crux-' + metricCode + '.png';
    a.click();
}

// Boutons de période rapide
function applyPeriod(months) {
    if (!globalLabels.length) return;
    const maxIndex = globalLabels.length - 1;
    let startIndex = 0;

    if (months > 0) {
        const lastDate   = new Date(globalLabels[maxIndex]);
        const targetDate = new Date(lastDate);
        targetDate.setMonth(targetDate.getMonth() - months);
        startIndex = globalLabels.findIndex(d => new Date(d) >= targetDate);
        if (startIndex < 0) startIndex = 0;
    }

    const rangeStart = document.getElementById('rangeStart');
    const rangeEnd   = document.getElementById('rangeEnd');
    if (rangeStart) rangeStart.value = startIndex;
    if (rangeEnd)   rangeEnd.value   = maxIndex;
    updateChartsRange(startIndex, maxIndex);
    const track = document.getElementById('rangeTrack');
    if (track && rangeEnd) {
        const max  = parseInt(rangeEnd.max, 10) || 1;
        const pct1 = startIndex / max * 100;
        const pct2 = maxIndex   / max * 100;
        track.style.background =
            `linear-gradient(to right, #cbd5e1 ${pct1}%, #66b2b2 ${pct1}%, #66b2b2 ${pct2}%, #cbd5e1 ${pct2}%)`;
    }
}

document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        applyPeriod(parseInt(btn.dataset.months, 10));
    });
});

// Désactiver le bouton période actif quand on bouge le slider manuellement
function deactivatePeriodBtns() {
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
}

// ─── Popover analytique CWV sur les cellules P75 ───────────────────────────

const cwvMetricInfo = {
    ttfb: { name: 'Time to First Byte',        unit: 'ms', max: 3000, good: cwvThresholds.ttfb.good, poor: cwvThresholds.ttfb.poor, step: 200,  labels: ['0', '800 ms', '1,8 s', '3 s']      },
    lcp:  { name: 'Largest Contentful Paint',  unit: 'ms', max: 6000, good: cwvThresholds.lcp.good,  poor: cwvThresholds.lcp.poor,  step: 200,  labels: ['0', '2,5 s', '4 s', '6 s']          },
    cls:  { name: 'Cumulative Layout Shift',   unit: '',   max: 0.5,  good: cwvThresholds.cls.good,  poor: cwvThresholds.cls.poor,  step: 0.02, labels: ['0', '0,1', '0,25', '0,5']           },
    inp:  { name: 'Interaction to Next Paint', unit: 'ms', max: 700,  good: cwvThresholds.inp.good,  poor: cwvThresholds.inp.poor,  step: 20,   labels: ['0', '200 ms', '500 ms', '700 ms']    },
};

// Construit les bins de l'histogramme :
// - Priorité aux bins réels (queryRecord) scalés sur les ratios de la période survolée
// - Fallback : simulation uniforme
function buildHistogramBins(metric, good, ni, poor, urlIdx, p75) {
    const info        = cwvMetricInfo[metric];
    const realEntries = (window.cruxHistogramBins || {})[metric];
    const realEntry   = realEntries ? realEntries[urlIdx] : null;

    if (realEntry && realEntry.bins && realEntry.bins.length >= 1) {
        const bins  = realEntry.bins;
        const maxEnd = info.max;

        // Totaux réels par catégorie (période courante de queryRecord)
        let rGood = 0, rNi = 0, rPoor = 0;
        bins.forEach(b => {
            const end = b.end !== null ? b.end : maxEnd;
            if (end   <= info.good) rGood += b.density;
            else if (b.start >= info.poor) rPoor += b.density;
            else rNi += b.density;
        });

        // Facteurs de scaling vers la période actuellement survolée
        const scaleGood = rGood > 0 ? good / rGood : 1;
        const scaleNi   = rNi   > 0 ? ni   / rNi   : 1;
        const scalePoor = rPoor > 0 ? poor / rPoor  : 1;

        return bins.map(b => {
            const end         = b.end !== null ? b.end : maxEnd;
            const actualWidth = end - b.start;
            const cat         = end <= info.good ? 'good' : b.start >= info.poor ? 'poor' : 'ni';
            const scale       = cat === 'good' ? scaleGood : cat === 'ni' ? scaleNi : scalePoor;
            const scaled      = b.density * scale;
            return { actualWidth, density: scaled, heightValue: scaled / actualWidth, cat };
        });
    }

    // Fallback : simulation avec contrainte P75
    return simulateHistogramBins(metric, good, ni, poor, p75);
}

// Cache pour simulateHistogramBins — clé : "metric|good|ni|poor|p75"
const _simHistoCache = {};

// Découpe chaque catégorie CWV en sous-intervalles de largeur `step`.
// Si p75 est fourni, la catégorie qui le contient est divisée en deux zones de
// densité différente (avant / après le P75), donnant des barres de hauteurs variées.
// Pour les autres catégories, la densité est uniforme (seule granularité API disponible).
function simulateHistogramBins(metric, good, ni, poor, p75) {
    const cacheKey = metric + '|' + good + '|' + ni + '|' + poor + '|' + p75;
    if (_simHistoCache[cacheKey]) return _simHistoCache[cacheKey];
    const info = cwvMetricInfo[metric];
    if (!info) return [];

    const cdfGood = good || 0;              // % sessions sous le seuil Bon
    const cdfPoor = cdfGood + (ni || 0);   // % sessions sous le seuil Mauvais

    // Génère les bins pour une catégorie [start, end] avec densité totale `d`.
    // Si p75 tombe dans cette plage, on coupe en deux zones de densité différente.
    function catBins(start, end, d, cat) {
        const rangeWidth  = end - start;
        if (rangeWidth <= 0) return [];
        const nBins       = Math.ceil(rangeWidth / info.step);
        const p75InRange  = p75 != null && p75 > start && p75 < end;

        // Densité (%) cumulée au début de cette catégorie
        const cdfStart = cat === 'good' ? 0 : cat === 'ni' ? cdfGood : cdfPoor;
        // Densité (%) dans la partie [start..p75] et [p75..end] de cette catégorie
        const dBelow   = p75InRange ? 75 - cdfStart       : null;
        const dAbove   = p75InRange ? (cdfStart + d) - 75 : null;
        const p75Off   = p75InRange ? p75 - start         : null; // offset de P75 dans la plage

        const bins = [];
        for (let i = 0; i < nBins; i++) {
            const bs   = start + i * info.step;
            const be   = Math.min(bs + info.step, end);
            const bw   = be - bs;
            let density;

            if (p75InRange) {
                if (be <= p75) {
                    // bin entièrement sous P75
                    density = dBelow * (bw / p75Off);
                } else if (bs >= p75) {
                    // bin entièrement au-dessus de P75
                    density = dAbove * (bw / (rangeWidth - p75Off));
                } else {
                    // bin à cheval sur P75
                    density = dBelow  * ((p75 - bs) / p75Off) +
                              dAbove  * ((be - p75) / (rangeWidth - p75Off));
                }
            } else {
                density = d * (bw / rangeWidth);
            }

            const heightValue = bw > 0 ? density / bw : 0;
            bins.push({ actualWidth: bw, density, heightValue, cat });
        }
        return bins;
    }

    const result = [
        ...catBins(0,         info.good, good || 0, 'good'),
        ...catBins(info.good, info.poor, ni   || 0, 'ni'),
        ...catBins(info.poor, info.max,  poor || 0, 'poor'),
    ];
    _simHistoCache[cacheKey] = result;
    return result;
}

function formatP75(metric, val) {
    if (metric === 'cls') return Number(val).toFixed(3).replace('.', ',');
    if (val >= 1000) return (val / 1000).toFixed(2).replace('.', ',') + ' s';
    return Math.round(val) + ' ms';
}

function buildCwvPopover(metric, p75, good, ni, poor) {
    const info = cwvMetricInfo[metric];
    if (!info) return '';

    const goodW = Math.round(info.good / info.max * 100);
    const niW   = Math.round((info.poor - info.good) / info.max * 100);
    const poorW = 100 - goodW - niW;
    const p75Pos = Math.min(Math.round(p75 / info.max * 100), 98);

    const fmt = v => String(Number(v).toFixed(1)).replace('.', ',');

    return `
<div class="cwv-pop-inner">
  <div class="cwv-pop-title">${info.name}</div>
  <div class="cwv-pop-sub">p75 &middot; ${formatP75(metric, p75)}</div>

  <div class="cwv-pop-bar-stacked">
    <div style="width:${good}%;background:#22c55e"></div>
    <div style="width:${ni}%;background:#f97316"></div>
    <div style="width:${poor}%;background:#ef4444"></div>
  </div>

  <div class="cwv-pop-legend">
    <span class="cwv-dot" style="color:#22c55e">●</span> Bon
    <span class="cwv-pct">${fmt(good)} %</span>
  </div>
  <div class="cwv-pop-legend">
    <span class="cwv-dot" style="color:#f97316">●</span> À améliorer
    <span class="cwv-pct">${fmt(ni)} %</span>
  </div>
  <div class="cwv-pop-legend" style="margin-bottom:10px">
    <span class="cwv-dot" style="color:#ef4444">●</span> Mauvais
    <span class="cwv-pct">${fmt(poor)} %</span>
  </div>

  <div class="cwv-pop-scale">
    <div class="cwv-pop-scale-bar">
      <div style="width:${goodW}%;background:#22c55e"></div>
      <div style="width:${niW}%;background:#f97316"></div>
      <div style="width:${poorW}%;background:#ef4444"></div>
    </div>
    <div class="cwv-pop-marker" style="left:${p75Pos}%"></div>
  </div>
  <div class="cwv-pop-labels">
    ${info.labels.map(l => `<span>${l}</span>`).join('')}
  </div>
</div>`;
}

document.querySelectorAll('[data-cwv-metric]').forEach(el => {
    const metric = el.dataset.cwvMetric;
    const p75    = parseFloat(el.dataset.cwvP75);
    const good   = parseFloat(el.dataset.cwvGood);
    const ni     = parseFloat(el.dataset.cwvNi);
    const poor   = parseFloat(el.dataset.cwvPoor);

    el.style.cursor = 'help';

    new bootstrap.Popover(el, {
        content:     buildCwvPopover(metric, p75, good, ni, poor),
        html:        true,
        sanitize:    false,
        trigger:     'hover focus',
        placement:   'auto',
        customClass: 'cwv-popover',
    });
});

// Initialisation du double slider
if (globalLabels.length) {
    const rangeStart = document.getElementById('rangeStart');
    const rangeEnd   = document.getElementById('rangeEnd');

    // Met à jour la piste visuelle : gris / orange / gris selon les deux curseurs
    function updateSliderTrack() {
        const track = document.getElementById('rangeTrack');
        if (!track || !rangeStart || !rangeEnd) return;
        const max  = parseInt(rangeEnd.max, 10) || 1;
        const pct1 = parseInt(rangeStart.value, 10) / max * 100;
        const pct2 = parseInt(rangeEnd.value,   10) / max * 100;
        track.style.background =
            `linear-gradient(to right, #cbd5e1 ${pct1}%, #66b2b2 ${pct1}%, #66b2b2 ${pct2}%, #cbd5e1 ${pct2}%)`;
    }

    if (rangeStart && rangeEnd) {
        const maxIndex = globalLabels.length - 1;
        rangeStart.min = 0;
        rangeStart.max = maxIndex;
        rangeEnd.min   = 0;
        rangeEnd.max   = maxIndex;

        rangeStart.value = 0;
        rangeEnd.value   = maxIndex;
        updateChartsRange(0, maxIndex);
        updateSliderTrack();

        rangeStart.addEventListener('input', (e) => {
            let start = parseInt(e.target.value, 10);
            let end   = parseInt(rangeEnd.value, 10);
            if (start >= end) { start = Math.max(0, end - 1); rangeStart.value = start; }
            deactivatePeriodBtns();
            updateChartsRange(start, end);
            updateSliderTrack();
        });

        rangeEnd.addEventListener('input', (e) => {
            let end   = parseInt(e.target.value, 10);
            let start = parseInt(rangeStart.value, 10);
            if (end <= start) { end = Math.min(globalLabels.length - 1, start + 1); rangeEnd.value = end; }
            deactivatePeriodBtns();
            updateChartsRange(start, end);
            updateSliderTrack();
        });
    }
}

// ─── Navigation Types Chart ─────────────────────────────────────────────────

const navTypeConfig = {
    navigate:           { label: 'Navigation',          color: '#3b82f6' },
    navigate_cache:     { label: 'Navigation (cache)',  color: '#06b6d4' },
    reload:             { label: 'Rechargement',        color: '#f97316' },
    back_forward:       { label: 'Précédent/Suivant',   color: '#a855f7' },
    back_forward_cache: { label: 'BFCache',             color: '#14b8a6' },
    prerender:          { label: 'Pré-rendu',           color: '#ec4899' },
    restore:            { label: 'Restauration',        color: '#6b7280' },
};

const navTypeOrder = ['navigate', 'navigate_cache', 'reload', 'back_forward', 'back_forward_cache', 'prerender', 'restore'];

function alignNavTypesToGlobal(entry) {
    if (!entry) return {};
    const map = {};
    Object.keys(entry.types).forEach(typeName => {
        const dateMap = {};
        entry.dates.forEach((d, i) => { dateMap[d] = entry.types[typeName][i]; });
        map[typeName] = globalLabels.map(d => Object.prototype.hasOwnProperty.call(dateMap, d) ? dateMap[d] : null);
    });
    return map;
}

function rebuildNavTypesChart() {
    const navData = window.cruxNavTypes;
    if (!navData || !navData[currentNavUrlIdx]) return;

    const canvas = document.getElementById('navTypesChart');
    if (!canvas) return;

    const entry   = navData[currentNavUrlIdx];
    const aligned = alignNavTypesToGlobal(entry);
    const labels  = globalLabels.slice(currentStartIndex, currentEndIndex + 1);

    const datasets = [];
    navTypeOrder.forEach(typeName => {
        if (!aligned[typeName]) return;
        const cfg  = navTypeConfig[typeName] || { label: typeName, color: '#6b7280' };
        const data = aligned[typeName].slice(currentStartIndex, currentEndIndex + 1);
        if (data.some(v => v !== null && v > 0)) {
            datasets.push({
                label:           cfg.label,
                data:            data,
                backgroundColor: cfg.color + 'cc',
                borderColor:     cfg.color,
                borderWidth:     1,
                fill:            true,
                tension:         0.25,
                pointRadius:     0,
            });
        }
    });

    if (navTypesChart) {
        navTypesChart.data.labels = labels;
        navTypesChart.data.datasets = datasets;
        navTypesChart.update();
        return;
    }

    navTypesChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            aspectRatio: 2.2,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    type: 'category',
                    title: { display: true, text: 'Fin de période de collecte (28 jours glissants)' },
                    ticks: { maxRotation: 60, minRotation: 45 }
                },
                y: {
                    stacked: true,
                    min: 0, max: 100,
                    title: { display: true, text: '% de navigations' },
                }
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        title: function(items) {
                            const dateStr = items[0]?.label || '';
                            return dateStr ? 'Période : ' + collectionPeriodRange(dateStr) : dateStr;
                        },
                        label: function(ctx) {
                            if (ctx.parsed.y == null) return ctx.dataset.label + ' : N/A';
                            return ctx.dataset.label + ' : ' + ctx.parsed.y.toFixed(1).replace('.', ',') + ' %';
                        }
                    }
                }
            }
        }
    });
}

function switchNavUrl(idx) {
    currentNavUrlIdx = idx;
    if (navTypesChart) {
        navTypesChart.destroy();
        navTypesChart = null;
    }
    rebuildNavTypesChart();
    document.querySelectorAll('.nav-url-btn').forEach((btn, i) => {
        btn.classList.toggle('active', i === idx);
    });
}

// Construire le chart au chargement
if (window.cruxNavTypes && window.cruxNavTypes.length > 0) {
    rebuildNavTypesChart();
}

// ─── Pagination et tri des tableaux Bulk ─────────────────────────────────────

(function() {
    var PAGE_SIZE = 25;

    function paginateTable(tableId) {
        var table = document.getElementById(tableId);
        if (!table) return;
        var tbody = table.querySelector('tbody');
        var rows = Array.from(tbody.querySelectorAll('tr'));
        var totalRows = rows.length;
        var totalPages = Math.max(1, Math.ceil(totalRows / PAGE_SIZE));
        var currentPage = 1;
        var nav = document.getElementById('pagination-' + tableId);
        var info = document.getElementById('page-info-' + tableId);

        function render() {
            var start = (currentPage - 1) * PAGE_SIZE;
            var end = Math.min(start + PAGE_SIZE, totalRows);
            rows.forEach(function(r, i) {
                r.style.display = (i >= start && i < end) ? '' : 'none';
            });
            if (info) {
                info.textContent = totalRows > 0
                    ? (start + 1) + '–' + end + ' sur ' + totalRows + ' URLs'
                    : '';
            }
            buildNav();
        }

        function buildNav() {
            if (!nav) return;
            var ul = nav.querySelector('ul');
            ul.innerHTML = '';
            if (totalPages <= 1) return;

            addBtn('«', currentPage > 1 ? currentPage - 1 : null);
            for (var p = 1; p <= totalPages; p++) {
                if (totalPages > 7) {
                    if (p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)) {
                        addBtn(p, p, p === currentPage);
                    } else if (p === currentPage - 2 || p === currentPage + 2) {
                        var li = document.createElement('li');
                        li.className = 'page-item disabled';
                        li.innerHTML = '<span class="page-link">…</span>';
                        ul.appendChild(li);
                    }
                } else {
                    addBtn(p, p, p === currentPage);
                }
            }
            addBtn('»', currentPage < totalPages ? currentPage + 1 : null);
        }

        function addBtn(label, target, isActive) {
            var li = document.createElement('li');
            li.className = 'page-item' + (isActive ? ' active' : '') + (target === null ? ' disabled' : '');
            var a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = label;
            if (target !== null) {
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    currentPage = target;
                    render();
                });
            }
            li.appendChild(a);
            var ul = nav.querySelector('ul');
            ul.appendChild(li);
        }

        table._bulkGoPage = function(page) {
            currentPage = page;
            rows = Array.from(tbody.querySelectorAll('tr'));
            totalRows = rows.length;
            totalPages = Math.max(1, Math.ceil(totalRows / PAGE_SIZE));
            render();
        };

        render();
    }

    function initSortable(tableId) {
        var table = document.getElementById(tableId);
        if (!table) return;
        var ths = table.querySelectorAll('.sortable-th');
        ths.forEach(function(th) {
            th.addEventListener('click', function() {
                var col = parseInt(th.getAttribute('data-sort-col'));
                var type = th.getAttribute('data-sort-type');
                var asc = !th.classList.contains('sort-asc');
                ths.forEach(function(t) { t.classList.remove('sort-asc', 'sort-desc'); });
                th.classList.add(asc ? 'sort-asc' : 'sort-desc');

                var tbody = table.querySelector('tbody');
                var rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort(function(a, b) {
                    var va = a.children[col].getAttribute('data-sort-value') || '';
                    var vb = b.children[col].getAttribute('data-sort-value') || '';
                    if (type === 'number') {
                        va = va === '' ? -Infinity : parseFloat(va);
                        vb = vb === '' ? -Infinity : parseFloat(vb);
                        return asc ? va - vb : vb - va;
                    }
                    return asc ? va.localeCompare(vb) : vb.localeCompare(va);
                });
                rows.forEach(function(r) { tbody.appendChild(r); });
                if (table._bulkGoPage) table._bulkGoPage(1);
            });
        });
    }

    // Auto-discovery : initialise toutes les tables bulk présentes dans le DOM
    document.querySelectorAll('table[id^="bulk-table-"]').forEach(function(table) {
        initSortable(table.id);
        paginateTable(table.id);
    });
})();
