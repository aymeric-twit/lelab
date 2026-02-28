# Patterns JavaScript — Reference

## AJAX POST avec FormData

```javascript
const baseUrl = window.MODULE_BASE_URL || '.';

async function envoyerRequete(query) {
    const formData = new FormData();
    formData.append('query', query);
    formData.append('hl', 'fr');

    try {
        const res = await fetch(baseUrl + '/process.php', {
            method: 'POST',
            body: formData
        });

        if (res.status === 429) {
            afficherStatus('Quota mensuel epuise.', 'error');
            return null;
        }

        if (!res.ok) {
            throw new Error('Erreur HTTP ' + res.status);
        }

        return await res.json();
    } catch (err) {
        afficherStatus('Erreur reseau : ' + err.message, 'error');
        return null;
    }
}
```

## Status feedback

```javascript
function afficherStatus(message, type) {
    const el = document.getElementById('statusMsg');
    el.textContent = message;
    el.className = 'status-msg status-' + type; // loading, success, error, warning
    el.classList.remove('d-none');
}
```

## Progress polling (worker arriere-plan)

```javascript
let pollingTimer = null;

function demarrerPolling(jobId) {
    pollingTimer = setInterval(function() {
        fetch(baseUrl + '/progress.php?job=' + encodeURIComponent(jobId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'done') {
                    clearInterval(pollingTimer);
                    chargerResultats(jobId);
                    return;
                }
                if (data.status === 'error') {
                    clearInterval(pollingTimer);
                    afficherStatus(data.message || 'Erreur inconnue', 'error');
                    return;
                }
                mettreAJourBarre(data.percent || 0, data.step || '');
            })
            .catch(function() {
                // Silencieux — reessayer au prochain tick
            });
    }, 2000);
}

function mettreAJourBarre(percent, step) {
    const bar = document.getElementById('progressBar');
    bar.style.width = percent + '%';
    bar.textContent = percent + '%';
    document.getElementById('statusLine').textContent = step;
}
```

## SSE (Server-Sent Events) — cote client

```javascript
function demarrerStream(domain, device) {
    const params = new URLSearchParams({ domain: domain, device: device });
    const evtSource = new EventSource(baseUrl + '/bulk_stream.php?' + params.toString());

    evtSource.addEventListener('log', function(e) {
        const data = JSON.parse(e.data);
        afficherStatus(data.message, 'loading');
    });

    evtSource.addEventListener('done', function(e) {
        evtSource.close();
        const data = JSON.parse(e.data);
        chargerResultats(data.cacheId);
        afficherStatus('Traitement termine.', 'success');
    });

    evtSource.addEventListener('error', function() {
        evtSource.close();
        afficherStatus('Connexion interrompue.', 'error');
    });
}
```

## Tri de tableau

```javascript
let sortColumn = null;
let sortDirection = 'asc';

function trierTableau(colonne) {
    if (sortColumn === colonne) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = colonne;
        sortDirection = 'asc';
    }

    document.querySelectorAll('.sortable').forEach(function(th) {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    const thActif = document.querySelector('[data-sort="' + colonne + '"]');
    if (thActif) thActif.classList.add('sort-' + sortDirection);

    results.sort(function(a, b) {
        let va = a[colonne], vb = b[colonne];
        if (va == null) return 1;
        if (vb == null) return -1;
        if (typeof va === 'string') va = va.toLowerCase();
        if (typeof vb === 'string') vb = vb.toLowerCase();
        return sortDirection === 'asc' ? (va > vb ? 1 : -1) : (va < vb ? 1 : -1);
    });

    renderTablePage(1);
}
```

## Export CSV

```javascript
function exporterCSV(donnees, nomFichier) {
    if (!donnees.length) return;
    const colonnes = Object.keys(donnees[0]);
    const lignes = [colonnes.join(';')];
    donnees.forEach(function(row) {
        lignes.push(colonnes.map(function(col) {
            const val = (row[col] ?? '').toString().replace(/"/g, '""');
            return '"' + val + '"';
        }).join(';'));
    });
    const blob = new Blob(['\uFEFF' + lignes.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nomFichier;
    a.click();
    URL.revokeObjectURL(url);
}
```

## CSRF — recuperation du token

```javascript
function getCsrfToken() {
    const input = document.querySelector('input[name="_csrf_token"]');
    return input ? input.value : '';
}

// Avec FormData (le token est deja dans le formulaire si auto-injecte)
const formData = new FormData(document.querySelector('form'));
fetch(baseUrl + '/process.php', { method: 'POST', body: formData });

// Avec un body JSON ou hors formulaire
fetch(baseUrl + '/process.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
    },
    body: JSON.stringify({ query: 'test' })
});
```
