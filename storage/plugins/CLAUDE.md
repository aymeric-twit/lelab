# CLAUDE.md — Guide de développement des plugins

Ce guide documente l'architecture des plugins de la plateforme SEO, basée sur le plugin de référence **CrUX History Explorer** (`storage/plugins/crux-history/crux/`).

---

## 1. Vue d'ensemble

Les plugins sont des **applications PHP autonomes** intégrées dans la plateforme via un système de registre. Chaque plugin :

- Est écrit en **français** (variables, fonctions, commentaires, interface)
- Fonctionne **sans framework** — PHP pur avec dépendances CDN
- Est rendu dans le layout plateforme (embedded/iframe) ou en mode passthrough
- Gère ses propres appels API, sa logique métier et son interface
- Dépend de **Bootstrap 5.3** et **Google Fonts (Poppins)** pour le style
- Peut utiliser **Chart.js** (CDN) pour les visualisations

---

## 2. Architecture fichiers

```
storage/plugins/mon-plugin/
├── module.json          # Métadonnées et configuration (OBLIGATOIRE)
├── boot.php             # Initialisation (chargement .env, constantes)
├── index.php            # Point d'entrée principal (formulaire + résultats)
├── functions.php        # Logique métier, appels API, handler principal
├── styles.css           # Styles CSS (variables brand + composants)
├── app.js               # JavaScript (Chart.js, SSE, interactions)
├── .env                 # Variables d'environnement (clés API)
├── bulk_stream.php      # Sous-route SSE (streaming temps réel)
├── robots_sitemap.php   # Sous-route AJAX (endpoint JSON)
└── CLAUDE.md            # Documentation spécifique au plugin
```

Le seul fichier **obligatoire** est `module.json`. Les autres dépendent de la complexité du plugin.

---

## 3. Schema `module.json`

```json
{
    "slug": "crux-history",
    "name": "CrUX History Explorer",
    "description": "Analysez les Core Web Vitals via l'API Google CrUX History.",
    "version": "1.0.0",
    "icon": "bi-graph-up",
    "entry_point": "index.php",
    "sort_order": 30,
    "quota_mode": "api_call",
    "default_quota": 1000,
    "env_keys": ["CRUX_API_KEY"],
    "display_mode": "embedded",
    "categorie_id": null,
    "routes": [
        {
            "path": "bulk_stream.php",
            "method": "POST",
            "type": "stream"
        },
        {
            "path": "robots_sitemap.php",
            "method": "GET",
            "type": "ajax"
        }
    ]
}
```

### Champs

| Champ | Type | Obligatoire | Description |
|-------|------|:-----------:|-------------|
| `slug` | `string` | oui | Identifiant unique, kebab-case (ex: `crux-history`) |
| `name` | `string` | oui | Nom affiché dans la sidebar et le header |
| `description` | `string` | non | Description courte pour l'admin |
| `version` | `string` | non | Version semver (défaut: `1.0.0`) |
| `icon` | `string` | non | Classe Bootstrap Icons (défaut: `bi-tools`) |
| `entry_point` | `string` | non | Fichier principal (défaut: `index.php`) |
| `sort_order` | `int` | non | Ordre d'affichage dans la sidebar (défaut: `100`) |
| `quota_mode` | `string` | non | Mode de quota : `none`, `request`, `form_submit`, `api_call` (défaut: `none`) |
| `default_quota` | `int` | non | Quota mensuel par défaut, `0` = illimité (défaut: `0`) |
| `env_keys` | `string[]` | non | Clés d'environnement requises par le plugin |
| `display_mode` | `string` | non | Mode d'affichage : `embedded`, `iframe`, `passthrough` (défaut: `embedded`) |
| `categorie_id` | `int\|null` | non | ID de la catégorie dans la table `categories` |
| `routes` | `array` | non | Sous-routes du plugin (voir section 6) |

---

## 4. Point d'entrée `index.php`

Le point d'entrée suit le pattern **GET = formulaire, POST = traitement + résultats**.

### Pattern standard

```php
<?php
require_once __DIR__ . '/functions.php';

// Le handler retourne un tableau de données pour la vue
$view = handle_mon_form();

// Extraction des variables de vue
$results    = $view['results'];
$hasPost    = $view['hasPost'];
$inputText  = $view['inputText'];
$jsSeries   = $view['jsSeries']; // Données JSON pour JavaScript
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Plugin — Description</title>

    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Styles du plugin -->
    <link rel="stylesheet" href="styles.css">

    <!-- Chart.js CDN (si nécessaire) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Navbar (retirée automatiquement en mode embedded) -->
<nav class="navbar mb-4">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            Mon Plugin
            <span class="d-block d-sm-inline ms-sm-2">Sous-titre</span>
        </span>
    </div>
</nav>

<div class="container-lg pb-5">
    <!-- Formulaire de configuration -->
    <div class="card shadow-sm mb-4" id="configCard">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Configuration</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <!-- Les champs du formulaire -->
                <button type="submit" class="btn btn-primary">Analyser</button>
            </form>
        </div>
    </div>

    <!-- Résultats (affichés si POST) -->
    <?php if ($hasPost): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Contenu des résultats -->
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Injection des données PHP → JS -->
<script>
    window.monPluginData = <?= $jsSeries ?>;
</script>
<script src="app.js"></script>

</body>
</html>
```

### Points importants

- Le `<nav class="navbar">` est **automatiquement supprimé** en mode embedded (la plateforme injecte sa propre navbar)
- Les liens CDN (Bootstrap, Chart.js, Google Fonts) sont **ignorés** par `extractParts` pour éviter les doublons
- Les chemins relatifs (`styles.css`, `app.js`) sont **réécrits** automatiquement vers `/module-assets/{slug}/`
- Le token CSRF est **injecté automatiquement** dans les `<form method="POST">`

---

## 5. Logique métier `functions.php`

### Structure recommandée

```php
<?php
// ========================
// CONFIG
// ========================

// Chargement .env (si boot.php ne le fait pas)
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env') as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) {
            [$k, $v] = explode('=', trim($line), 2);
            putenv("$k=$v");
        }
    }
}

define('MA_CLE_API', getenv('MA_CLE_API') ?: '');

// Constantes métier
const MES_METRIQUES = [
    'metrique1' => 'api_field_name',
    'metrique2' => 'api_field_name_2',
];

// ========================
// HELPER cURL
// ========================

function creer_curl(string $url, array $opts = []) {
    $ch = curl_init($url);
    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    foreach ($defaults as $k => $v) {
        curl_setopt($ch, $k, $opts[$k] ?? $v);
    }
    foreach ($opts as $k => $v) {
        if (!isset($defaults[$k])) {
            curl_setopt($ch, $k, $v);
        }
    }
    return $ch;
}

// ========================
// APPELS API
// ========================

function appeler_mon_api(string $endpoint, array $payload): ?array {
    $ch = creer_curl($endpoint, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    return json_decode($response, true);
}

// ========================
// HANDLER PRINCIPAL
// ========================

/**
 * Gère le formulaire : retourne un tableau de données pour la vue.
 * Ne fait AUCUN echo/print — toute la sortie est dans index.php.
 *
 * @return array<string, mixed>
 */
function handle_mon_form(): array {
    $defaults = [
        'results'    => [],
        'hasPost'    => false,
        'inputText'  => '',
        'jsSeries'   => '{}',
        'erreur'     => null,
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $defaults;
    }

    $input = trim($_POST['query'] ?? '');
    if ($input === '') {
        return array_merge($defaults, ['hasPost' => true, 'erreur' => 'Champ requis']);
    }

    // Logique métier...
    $results = appeler_mon_api('https://api.example.com/endpoint', ['query' => $input]);

    return array_merge($defaults, [
        'hasPost'   => true,
        'inputText' => $input,
        'results'   => $results ?? [],
        'jsSeries'  => json_encode($results ?? [], JSON_UNESCAPED_UNICODE),
    ]);
}
```

### Règles

- Le handler retourne **toujours** un tableau associatif — jamais d'`echo`
- Inclure une version JSON des données dans le tableau (`$jsSeries`) pour le passage PHP → JS
- Séparer les constantes, helpers cURL, appels API et handler dans des sections claires

---

## 6. Sous-routes

Chaque sous-route est déclarée dans `module.json` → `routes[]` et correspond à un fichier PHP dans le répertoire du plugin.

### Types de routes

| Type | Comportement | Usage |
|------|-------------|-------|
| `page` | Rendu dans le layout plateforme (extractParts) | Pages secondaires |
| `ajax` | Passthrough (pas de layout), réponse JSON | Endpoints AJAX |
| `stream` | Passthrough, réponse SSE (text/event-stream) | Streaming temps réel |

### Exemple sous-route AJAX — `robots_sitemap.php`

```php
<?php
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$domain = trim($_GET['domain'] ?? '');
$resultat = $domain !== '' ? detecter_sitemap($domain) : null;

echo json_encode(['sitemap_url' => $resultat]);
```

**Appel côté JS** :
```javascript
fetch(window.MODULE_BASE_URL + '/robots_sitemap.php?domain=' + encodeURIComponent(domain))
    .then(r => r.json())
    .then(data => { /* ... */ });
```

### Exemple sous-route SSE — `bulk_stream.php`

```php
<?php
require_once __DIR__ . '/functions.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Désactiver tous les buffers de sortie
while (ob_get_level()) {
    ob_end_flush();
}

$domain = $_GET['domain'] ?? '';
set_time_limit(0);

// Callback SSE : chaque log est envoyé en temps réel
$onLog = function (string $message) {
    echo "event: log\ndata: " . json_encode(['message' => $message], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    if (connection_aborted()) {
        exit;
    }
};

$result = traiter_en_masse($domain, $onLog);

echo "event: done\ndata: " . json_encode(['resultat' => $result]) . "\n\n";
flush();
```

**Appel côté JS** :
```javascript
const source = new EventSource(
    window.MODULE_BASE_URL + '/bulk_stream.php?domain=' + encodeURIComponent(domain)
);

source.addEventListener('log', (e) => {
    const data = JSON.parse(e.data);
    console.log(data.message);
});

source.addEventListener('done', (e) => {
    const data = JSON.parse(e.data);
    source.close();
    // Traiter les résultats finaux
});
```

---

## 7. Systeme de quotas

### Les 4 modes

| Mode | `quota_mode` | Comportement |
|------|-------------|-------------|
| **Aucun** | `none` | Pas de suivi, accès illimité |
| **Par requête** | `request` | Chaque chargement de page = 1 unité (auto-incrémenté par le middleware) |
| **Par soumission** | `form_submit` | Chaque POST = 1 unité (auto-incrémenté par le middleware) |
| **Par appel API** | `api_call` | Le plugin incrémente manuellement, le middleware bloque si dépassé |

### Mode `api_call` — le plus courant pour les plugins SEO

Le middleware `CheckModuleQuota` agit comme **filet de sécurité** : il vérifie le quota et bloque si dépassé, mais **n'auto-incrémente pas**. C'est au plugin de compter ses unités via les méthodes `Quota`.

#### Méthodes disponibles

```php
use Platform\Module\Quota;

// Incrémenter après une opération réussie (1 unité)
Quota::track('mon-slug');

// Incrémenter de N unités
Quota::track('mon-slug', 5);

// Vérifier + incrémenter atomiquement (recommandé)
// Retourne true si le quota permet l'opération, false sinon
if (!Quota::trackerSiDisponible('mon-slug', 3)) {
    echo json_encode(['error' => 'Quota dépassé']);
    exit;
}

// Consulter le quota restant
$restant = Quota::restant('mon-slug');
// null  → quota illimité
// 0     → quota atteint
// int>0 → unités restantes
```

#### Bonnes pratiques

- Utiliser `Quota::trackerSiDisponible()` **avant** un traitement coûteux (ex: appel API externe)
- Utiliser `Quota::track()` **après** une opération réussie si l'échec est fréquent
- Utiliser `Quota::restant()` pour afficher le quota restant dans l'interface
- Ne **jamais** se fier uniquement au middleware — toujours gérer le quota côté plugin pour un contrôle fin

#### Exemple dans un endpoint AJAX

```php
<?php
require_once __DIR__ . '/functions.php';

use Platform\Module\Quota;

header('Content-Type: application/json');

$urls = json_decode(file_get_contents('php://input'), true)['urls'] ?? [];
$nbUrls = count($urls);

// Vérifier le quota AVANT le traitement
if (!Quota::trackerSiDisponible('mon-slug', $nbUrls)) {
    $restant = Quota::restant('mon-slug');
    echo json_encode([
        'error'          => 'Quota insuffisant',
        'quota_exceeded' => true,
        'restant'        => $restant,
    ]);
    exit;
}

// Le quota a déjà été incrémenté, traiter les URLs
$resultats = traiter_urls($urls);
echo json_encode(['donnees' => $resultats]);
```

---

## 8. Charte graphique CSS

Source de référence : `public/assets/css/platform.css` (design system de la plateforme).

### Variables CSS

Le plugin doit déclarer les variables brand dans son `:root`. En mode embedded, la plateforme les fournit déjà ; en mode iframe/standalone, le plugin doit les définir lui-même.

```css
:root {
    /* ── Palette brand ─────────────────────────────── */
    --brand-teal:        #66b2b2;   /* Bleu givre — accents, liens, focus */
    --brand-teal-light:  #e8f4f4;   /* Fonds légers, highlights */
    --brand-gold:        #fbb03b;   /* Jaune tournesol — header border, badges */
    --brand-gold-light:  #fef4e0;   /* Fonds d'alerte doux */
    --brand-dark:        #004c4c;   /* Canard profond — header, btn-primary */
    --brand-linen:       #f2f2f2;   /* Lin léger — background body */
    --brand-anthracite:  #333333;   /* Texte principal */

    /* ── Couleurs sémantiques ──────────────────────── */
    --color-good:   #22c55e;   /* Succès, bon score */
    --color-warn:   #f97316;   /* Attention, moyen */
    --color-bad:    #ef4444;   /* Erreur, mauvais */

    /* ── Palette neutre ────────────────────────────── */
    --bg-body:       #f2f2f2;
    --bg-card:       #ffffff;
    --bg-card-alt:   #f1f5f9;   /* Fond alterné, thead tables */
    --border-color:  #e2e8f0;
    --text-primary:  #0f172a;   /* Quasi-noir — texte principal */
    --text-secondary:#475569;   /* Labels, texte secondaire */
    --text-muted:    #94a3b8;   /* Placeholders, texte discret */

    /* ── CWV (si pertinent pour le plugin) ─────────── */
    --cwv-good: #22c55e;
    --cwv-ni:   #f97316;
    --cwv-poor: #ef4444;
}
```

### Composants

#### Body & typographie

```css
body {
    font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    background: var(--bg-body);
    color: var(--brand-anthracite);
    scrollbar-color: #cbd5e1 transparent;
}

a       { color: var(--brand-teal); }
a:hover { color: var(--brand-dark); }

::selection {
    background: rgba(102, 178, 178, 0.3);
    color: #fff;
}

h2 {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 1.35rem;
}
```

#### Cards

```css
.card {
    border-radius: 1rem;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    box-shadow: 0 2px 8px rgba(0, 76, 76, 0.06), 0 1px 3px rgba(0, 0, 0, 0.04);
}

.card-header {
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, rgba(102, 178, 178, 0.04) 0%, transparent 60%);
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

/* Variantes d'accent */
.card-accent-teal { border-top: 3px solid var(--brand-teal); }
.card-accent-gold { border-left: 3px solid var(--brand-gold); }
```

#### Navbar (visible uniquement en mode standalone/iframe)

En mode embedded, la navbar du plugin est automatiquement supprimée par `extractParts`.

```css
.navbar {
    background: var(--brand-dark);
    border-bottom: 3px solid var(--brand-gold);   /* Ligne dorée signature */
    padding: 0.75rem 1rem;
}

.navbar-brand {
    color: #fff;
    font-weight: 700;
}

.navbar-brand span {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);   /* Sous-titre gris clair */
}
```

#### Boutons

```css
/* Primaire — fond canard profond */
.btn-primary {
    background-color: var(--brand-dark);
    border-color: var(--brand-dark);
    color: #fff;
    font-weight: 600;
}
.btn-primary:hover,
.btn-primary:focus-visible {
    background-color: #006666;
    border-color: #006666;
    box-shadow: 0 0 0 0.2rem rgba(102, 178, 178, 0.35);
}

/* Outline primary — bordure teal */
.btn-outline-primary {
    border-color: var(--brand-teal);
    color: var(--brand-dark);
    font-weight: 600;
    background: transparent;
}
.btn-outline-primary:hover {
    background: var(--brand-teal-light);
    border-color: var(--brand-teal);
    color: var(--brand-dark);
}

/* Outline secondary — bordure grise */
.btn-outline-secondary {
    border-color: #cbd5e1;
    color: var(--text-secondary);
    font-weight: 500;
}
.btn-outline-secondary:hover {
    background: var(--bg-card-alt);
    border-color: #cbd5e1;
    color: var(--text-primary);
}

/* Toggle actif (ex: filtres, toggles de mode) */
.btn-outline-secondary.active {
    background: var(--brand-teal-light);
    border-color: rgba(102, 178, 178, 0.5);
    color: var(--brand-dark);
}
```

#### Tables

```css
.table {
    color: var(--text-primary);              /* #0f172a quasi-noir */
    --bs-table-bg: transparent;
    background-color: transparent;
}

.table th {
    color: var(--text-secondary);            /* #475569 */
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    border-color: var(--border-color);
}

.table td {
    border-color: var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

/* Header gris bleuté */
.table thead tr {
    background: var(--bg-card-alt);          /* #f1f5f9 */
}

/* Zebra striping léger */
.table tbody tr:nth-child(even) { background: #f8fafc; }
.table tbody tr:nth-child(odd)  { background: #ffffff; }

/* Hover sur les lignes */
.table tbody tr:hover {
    background: var(--bg-card-alt);
}

/* En-têtes triables */
.sortable-th {
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
.sortable-th::after         { content: ' ⇅'; font-size: 0.7em; color: var(--text-muted); }
.sortable-th.sort-asc::after  { content: ' ▲'; color: var(--text-primary); }
.sortable-th.sort-desc::after { content: ' ▼'; color: var(--text-primary); }
.sortable-th:hover          { color: var(--text-primary); }

/* Cellule URL tronquée */
.bulk-url-cell {
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
```

#### Formulaires

```css
.form-label {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 600;
}

.form-control,
.form-select {
    border-color: var(--border-color);       /* #e2e8f0 */
    color: var(--text-primary);
}

.form-control::placeholder {
    color: var(--text-muted);                /* #94a3b8 */
    opacity: 1;
}

/* Focus ring teal */
.form-control:focus,
.form-select:focus,
.form-check-input:focus {
    border-color: var(--brand-teal);
    box-shadow: 0 0 0 0.2rem rgba(102, 178, 178, 0.25);
}

/* Checkbox/radio cochés */
.form-check-input:checked {
    background-color: var(--brand-teal);
    border-color: var(--brand-teal);
}

.form-check-label {
    color: var(--text-primary);
}

textarea {
    font-family: monospace;
}
```

#### Onglets / Tabs

```css
.nav-tabs {
    border-bottom: 1px solid var(--border-color);
}

.nav-tabs .nav-link {
    color: #64748b;
    font-weight: 600;
    font-size: 0.88rem;
    border-color: transparent;
    letter-spacing: 0.04em;
}

.nav-tabs .nav-link:hover {
    color: var(--text-primary);
    border-color: transparent;
}

.nav-tabs .nav-link.active {
    color: var(--brand-dark);
    background: transparent;
    border-bottom: 3px solid var(--brand-teal);   /* Underline teal signature */
    border-top-color: transparent;
    border-left-color: transparent;
    border-right-color: transparent;
}
```

#### Badges

```css
/* Badges de rôle */
.badge-role-admin {
    background: var(--brand-gold);
    color: var(--brand-dark);
    font-weight: 600;
}
.badge-role-user {
    background: var(--bg-card-alt);
    color: var(--text-secondary);
    font-weight: 500;
}

/* Badges de statut */
.badge-active {
    background: rgba(34, 197, 94, 0.12);
    color: var(--color-good);
    font-weight: 600;
}
.badge-inactive {
    background: rgba(239, 68, 68, 0.12);
    color: var(--color-bad);
    font-weight: 600;
}

/* Badge générique (métriques, versions) */
.badge {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

#### Alertes

```css
.alert-success {
    background: rgba(34, 197, 94, 0.08);
    border-color: rgba(34, 197, 94, 0.2);
    color: #166534;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.08);
    border-color: rgba(239, 68, 68, 0.25);
    color: var(--color-bad);
}

.alert-warning {
    background: var(--brand-gold-light);
    border-color: rgba(251, 176, 59, 0.35);
    color: #92690d;
}
```

#### Barres de progression

```css
.progress {
    background-color: var(--bg-card-alt);
    border-radius: 0.5rem;
}

.progress-bar-teal   { background-color: var(--brand-teal); }
.progress-bar-warn   { background-color: var(--color-warn); }
.progress-bar-danger { background-color: var(--color-bad); }
```

#### Pagination

```css
.bulk-pagination .page-link {
    background-color: #fff;
    border-color: var(--border-color);
    color: #64748b;
    font-size: 0.78rem;
    transition: background-color 0.2s, color 0.2s, border-color 0.2s;
}
.bulk-pagination .page-item.active .page-link {
    background-color: var(--brand-teal-light);
    border-color: rgba(102, 178, 178, 0.5);
    color: var(--brand-dark);
}
.bulk-pagination .page-item.disabled .page-link {
    background-color: #f8fafc;
    border-color: var(--border-color);
    color: #cbd5e1;
}
.bulk-pagination .page-link:hover {
    background-color: var(--bg-card-alt);
    color: var(--text-primary);
}
```

#### KPI Cards (métriques)

```css
.cwv-kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-left: 4px solid var(--border-color);  /* Coloré selon le statut */
    border-radius: 0.75rem;
    padding: 1rem;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
}

.cwv-kpi-card:hover {
    border-color: rgba(102, 178, 178, 0.5);
    box-shadow: 0 0 0 1px rgba(102, 178, 178, 0.25), 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.cwv-kpi-name {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.cwv-kpi-value {
    font-size: 1.75rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
```

#### Panneau d'aide contextuel

```css
.config-help-panel {
    background: var(--brand-linen);
    border: 1px solid var(--border-color);
    border-left: 3px solid var(--brand-gold);
    border-radius: 0.75rem;
    padding: 1rem;
    font-size: 0.82rem;
    color: var(--text-secondary);
    line-height: 1.55;
}

.config-help-panel .help-title {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--brand-dark);
}

.config-help-panel ul { padding-left: 1.1rem; margin-bottom: 0; }
.config-help-panel li { margin-bottom: 0.25rem; }
```

#### Scrollbars

```css
::-webkit-scrollbar       { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
```

### Principes de design

- **Fond clair** (`--bg-body` / `--brand-linen`) avec cards blanches — interface aérée
- **Header sombre** (`--brand-dark`) ancre visuellement la page
- **Ligne dorée** (`--brand-gold`) sous le header : signature visuelle, toujours `3px solid`
- **Focus ring teal** : tous les champs de formulaire ont un `box-shadow` teal au focus
- **Ombres douces** : jamais d'ombres lourdes, teinte teal subtile (`rgba(0, 76, 76, 0.06)`)
- **Coins arrondis** : `1rem` cards, `0.75rem` KPI/panneaux, `0.5rem` sidebar links/progress
- **Zebra striping** sur les tableaux : `#f8fafc` / `#ffffff` en alternance, hover `#f1f5f9`
- **Espacement généreux** : ne pas tasser les elements, `padding: 0.75rem 1rem` minimum
- **Couleurs sémantiques** : vert (`--color-good`) / orange (`--color-warn`) / rouge (`--color-bad`)
- **Transitions** : `0.15s–0.2s ease` sur hover/transform pour les éléments interactifs

---

## 9. Patterns JavaScript

### Flux PHP → JS

Le handler PHP prépare des données JSON, injectées dans `window.*` par `index.php` :

```php
<!-- Dans index.php -->
<script>
    window.cruxSeries    = <?= $jsSeries ?>;
    window.cruxSeriesP75 = <?= $jsSeriesP75 ?>;
    window.histogramBins = <?= $jsHistogramBins ?>;
</script>
<script src="app.js"></script>
```

```javascript
// Dans app.js
const series    = window.cruxSeries    || {};
const seriesP75 = window.cruxSeriesP75 || {};
```

### Chart.js

Les graphiques utilisent Chart.js via CDN. Pattern typique :

```javascript
function creerGraphique(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
            },
            scales: {
                y: { beginAtZero: true },
            },
        },
    });
}
```

### SSE / EventSource

Pour le streaming temps réel (routes de type `stream`) :

```javascript
function lancerBulk(params) {
    const url = window.MODULE_BASE_URL + '/bulk_stream.php?' + new URLSearchParams(params);
    const source = new EventSource(url);

    source.addEventListener('log', (e) => {
        const data = JSON.parse(e.data);
        afficherLog(data.message);
    });

    source.addEventListener('done', (e) => {
        const data = JSON.parse(e.data);
        source.close();
        afficherResultats(data);
    });

    source.onerror = () => {
        source.close();
        afficherErreur('Connexion interrompue');
    };
}
```

### `MODULE_BASE_URL`

En mode **embedded**, la plateforme injecte automatiquement :

```javascript
window.MODULE_BASE_URL = '/m/mon-slug';
```

Utiliser cette variable pour **tous** les appels AJAX et SSE vers les sous-routes du module :

```javascript
fetch(window.MODULE_BASE_URL + '/mon-endpoint.php', { method: 'POST', body: formData });
```

---

## 10. Modes d'affichage

### `embedded` (défaut)

Le HTML du plugin est **parsé** par `ModuleRenderer::extractParts()` :
- Le `<body>` est extrait et injecté dans le layout plateforme
- Les `<nav class="navbar">` sont supprimés (la plateforme a sa propre navbar)
- Les `<style>` et `<link rel="stylesheet">` locaux sont déplacés dans le `<head>`
- Les `<script>` locaux sont déplacés en fin de page
- Les chemins relatifs (`src="app.js"`, `href="styles.css"`) sont réécrits vers `/module-assets/{slug}/`
- Les liens CDN sont ignorés (déjà présents dans le layout)
- Un token CSRF est injecté dans chaque `<form method="POST">`
- La constante PHP `PLATFORM_EMBEDDED` est définie à `true`

### `iframe`

Le plugin tourne dans un `<iframe>` à l'intérieur du layout plateforme :
- La route `/m/{slug}` affiche le layout avec un `<iframe src="/m/{slug}/_app">`
- La route `/m/{slug}/_app` sert le HTML complet du plugin (standalone)
- Les sous-routes `/m/{slug}/styles.css`, `/m/{slug}/app.js` servent les assets statiques
- La constante PHP `PLATFORM_IFRAME` est définie à `true`
- Le plugin gère lui-même sa navbar, ses CDN, etc.

### `passthrough`

Le plugin gère **tout** — pas de layout plateforme, pas de parsing :
- Le fichier est exécuté directement, sa sortie est envoyée telle quelle au navigateur
- Utilisé pour les outils qui ont leur propre stack complète

### Détection du mode dans le plugin

```php
<?php
if (defined('PLATFORM_EMBEDDED') && PLATFORM_EMBEDDED) {
    // Mode embedded : pas besoin de <html>, <head>, navbar
}

if (defined('PLATFORM_IFRAME') && PLATFORM_IFRAME) {
    // Mode iframe : app autonome dans un iframe
}

// Ni l'un ni l'autre : mode standalone (développement local)
```

---

## 11. Variables d'environnement

### Déclaration dans `module.json`

```json
{
    "env_keys": ["CRUX_API_KEY", "AUTRE_CLE"]
}
```

### Fichier `.env` dans le répertoire du plugin

```
CRUX_API_KEY=AIzaSy...
AUTRE_CLE=xxx
```

### Chargement dans le code

```php
// En début de functions.php ou dans boot.php
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env') as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) {
            [$k, $v] = explode('=', trim($line), 2);
            putenv("$k=$v");
        }
    }
}

define('MA_CLE_API', getenv('MA_CLE_API') ?: '');
```

Le fichier `.env` est **local au plugin** et ne doit **pas** être commité. Il est chargé au moment de l'exécution du plugin (via `boot.php` ou en début de `functions.php`).

---

## 12. Constantes plateforme

| Constante | Type | Contexte | Description |
|-----------|------|----------|-------------|
| `PLATFORM_EMBEDDED` | PHP `define` | Mode embedded | Le plugin est intégré via extractParts |
| `PLATFORM_IFRAME` | PHP `define` | Mode iframe | Le plugin tourne dans un iframe |
| `window.MODULE_BASE_URL` | JS global | Mode embedded | Base URL pour les appels AJAX : `/m/{slug}` |
