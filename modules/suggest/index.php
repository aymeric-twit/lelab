<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vérificateur de suggestions Google (AJAX)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* === GLOBAL === */
        html { scroll-behavior: smooth; }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
            min-height: 100vh;
            font-family: system-ui, sans-serif;
            color: #e5e7eb;
        }

        /* === TYPOGRAPHY === */
        h1 { font-size: 1.5rem; font-weight: 700; color: #f9fafb; }
        p   { color: #d1d5db; }

        /* === ALERT INFO === */
        .alert-info {
            background-color: rgba(31, 41, 55, 0.8) !important;
            border: 1px solid rgba(148, 163, 184, 0.3) !important;
            color: #d1d5db !important;
            border-radius: 1rem;
        }
        .alert-info strong { color: #f9fafb; }
        .alert-info code {
            color: #a855f7;
            background: rgba(168, 85, 247, 0.12);
            padding: 0.1rem 0.3rem;
            border-radius: 0.25rem;
        }
        .alert-info em { color: #9ca3af; font-style: normal; }

        /* === FORM CONTROLS === */
        .form-label { color: #d1d5db; font-weight: 600; }
        .form-select,
        .form-control {
            background-color: #1f2937 !important;
            border: 1px solid rgba(148, 163, 184, 0.3) !important;
            color: #e5e7eb !important;
            border-radius: 0.5rem;
        }
        .form-select:focus,
        .form-control:focus {
            background-color: #1f2937 !important;
            border-color: rgba(148, 163, 184, 0.6) !important;
            box-shadow: 0 0 0 0.2rem rgba(148, 163, 184, 0.15) !important;
            color: #e5e7eb !important;
        }
        .form-select option { background-color: #1f2937; color: #e5e7eb; }
        .form-check-input {
            background-color: #1f2937;
            border-color: rgba(148, 163, 184, 0.4);
        }
        .form-check-input:checked {
            background-color: #6366f1;
            border-color: #6366f1;
        }
        .form-check-label { color: #e5e7eb !important; }

        /* === BUTTONS === */
        .btn-primary {
            background-color: #6366f1;
            border-color: #6366f1;
            color: #f9fafb;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        .btn-danger  { background-color: #ef4444; border-color: #ef4444; }
        .btn-outline-secondary {
            border-color: rgba(148, 163, 184, 0.4);
            color: #9ca3af;
        }
        .btn-outline-secondary:hover {
            background-color: rgba(148, 163, 184, 0.15);
            color: #e5e7eb;
            border-color: rgba(148, 163, 184, 0.6);
        }

        /* === PROGRESS BAR === */
        .progress {
            background-color: #1f2937;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.5rem;
        }
        .progress-bar { background-color: #6366f1; }

        /* === TABLE === */
        .table {
            --bs-table-bg: transparent;
            color: #e5e7eb;
            border-color: rgba(148, 163, 184, 0.3);
        }
        .table-bordered { border: 1px solid rgba(148, 163, 184, 0.3); }
        .table thead th {
            background-color: rgba(15, 23, 42, 0.85);
            color: #d1d5db;
            border-color: rgba(148, 163, 184, 0.4) !important;
            font-weight: 600;
        }
        .table tbody tr:nth-child(odd)  td { background-color: rgba(15, 23, 42, 0.75); }
        .table tbody tr:nth-child(even) td { background-color: rgba(15, 23, 42, 0.45); }
        .table td { border-color: rgba(148, 163, 184, 0.3) !important; color: #e5e7eb; }
        .table-striped > tbody > tr { --bs-table-striped-bg: transparent; }

        /* === PAGINATION === */
        .page-link {
            background-color: #1f2937;
            border-color: rgba(148, 163, 184, 0.3);
            color: #9ca3af;
        }
        .page-link:hover {
            background-color: rgba(148, 163, 184, 0.15);
            color: #e5e7eb;
            border-color: rgba(148, 163, 184, 0.5);
        }
        .page-item.active .page-link {
            background-color: #6366f1;
            border-color: #6366f1;
            color: #f9fafb;
        }

        /* === ALERTS === */
        .alert-secondary {
            background-color: rgba(31, 41, 55, 0.8) !important;
            border: 1px solid rgba(148, 163, 184, 0.3) !important;
            color: #d1d5db !important;
        }

        /* === MISC === */
        .text-muted  { color: #9ca3af !important; }
        #resultLine  { white-space: pre-wrap; font-family: monospace; }
        #queries::placeholder { color: #4b5563; opacity: 1; }
        details summary { cursor: pointer; color: #9ca3af; }
        details summary:hover { color: #e5e7eb; }
        details pre  { font-size: 0.75rem; color: #9ca3af; }
        a            { color: #a855f7; }
        a:hover      { color: #c084fc; }
    </style>
</head>
<body>
<div class="container-md py-5 mx-auto" style="max-width: 950px;">
    <h1 class="mb-4">🔎 Vérificateur de suggestions Google</h1>
	<p class="mb-4">L'objectif de ce script est de détecter les requêtes qui n'ont pas de volume de recherche mensuel dans Adwords, mais qui sont présentes dans les suggestions de Google, et donc présentent un intérêt potentiel.</p>
	<div class="alert alert-info py-2 px-3 small mb-4">
    <strong>📌 Paramètres de recherche Google :</strong><br>
		<ul class="mb-1 mt-2 ps-3">
			<li><code>hl</code> : <em>Langue de l'interface</em> (ex : <code>fr</code> pour français, <code>en</code> pour anglais)</li>
			<li><code>gl</code> : <em>Pays de localisation</em> (ex : <code>FR</code> pour France, <code>US</code> pour États-Unis)</li>
		</ul>
		<div class="mt-2">
			Ces paramètres influencent les suggestions affichées par Google selon la langue et le pays choisis.
		</div>
	</div>

    <div class="mb-3">
        <label for="hl" class="form-label">🌍 Langue (hl)</label>
        <select id="hl" class="form-select" style="max-width: 200px;">
            <?php foreach (["fr", "en", "de", "es", "it", "nl"] as $lang): ?>
                <option value="<?= $lang ?>"><?= $lang ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="gl" class="form-label">📍 Pays (gl)</label>
        <select id="gl" class="form-select" style="max-width: 200px;">
            <?php foreach (["FR", "US", "CA", "BE", "CH", "DE", "ES", "IT", "NL"] as $country): ?>
                <option value="<?= $country ?>"><?= $country ?></option>
            <?php endforeach; ?>
        </select>
    </div>
	<div class="form-check mb-2">
	  <input class="form-check-input" type="checkbox" id="ignoreAccents">
	  <label class="form-check-label" for="ignoreAccents">
		Ignorer les accents (pour le dédoublonnage et l’analyse)
	  </label>
</div>
    <div class="mb-3">
        <label for="queries" class="form-label">✏️ Entrez vos requêtes (une par ligne) :</label>
        <textarea id="queries" rows="6" class="form-control"placeholder="lunette de soleil bébé
lunette enfant 3 ans
lunette femme rouge"></textarea>
    </div>

    <button id="startBtn" class="btn btn-primary mb-3">🚀 Lancer la vérification</button>
    <button id="stopBtn" class="btn btn-danger mb-3 ms-2" style="display: none;">⛔ Stop</button>

    <div id="progressWrapper" class="mb-3" style="display:none;">
        <div class="progress">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
        </div>
    </div>
    <div id="statusLine" class="mt-2 small text-muted" style="display:none;"></div>

    <div id="filterWrapper" class="mb-3" style="display:none;">
        <label for="filter" class="form-label">🔍 Filtrer les résultats</label>
        <select id="filter" class="form-select">
            <option value="all">Toutes les requêtes</option>
            <option value="yes">✅ Suggestions existantes</option>
            <option value="no">❌ Non présentes</option>
        </select>
    </div>

    <table class="table table-bordered table-striped table-sm" id="resultTable" style="display:none;">
        <thead>
        <tr>
            <th>Requête</th>
            <th>Est une suggestion Google ?</th>
            <th>Suggestions similaires</th>
			<th>Détails</th>
        </tr>
        </thead>
        <tbody id="resultBody"></tbody>
    </table>
    <nav id="paginationWrapper" class="d-flex justify-content-center mt-3" style="display:none;">
        <ul id="pagination" class="pagination pagination-sm mb-0"></ul>
    </nav>
    <button id="exportCSV" class="btn btn-outline-secondary mb-3" style="display: none;">📥 Exporter (selon filtre)</button>
    <div id="resultLine" class="alert alert-secondary" style="display: none;"></div>
</div>

<script>
let rowsPerPage = 50;
let currentPage = 1;
let results = [];
let currentIndex = 0;
let isPaused = false;
let isStopped = false;
let currentQueries = [], currentHl = '', currentGl = '';

const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const progressWrapper = document.getElementById('progressWrapper');
const progressBar = document.getElementById('progressBar');
const statusLine = document.getElementById('statusLine');
const resultTable = document.getElementById('resultTable');
const resultBody = document.getElementById('resultBody');

startBtn.addEventListener('click', async () => {

    if (startBtn.textContent.includes('Pause')) {
        isPaused = true;
        startBtn.textContent = '▶️ Reprendre';
        return;
    }
    if (startBtn.textContent.includes('Reprendre')) {
        isPaused = false;
        startBtn.textContent = '⏸ Pause';
        processNextQuery();
        return;
    }
	
		const rawLines = document.getElementById('queries').value
			.trim()
			.split('\n')
			.map(q => q.trim())
			.filter(q => q !== '');

		const seen = new Set();
		const queries = [];
		let duplicateCount = 0;

		for (let q of rawLines) {
			const key = ignoreAccents ? normalizeString(q) : q;
			if (seen.has(key)) {
				duplicateCount++;
			} else {
				seen.add(key);
				queries.push(q); // on garde la version d'origine visible
			}
		}

		if (duplicateCount > 0) {
			statusLine.textContent = `${duplicateCount} doublon(s) supprimé(s) sur ${rawLines.length} lignes.`;
		}


	if (duplicateCount > 0) {
		alert(`${duplicateCount} doublon(s) exact(s) supprimé(s) sur ${rawLines.length} lignes.`);
	}
	
    if (queries.length === 0) return alert("Veuillez entrer au moins une requête.");

    results = [];
    currentIndex = 0;
    isPaused = false;
    isStopped = false;
    currentQueries = queries;
    currentHl = document.getElementById('hl').value;
    currentGl = document.getElementById('gl').value;

    startBtn.textContent = '⏸ Pause';
    stopBtn.style.display = 'inline-block';

    resultBody.innerHTML = '';
    resultTable.style.display = 'none';
    progressWrapper.style.display = 'block';
    statusLine.style.display = 'block';
    statusLine.textContent = 'Démarrage du traitement...';
	stopBtn.disabled = false;
    processNextQuery();
});

stopBtn.addEventListener('click', () => {
    isStopped = true;
    isPaused = false;
    stopBtn.disabled = true;
    startBtn.disabled = true;
    statusLine.textContent = '⛔ Traitement stoppé par l’utilisateur.';
});

async function processNextQuery() {
if (isStopped) return; // Ne rien faire si l'utilisateur a cliqué sur STOP
if (currentIndex >= currentQueries.length) {
    return finishProcessing(); // Fin normale du traitement
}
if (isPaused) return;

	

    const q = currentQueries[currentIndex];
    const formData = new FormData();
    formData.append('query', q);
    formData.append('hl', currentHl);
    formData.append('gl', currentGl);

    statusLine.textContent = `Traitement (${currentIndex + 1}/${currentQueries.length}) : "${q}" ...`;
    const startTime = performance.now();

    try {
        const processUrl = (window.MODULE_BASE_URL || '') + '/process.php';
        const res = await fetch(processUrl, { method: 'POST', body: formData });
        const data = await res.json();
		data._url = `https://suggestqueries.google.com/complete/search?client=firefox&hl=${currentHl}&gl=${currentGl}&q=${encodeURIComponent(q)}`;
        const ignoreAccents = document.getElementById('ignoreAccents').checked;
        const match = (data.suggestions || []).some(s => {
			return ignoreAccents
				? normalizeString(s) === normalizeString(q)
				: s === q;
		});

		results.push({
		  query: q,
		  present: match,
		  suggestions: data.suggestions || [],
		  raw: data,
		});

        statusLine.textContent = `Terminé pour "${q}" : ${data.present ? '✅' : '❌'}`;
    } catch (e) {
        results.push({ query: q, present: false, error: true });
        statusLine.textContent = `⚠️ Erreur lors du traitement de "${q}"`;
    }

    const percent = Math.round(((currentIndex + 1) / currentQueries.length) * 100);
    progressBar.style.width = percent + '%';
    progressBar.innerText = percent + '%';
    currentIndex++;

    const elapsed = performance.now() - startTime;
    const wait = Math.max(0, 200 - elapsed);
    await new Promise(resolve => setTimeout(resolve, wait));

    processNextQuery();
}

function finishProcessing() {
    console.log("Résultats finaux:", results);
    if (results.length === 0) {
        alert("Aucune donnée reçue !");
        return;
    }
    renderTablePage(1);
    setupPagination();
    document.getElementById('filterWrapper').style.display = 'block';
    resultTable.style.display = 'table';
    progressWrapper.style.display = 'none';
    startBtn.textContent = '🚀 Lancer la vérification';
    stopBtn.style.display = 'none';
    startBtn.disabled = false;
    stopBtn.disabled = false;
    document.getElementById('exportCSV').style.display = 'inline-block';
    statusLine.textContent = `✅ Traitement terminé.`;
    window.scrollTo({ top: resultTable.offsetTop - 100, behavior: 'smooth' });
}
function getFilteredResults() {
    const filterValue = document.getElementById('filter').value;
    return results.filter(r => {
        if (filterValue === 'all') return true;
        return (filterValue === 'yes' && r.present) || (filterValue === 'no' && !r.present);
    });
}

function renderTablePage(page) {
    const resultBody = document.getElementById('resultBody');
    const filtered = getFilteredResults();

    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    resultBody.innerHTML = '';

    const pageResults = filtered.slice(start, end);

    for (let r of pageResults) {
        const row = document.createElement('tr');
        row.dataset.present = r.present ? 'yes' : 'no';

        const cell1 = document.createElement('td');
        cell1.textContent = r.query;

        const cell2 = document.createElement('td');
        cell2.className = 'text-center';
        cell2.textContent = r.error ? '❗ Erreur' : (r.present ? '✅' : '❌');
		const cellSimilar = document.createElement('td');

		if (r.error || !r.suggestions || r.suggestions.length === 0) {
			cellSimilar.textContent = '—';
		} else {
			const normalizedQuery = r.query.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

			const filteredSuggestions = r.suggestions.filter(s => {
				const normalizedS = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
				return normalizedS !== normalizedQuery;
			});

			if (filteredSuggestions.length === 0) {
				cellSimilar.textContent = '—';
			} else {
				const details = document.createElement('details');
				const summary = document.createElement('summary');
				summary.textContent = `${filteredSuggestions.length} suggestion${filteredSuggestions.length > 1 ? 's' : ''}`;
				const ul = document.createElement('ul');
				ul.className = 'mb-0 ps-3';

				for (let s of filteredSuggestions) {
					const li = document.createElement('li');
					li.textContent = s;
					ul.appendChild(li);
				}

				details.appendChild(summary);
				details.appendChild(ul);
				cellSimilar.appendChild(details);
			}
		}

		
        const cell3 = document.createElement('td');
        if (r.error) {
            cell3.textContent = 'Erreur lors du traitement';
        } else {
            const details = document.createElement('details');
			const summary = document.createElement('summary');
			summary.textContent = 'Voir';
			const pre = document.createElement('pre');
			pre.style.marginTop = '0.5rem';
			pre.textContent = JSON.stringify(r.raw, null, 2);
			details.appendChild(summary);
			details.appendChild(pre);

			if (r.raw && r.raw._url) {
				const link = document.createElement('a');
				link.href = r.raw._url;
				link.target = '_blank';
				link.className = 'd-block small text-decoration-underline text-primary mt-1';
				link.textContent = '🔗 Voir l’URL requêtée';
				details.appendChild(link);
			}

		cell3.appendChild(details);
        }

        row.appendChild(cell1);
        row.appendChild(cell2);
		row.appendChild(cellSimilar);
        row.appendChild(cell3);
        resultBody.appendChild(row);
    }
}

function setupPagination() {
    const pagination = document.getElementById('pagination');
    const filtered = getFilteredResults();
    const totalPages = Math.ceil(filtered.length / rowsPerPage);

    pagination.innerHTML = '';

    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement('li');
        li.className = 'page-item' + (i === currentPage ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = i;
        a.addEventListener('click', (e) => {
            e.preventDefault();
            currentPage = i;
            renderTablePage(currentPage);
            setupPagination(); // re-render to highlight current page
        });
        li.appendChild(a);
        pagination.appendChild(li);
    }

    document.getElementById('paginationWrapper').style.display = totalPages > 1 ? 'flex' : 'none';
}

// Gère les changements de filtre
document.getElementById('filter').addEventListener('change', () => {
    currentPage = 1;
    renderTablePage(currentPage);
    setupPagination();
});
document.getElementById('exportCSV').addEventListener('click', () => {
    const filterValue = document.getElementById('filter').value;

    const filteredResults = results.filter(r => {
        if (filterValue === 'all') return true;
        return (filterValue === 'yes' && r.present) || (filterValue === 'no' && !r.present);
    });

    if (filteredResults.length === 0) {
        alert("Aucune ligne à exporter pour ce filtre.");
        return;
    }

	const headers = ['Requête', 'Est une suggestion', 'Suggestions similaires','Détails',];
	const csvRows = [headers.join(',')];

	filteredResults.forEach(r => {
		const requete = r.query.replace(/"/g, '""');
		const statut = r.error ? '❗ Erreur' : (r.present ? '✅' : '❌');
		const contenu = r.error ? 'Erreur' : JSON.stringify(r.raw).replace(/"/g, '""');

		// Suggestions similaires filtrées
		let suggestions = [];
		if (!r.error && Array.isArray(r.suggestions)) {
			const normalizedQuery = r.query.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
			suggestions = r.suggestions.filter(s => {
				const normalizedS = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
				return normalizedS !== normalizedQuery;
			});
		}

		const similar = suggestions.join(' | ').replace(/"/g, '""');

		csvRows.push(`"${requete}","${statut}","${similar}","${contenu}"`);
	});


	const utf8Bom = '\uFEFF'; // ← BOM UTF-8
	const blob = new Blob([utf8Bom + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'suggestions_export_filtrées.csv';
    link.click();
});
function normalizeString(str) {
    return str
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
}
</script>
</body>
</html>
