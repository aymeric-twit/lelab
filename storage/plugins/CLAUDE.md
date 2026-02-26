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

### Variables obligatoires

```css
:root {
    /* Couleurs de la plateforme */
    --brand-teal:        #66b2b2;   /* Accents, bordures actives, liens hover */
    --brand-teal-light:  #e8f4f4;   /* Fonds légers, highlights */
    --brand-gold:        #fbb03b;   /* Bordure header, badges, attention */
    --brand-gold-light:  #fef4e0;   /* Fonds d'alerte doux */
    --brand-dark:        #004c4c;   /* Header, boutons primaires */
    --brand-linen:       #f2f2f2;   /* Background body */
    --brand-anthracite:  #333333;   /* Texte principal */

    /* Couleurs de statut CWV (si pertinent) */
    --cwv-good: #22c55e;
    --cwv-ni:   #f97316;
    --cwv-poor: #ef4444;
}
```

### Composants

#### Body & typographie

```css
body {
    background: var(--brand-linen);
    color: var(--brand-anthracite);
    font-family: 'Poppins', system-ui, -apple-system, sans-serif;
}
```

#### Cards

```css
.card {
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 76, 76, 0.06), 0 1px 3px rgba(0, 0, 0, 0.04);
}
.card-header {
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, rgba(102, 178, 178, 0.04) 0%, transparent 60%);
}
```

#### Navbar (visible uniquement en mode standalone/iframe)

```css
.navbar {
    background: var(--brand-dark);
    padding: 0.75rem 0;
    border-bottom: 2.5px solid var(--brand-gold);
}
.navbar-brand {
    color: #fff !important;
    font-weight: 700;
}
```

#### Boutons

```css
.btn-primary {
    background: var(--brand-dark);
    border-color: var(--brand-dark);
    border-radius: 6px;
}
.btn-primary:hover {
    background: var(--brand-teal);
    border-color: var(--brand-teal);
}
```

#### Tables

```css
table { font-size: 16px; }
thead { background: #f1f5f9; }
/* Texte quasi-noir pour la lisibilité */
td, th { color: #0f172a; }
```

#### KPI Cards

```css
.kpi-card {
    text-align: center;
    padding: 1rem;
}
.kpi-card .kpi-value {
    font-size: 1.8rem;
    font-weight: 700;
}
/* Couleurs selon le statut */
.kpi-card.good .kpi-value { color: var(--cwv-good); }
.kpi-card.ni .kpi-value   { color: var(--cwv-ni); }
.kpi-card.poor .kpi-value { color: var(--cwv-poor); }
```

#### Badges

```css
.badge {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

### Principes de design

- **Fond clair** (`--brand-linen`) avec cards blanches
- **Header sombre** (`--brand-dark`) ancre visuellement la page
- **Ligne dorée** (`--brand-gold`) sous le header : signature visuelle obligatoire
- **Ombres douces** : jamais d'ombres lourdes, teinte teal subtile
- **Coins arrondis** : `1rem` cards, `6px` boutons, `4px` inputs
- **Espacement généreux** : ne pas tasser les elements

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
