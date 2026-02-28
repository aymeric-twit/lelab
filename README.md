# SEO Platform

> A modular PHP platform that unifies SEO tools (plugins) under a single authenticated interface.

Plateforme PHP modulaire qui regroupe des outils SEO (plugins) au sein d'une interface unique avec authentification, gestion des droits et quotas par utilisateur.

---

## Fonctionnalites

- **Systeme de plugins** : 3 modes d'affichage (embedded, iframe, passthrough) avec detection automatique des assets et injection dans le layout.
- **Authentification** : sessions PHP avec hachage de mot de passe, middleware `RequireAuth`.
- **Gestion des droits** : controle d'acces par utilisateur et par plugin, roles administrateur/utilisateur.
- **Quotas** : limitation mensuelle par utilisateur avec 4 modes (`none`, `request`, `form_submit`, `api_call`).
- **Administration** : CRUD utilisateurs, gestion des plugins (installation via Git ou ZIP), categories, surcharges de quotas.
- **Securite** : protection CSRF sur tous les formulaires et appels AJAX, middleware `VerifyCsrf`.
- **Audit** : journalisation des actions utilisateur via le systeme d'audit.
- **Installation de plugins** : installation par URL Git ou upload ZIP, mise a jour automatique via webhook GitHub.
- **Internationalisation** : support multilingue par plugin (systeme `translations.js` + fonction `t()`).
- **CLI** : scaffolding de plugins et migrations de base de donnees.

---

## Prerequis

- **PHP** >= 8.1
- **Composer** >= 2.0
- **MySQL** ou **MariaDB** (SQLite pour le developpement local)
- Extensions PHP : `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`

---

## Installation

```bash
# 1. Cloner le depot
git clone <url-du-depot> seo-platform
cd seo-platform

# 2. Installer les dependances
composer install

# 3. Configurer l'environnement
cp .env.example .env
# Editer .env avec vos parametres :
#   APP_NAME, APP_URL, APP_ENV
#   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
#   + cles API specifiques aux plugins (CRUX_API_KEY, GOOGLE_KG_API_KEY, etc.)

# 4. Executer les migrations
php database/migrate.php

# 5. Charger les donnees initiales
mysql -u $DB_USER -p $DB_NAME < database/seed.sql

# 6. Lancer le serveur de developpement
php -S localhost:8000 -t public/ public/router.php
```

L'application est accessible a `http://localhost:8000`.

---

## Systeme de plugins

Chaque plugin est un repertoire autonome avec un fichier `module.json` qui declare ses metadonnees, son mode d'affichage, ses quotas et ses sous-routes.

### Modes d'affichage

| Mode | Comportement | Cas d'usage |
|------|-------------|-------------|
| **embedded** | La plateforme extrait le `<body>`, les CSS/JS locaux et les injecte dans son propre layout. Les CDN communs sont ignores (deja presents). | Plugins simples, formulaires AJAX |
| **iframe** | Le plugin est charge dans un `<iframe>`. Le HTML complet est servi tel quel. | Applications complexes avec routeur propre |
| **passthrough** | Aucun layout applique, le plugin controle tout. | OAuth, API REST, adaptateurs externes |

### Quotas

| Mode | Declenchement |
|------|--------------|
| `none` | Pas de limitation |
| `request` | Incremente a chaque chargement de page |
| `form_submit` | Incremente sur `POST` uniquement |
| `api_call` | Le plugin appelle `Quota::trackerSiDisponible()` explicitement |

Les trois premiers modes sont geres automatiquement par le middleware `CheckModuleQuota`. Le mode `api_call` est gere par le plugin lui-meme.

### Sous-routes

Les sous-routes sont declarees dans `module.json` et accessibles a `/m/{slug}/{path}` :

| Type | Layout | Usage |
|------|--------|-------|
| `page` | Oui (extraction HTML) | Pages supplementaires dans le layout |
| `ajax` | Non (passthrough) | Endpoints JSON |
| `stream` | Non (passthrough) | Server-Sent Events (SSE) |

---

## Administration

L'interface d'administration (`/admin`) est reservee aux utilisateurs avec le role `admin` (middleware `RequireAdmin`).

- **Utilisateurs** : creation, modification, suppression douce, activation/desactivation.
- **Controle d'acces** : attribution des plugins accessibles par utilisateur.
- **Quotas** : surcharge de la limite mensuelle par utilisateur et par plugin.
- **Categories** : organisation des plugins dans la sidebar.
- **Plugins** : installation via URL Git ou upload ZIP, mise a jour, configuration des variables d'environnement, gestion de l'ordre d'affichage.

---

## Plugins disponibles

| Plugin | Slug | Mode | Description |
|--------|------|------|-------------|
| Suggest Checker | `suggest` | embedded | Verification de la presence de mots-cles dans Google Suggest |
| CrUX History | `crux-history` | iframe | Visualisation des Core Web Vitals via l'API CrUX History |
| KG Entity Audit | `kg-entities` | embedded | Audit des entites Knowledge Graph et JSON-LD |
| KWCible | `kwcible` | embedded | Analyse semantique d'une page vs mot-cle cible |
| Keyword Forge | `keywords-forge` | embedded | Generation et validation de mots-cles via templates |
| HTAccess Cleaner | `htaccess-cleaner` | embedded | Analyse des regles .htaccess vs trafic reel |
| Cache Warmer | `cache-maker` | iframe | Pre-chauffage du cache HTTP avec apprentissage TTL |
| Analyseur Facettes | `facettes` | iframe | Qualification des facettes e-commerce pour l'indexation |
| Robots.txt Checker | `robotstxt-checker` | embedded | Test allow/block de robots.txt multi-User-Agent |
| Search Console | `search-console` | passthrough | Dashboard Google Search Console avec sync OAuth2 |
| URL Organizer | `url-organizer` | embedded | Categorisation d'URLs GSC par pattern et topologie |

---

## CLI

```bash
# Scaffolding d'un nouveau plugin
php cli/make-module.php <slug>

# Executer les migrations
php database/migrate.php
```

Le script `make-module.php` genere la structure minimale d'un plugin : `module.json`, `boot.php`, `index.php`, `process.php`, `styles.css`, `app.js` et `.gitignore`.

---

## Stack technique

| Composant | Technologie |
|-----------|------------|
| Langage | PHP >= 8.1 |
| Namespace | `Platform\` (PSR-4 dans `core/`) |
| Front controller | `public/index.php` avec `Platform\Router` |
| Authentification | Sessions PHP, hachage bcrypt |
| Base de donnees | MySQL / MariaDB (PDO) |
| Migrations | 14 fichiers SQL dans `database/migrations/` |
| Templates | PHP natif (`templates/`) |
| CSS | Bootstrap 5.3.3, charte graphique custom |
| Icones | Bootstrap Icons 1.11.3 |
| Typographie | Poppins (Google Fonts) |
| Tests | Pest 3.0 |
| Analyse statique | PHPStan 2.0 |
| Environnement | vlucas/phpdotenv 5.6 |

### Enums PHP

| Enum | Fichier | Valeurs |
|------|---------|---------|
| `AuditAction` | `core/Enum/AuditAction.php` | Actions journalisees |
| `ModeAffichage` | `core/Enum/ModeAffichage.php` | `embedded`, `iframe`, `passthrough` |
| `QuotaMode` | `core/Enum/QuotaMode.php` | `none`, `request`, `form_submit`, `api_call` |
| `Role` | `core/Enum/Role.php` | Roles utilisateur (`admin`, `user`) |
| `RouteType` | `core/Enum/RouteType.php` | `page`, `ajax`, `stream` |

### Middlewares

| Middleware | Role |
|-----------|------|
| `RequireAuth` | Bloque les utilisateurs non connectes |
| `RequireAdmin` | Restreint l'acces aux administrateurs |
| `VerifyCsrf` | Verifie le jeton CSRF sur les requetes POST |
| `CheckModuleQuota` | Applique les limites de quota par plugin |

---

## Structure du projet

```
seo-platform/
├── cli/                        # Outils CLI
│   └── make-module.php         #   Scaffolding de plugin
├── composer.json
├── config/                     # Configuration
│   ├── app.php                 #   Parametres application
│   └── database.php            #   Connexion base de donnees
├── core/                       # Code source (namespace Platform\)
│   ├── App.php                 #   Bootstrap de l'application
│   ├── Auth/                   #   Authentification et sessions
│   ├── Controller/             #   Controleurs HTTP
│   ├── Database/               #   Connexion et requetes PDO
│   ├── Enum/                   #   Enums PHP (Role, QuotaMode, etc.)
│   ├── Http/                   #   Request, Response, middlewares
│   ├── Log/                    #   Journalisation et audit
│   ├── Module/                 #   Chargement et rendu des plugins
│   ├── Router.php              #   Routeur principal
│   ├── Service/                #   Services metier (quotas, etc.)
│   ├── User/                   #   Gestion des utilisateurs
│   ├── Validation/             #   Regles de validation
│   └── View/                   #   Moteur de templates
├── database/                   # Base de donnees
│   ├── migrate.php             #   Script de migration
│   ├── migrations/             #   14 fichiers SQL incrementaux
│   └── seed.sql                #   Donnees initiales
├── docs/                       # Documentation developpeur
│   ├── charte-css.md           #   Variables CSS, composants, responsive
│   ├── i18n.md                 #   Internationalisation (translations.js, t())
│   ├── patterns-js.md          #   Patterns JS (AJAX, SSE, polling, export)
│   ├── patterns-php.md         #   Patterns PHP (process, progress, worker)
│   └── template-html.md        #   Template HTML embedded + redirections
├── modules/                    # Plugins installes
│   └── _template/              #   Template de base pour nouveaux plugins
├── public/                     # Racine web
│   ├── assets/                 #   CSS/JS de la plateforme
│   ├── index.php               #   Front controller
│   ├── module-assets/          #   Symlinks vers les assets des plugins
│   ├── robots.txt
│   └── router.php              #   Routeur pour le serveur PHP integre
├── storage/                    # Fichiers generes (logs, sessions)
├── templates/                  # Templates PHP
│   ├── admin/                  #   Interface d'administration
│   ├── dashboard.php           #   Tableau de bord
│   ├── layout.php              #   Layout principal
│   ├── login.php               #   Page de connexion
│   └── quota-exceeded.php      #   Page quota depasse
├── tests/                      # Tests Pest
│   ├── Feature/                #   Tests fonctionnels
│   ├── Pest.php                #   Configuration Pest
│   └── Unit/                   #   Tests unitaires
└── vendor/                     # Dependances Composer
```

---

## Documentation developpeur

Le repertoire `docs/` contient la documentation de reference pour le developpement de plugins :

| Fichier | Contenu |
|---------|---------|
| `docs/charte-css.md` | Variables CSS (`--brand-*`), composants (cards, boutons, onglets), responsive design |
| `docs/patterns-js.md` | Patterns JavaScript : AJAX avec CSRF, Server-Sent Events, polling de progression, tri de tableaux, export CSV |
| `docs/patterns-php.md` | Patterns PHP : `process.php`, `progress.php`, SSE, worker CLI, gestion des quotas |
| `docs/template-html.md` | Template HTML pour plugins embedded, gestion des redirections |
| `docs/i18n.md` | Systeme d'internationalisation : `translations.js`, fonction `t()`, declaration des langues |

---

## Variables d'environnement

```env
# Application
APP_NAME="SEO Platform"
APP_URL=http://localhost:8000
APP_ENV=local

# Base de donnees
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=seo_platform
DB_USER=root
DB_PASSWORD=

# Plugins (selon les plugins installes)
CRUX_API_KEY=
GOOGLE_KG_API_KEY=
SEMRUSH_API_KEY=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

---

## Tests

```bash
# Lancer tous les tests
./vendor/bin/pest

# Filtrer par nom
./vendor/bin/pest --filter=quota

# Analyse statique
./vendor/bin/phpstan analyse
```

---

## Licence

Projet prive — tous droits reserves.
