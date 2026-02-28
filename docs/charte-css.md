# Charte graphique CSS — Reference complete

Tous les plugins suivent la meme charte visuelle. Ce fichier constitue le socle obligatoire de `styles.css`.

## Variables CSS obligatoires

```css
:root {
    /* Couleurs brand */
    --brand-dark:        #004c4c;
    --brand-teal:        #66b2b2;
    --brand-teal-light:  #e8f4f4;
    --brand-gold:        #fbb03b;
    --brand-gold-light:  #fef4e0;
    --brand-linen:       #f2f2f2;
    --brand-anthracite:  #333333;

    /* Couleurs semantiques */
    --border-color:      #e2e8f0;
    --bg-card-alt:       #f1f5f9;
    --text-primary:      #0f172a;
    --text-secondary:    #475569;
    --text-muted:        #94a3b8;

    /* Couleurs de statut */
    --score-high:        #22c55e;
    --score-mid:         #f97316;
    --score-low:         #ef4444;
}
```

## Body et typographie

```css
html { scroll-behavior: smooth; }
body {
    background: var(--brand-linen);
    min-height: 100vh;
    font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    color: var(--brand-anthracite);
    font-size: 16px;
    line-height: 1.6;
}
::selection { background: rgba(102, 178, 178, 0.3); color: #fff; }

/* Tailles de police de reference :
   0.72rem — micro-badges
   0.75rem — badges, petits labels
   0.8rem  — sous-titres navbar, aide formulaire
   0.82rem — cellules tableau, texte compact
   0.85rem — labels formulaire, sous-titres
   0.9rem  — boutons, petits headings
   1rem    — texte normal
   1.25rem — navbar brand
   1.75rem — valeurs KPI
   2rem    — grandes valeurs KPI
*/
```

## Navbar (standalone — supprimee en embedded)

```css
.navbar {
    background: var(--brand-dark);
    border-bottom: 3px solid var(--brand-gold);
    padding: 0.75rem 0;
}
.navbar-brand { color: #fff !important; font-weight: 700; font-size: 1.25rem; }
.navbar-brand span { font-size: 0.8rem; color: rgba(255,255,255,0.6); font-weight: 400; }
```

## Cards

```css
.card {
    background: #fff;
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    box-shadow: 0 2px 8px rgba(0, 76, 76, 0.06), 0 1px 3px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}
.card-header {
    background: linear-gradient(135deg, rgba(102, 178, 178, 0.04) 0%, transparent 60%);
    border-bottom: 1px solid var(--border-color);
    padding: 0.75rem 1.25rem;
}
.card-header h6 { color: var(--brand-anthracite); font-weight: 700; }
.card-body { padding: 1.25rem; }
```

## Boutons

```css
.btn-primary {
    background: var(--brand-dark);
    border-color: var(--brand-dark);
    border-radius: 6px;
    font-weight: 600;
    color: #fff;
}
.btn-primary:hover, .btn-primary:focus-visible {
    background: #006666;
    border-color: #006666;
    box-shadow: 0 0 0 0.2rem rgba(102, 178, 178, 0.35);
}
.btn-outline-secondary {
    border-color: var(--border-color);
    color: var(--text-secondary);
}
.btn-outline-secondary:hover {
    background: var(--bg-card-alt);
    color: var(--text-primary);
}
```

## Formulaires

```css
.form-label {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.35rem;
}
.form-control, .form-select {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: var(--brand-teal);
    box-shadow: 0 0 0 0.2rem rgba(102, 178, 178, 0.25);
}
.form-check-input:checked {
    background-color: var(--brand-dark);
    border-color: var(--brand-dark);
}
textarea.form-control { resize: vertical; min-height: 80px; }
```

## Tableaux

```css
.table {
    --bs-table-bg: transparent;
    color: var(--text-primary);
    border-color: var(--border-color);
    font-size: 14px;
    margin-bottom: 0;
}
.table thead th {
    background: var(--bg-card-alt);
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    border-bottom: 2px solid var(--border-color);
    padding: 0.75rem 1rem;
}
.table tbody td {
    padding: 0.65rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
}
.table tbody tr:nth-child(odd)  { background: #fff; }
.table tbody tr:nth-child(even) { background: #f8fafc; }
.table tbody tr:hover { background: var(--brand-teal-light); }
```

## Colonnes triables

```css
.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 1.2em !important;
}
.sortable:hover { background: #e8ecf1; }
.sortable::after {
    content: '⇅';
    position: absolute;
    right: 0.4em;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.7rem;
    opacity: 0.4;
}
.sortable.sort-asc::after { content: '▲'; opacity: 0.8; color: var(--brand-dark); }
.sortable.sort-desc::after { content: '▼'; opacity: 0.8; color: var(--brand-dark); }
```

## Badges de statut

```css
/* Succes (vert) */
.badge-succes {
    background: #d1fae5;
    color: #065f46;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.35em 0.75em;
    border-radius: 4px;
}
/* Attention (orange/jaune) */
.badge-attention {
    background: #fef3c7;
    color: #92400e;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.35em 0.75em;
    border-radius: 4px;
}
/* Erreur (rouge) */
.badge-erreur {
    background: #fee2e2;
    color: #991b1b;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.35em 0.75em;
    border-radius: 4px;
}
/* Pour les scores avec uppercase */
.badge-score {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

## KPI Cards

```css
.kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}
.kpi-card {
    background: #fff;
    border-radius: 0.75rem;
    border-left: 4px solid var(--brand-teal);
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 4px rgba(0, 76, 76, 0.08);
    transition: transform 0.15s;
}
.kpi-card:hover { transform: translateY(-2px); }
.kpi-card.kpi-green  { border-left-color: var(--score-high); }
.kpi-card.kpi-red    { border-left-color: var(--score-low); }
.kpi-card.kpi-gold   { border-left-color: var(--brand-gold); }
.kpi-card.kpi-dark   { border-left-color: var(--brand-dark); }
.kpi-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--text-primary);
}
.kpi-label {
    font-size: 0.82rem;
    color: var(--text-secondary);
    font-weight: 600;
    margin-top: 0.15rem;
}
.kpi-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.1rem;
}
```

## Onglets / Tabs

```css
.nav-tabs {
    border-bottom: 2px solid var(--border-color);
}
.nav-tabs .nav-link {
    color: var(--text-secondary);
    font-weight: 500;
    border: none;
    border-bottom: 2.5px solid transparent;
    border-radius: 6px 6px 0 0;
    padding: 0.5rem 1rem;
    opacity: 0.65;
    transition: all 0.2s;
}
.nav-tabs .nav-link:hover {
    opacity: 1;
    color: var(--brand-dark);
    border-bottom-color: var(--brand-teal-light);
}
.nav-tabs .nav-link.active {
    color: var(--brand-dark);
    font-weight: 600;
    border-bottom-color: var(--brand-teal);
    opacity: 1;
    background: transparent;
}
```

## Barre de progression

```css
.progress {
    background-color: #e2e8f0;
    height: 1.2rem;
    border-radius: 0.5rem;
}
.progress-bar { background-color: var(--brand-teal); }
```

## Status feedback

```css
.status-msg {
    padding: 0.4rem 0.75rem;
    border-radius: 4px;
    font-size: 0.82rem;
}
.status-msg.status-loading { background: var(--brand-teal-light); color: var(--brand-dark); }
.status-msg.status-success  { background: #d1fae5; color: #065f46; }
.status-msg.status-error    { background: #fee2e2; color: #991b1b; }
.status-msg.status-warning  { background: var(--brand-gold-light); color: #92400e; }
```

## Alertes

```css
.alert-config {
    background: var(--brand-gold-light);
    border: 1px solid rgba(251, 176, 59, 0.35);
    color: #92690d;
    border-radius: 0.5rem;
}
.alert-config code {
    color: var(--brand-dark);
    background: var(--brand-teal-light);
    padding: 0.15em 0.35em;
    border-radius: 0.25rem;
}
```

## Pagination

```css
.pg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 30px;
    padding: 0 0.4em;
    border: 1px solid var(--border-color);
    border-radius: 0.375rem;
    background: #fff;
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.pg-btn:hover:not(:disabled) {
    background: var(--brand-teal-light);
    border-color: var(--brand-teal);
    color: var(--brand-dark);
}
.pg-btn.pg-active {
    background: var(--brand-dark);
    border-color: var(--brand-dark);
    color: #fff;
}
.pg-btn:disabled { opacity: 0.35; cursor: default; }
```

## Scrollbar et liens

```css
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

a { color: var(--brand-teal); }
a:hover { color: var(--brand-dark); }
```

## Responsive

```css
@media (max-width: 768px) {
    .kpi-row { grid-template-columns: repeat(2, 1fr); }
    .table { font-size: 13px; }
}
@media (max-width: 480px) {
    .kpi-row { grid-template-columns: 1fr; }
}
```
