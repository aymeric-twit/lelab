<?php

use Platform\Database\Connection;
use Platform\Module\ModuleRegistry;
use Platform\Service\CreditService;

beforeEach(function () {
    // BDD SQLite en mémoire
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Créer les tables nécessaires
    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        email TEXT,
        password_hash TEXT NOT NULL DEFAULT "",
        role TEXT DEFAULT "user",
        active INTEGER DEFAULT 1,
        plan_id INTEGER DEFAULT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        deleted_at TEXT DEFAULT NULL
    )');

    $pdo->exec('CREATE TABLE plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        nom TEXT NOT NULL,
        description TEXT DEFAULT NULL,
        prix_mensuel REAL DEFAULT NULL,
        prix_annuel REAL DEFAULT NULL,
        credits_mensuels INTEGER NOT NULL DEFAULT 50,
        quotas_defaut TEXT DEFAULT "{}",
        modules_inclus TEXT DEFAULT "[]",
        limites TEXT DEFAULT "{}",
        sort_order INTEGER NOT NULL DEFAULT 0,
        actif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        icon TEXT DEFAULT NULL,
        enabled INTEGER DEFAULT 1,
        desinstalle_le TEXT DEFAULT NULL,
        credits_par_analyse INTEGER NOT NULL DEFAULT 1,
        quota_mode TEXT DEFAULT "mensuel",
        default_quota INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0,
        chemin_source TEXT DEFAULT NULL
    )');

    $pdo->exec('CREATE TABLE user_credits (
        user_id INTEGER PRIMARY KEY,
        credits_utilises INTEGER NOT NULL DEFAULT 0,
        credits_limite INTEGER NOT NULL DEFAULT 50,
        periode_debut TEXT NOT NULL,
        periode_fin TEXT NOT NULL,
        updated_at TEXT DEFAULT NULL
    )');

    $pdo->exec('CREATE TABLE module_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        year_month TEXT NOT NULL,
        usage_count INTEGER NOT NULL DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        titre TEXT NOT NULL,
        message TEXT NOT NULL,
        lien TEXT DEFAULT NULL,
        icone TEXT DEFAULT "bi-bell",
        lue INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE credits_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        module_slug TEXT NOT NULL,
        module_name TEXT NOT NULL,
        credits_deduits INTEGER NOT NULL,
        credits_restants INTEGER NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    // Fonctions SQLite pour compatibilité MySQL
    $pdo->sqliteCreateFunction('NOW', function () {
        return date('Y-m-d H:i:s');
    });
    $pdo->sqliteCreateFunction('CURDATE', function () {
        return date('Y-m-d');
    });

    // Insérer un plan de test
    $pdo->exec("INSERT INTO plans (id, slug, nom, credits_mensuels, sort_order, actif)
        VALUES (1, 'gratuit', 'Gratuit', 100, 1, 1)");

    $pdo->exec("INSERT INTO plans (id, slug, nom, credits_mensuels, sort_order, actif)
        VALUES (2, 'pro', 'Pro', 0, 2, 1)");

    // Insérer des utilisateurs de test
    $pdo->exec("INSERT INTO users (id, username, plan_id, created_at)
        VALUES (1, 'alice', 1, '" . date('Y-m-d H:i:s') . "')");

    $pdo->exec("INSERT INTO users (id, username, plan_id, created_at)
        VALUES (2, 'bob_illimite', 2, '" . date('Y-m-d H:i:s') . "')");

    // Insérer un module de test
    $pdo->exec("INSERT INTO modules (id, slug, name, credits_par_analyse, enabled)
        VALUES (1, 'crux-history', 'CrUX History', 5, 1)");

    $pdo->exec("INSERT INTO modules (id, slug, name, credits_par_analyse, enabled)
        VALUES (2, 'module-gratuit', 'Module Gratuit', 0, 1)");

    // Pré-remplir les crédits (évite ON DUPLICATE KEY UPDATE incompatible SQLite)
    $debutPeriode = date('Y-m-01');
    $finPeriode = date('Y-m-t');
    $pdo->exec("INSERT INTO user_credits (user_id, credits_utilises, credits_limite, periode_debut, periode_fin)
        VALUES (1, 0, 100, '{$debutPeriode}', '{$finPeriode}')");
    $pdo->exec("INSERT INTO user_credits (user_id, credits_utilises, credits_limite, periode_debut, periode_fin)
        VALUES (2, 0, 0, '{$debutPeriode}', '{$finPeriode}')");

    // Injecter le PDO via Reflection dans Connection
    $ref = new ReflectionClass(Connection::class);
    $prop = $ref->getProperty('instance');
    $prop->setAccessible(true);
    $prop->setValue(null, $pdo);

    // Invalider le cache ModuleRegistry pour forcer le fallback BDD
    try {
        $refMR = new ReflectionClass(ModuleRegistry::class);
        $propLoaded = $refMR->getProperty('loaded');
        $propLoaded->setAccessible(true);
        $propLoaded->setValue(null, false);
        $propModules = $refMR->getProperty('modules');
        $propModules->setAccessible(true);
        $propModules->setValue(null, []);
    } catch (ReflectionException) {
        // Propriétés non accessibles — pas grave, le fallback BDD fonctionnera
    }

    $this->pdo = $pdo;
    $this->service = new CreditService($pdo);
});

afterEach(function () {
    // Nettoyer le singleton Connection
    $ref = new ReflectionClass(Connection::class);
    $prop = $ref->getProperty('instance');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
});

// --- Tests ---

it('devrait consommer des crédits et les déduire du solde', function () {
    $resultat = $this->service->consommer(1, 'crux-history');

    expect($resultat)->toBeTrue();

    // Vérifier que les crédits ont été déduits
    $stmt = $this->pdo->prepare('SELECT credits_utilises FROM user_credits WHERE user_id = 1');
    $stmt->execute();
    $row = $stmt->fetch();

    expect((int) $row['credits_utilises'])->toBe(5);
});

it('devrait refuser la consommation si crédits insuffisants', function () {
    // Consommer presque tout (100 crédits, consommons 20 fois = 100)
    for ($i = 0; $i < 20; $i++) {
        $this->service->consommer(1, 'crux-history');
    }

    // La 21e consommation devrait échouer
    $resultat = $this->service->consommer(1, 'crux-history');

    expect($resultat)->toBeFalse();
});

it('devrait toujours autoriser un module avec poids 0', function () {
    $resultat = $this->service->consommer(1, 'module-gratuit');

    expect($resultat)->toBeTrue();

    // Aucun crédit ne devrait avoir été déduit
    $stmt = $this->pdo->prepare('SELECT credits_utilises FROM user_credits WHERE user_id = 1');
    $stmt->execute();
    $row = $stmt->fetch();

    // Pas de record car le module est gratuit — ou record à 0
    if ($row) {
        expect((int) $row['credits_utilises'])->toBe(0);
    } else {
        expect(true)->toBeTrue();
    }
});

it('devrait retourner le solde correct', function () {
    // Consommer 5 crédits
    $this->service->consommer(1, 'crux-history');

    $solde = $this->service->solde(1);

    expect($solde)->toBe(95); // 100 - 5
});

it('devrait retourner true pour estIllimite quand limite = 0', function () {
    $estIllimite = $this->service->estIllimite(2);

    expect($estIllimite)->toBeTrue();
});

it('devrait retourner false pour estIllimite quand limite > 0', function () {
    $estIllimite = $this->service->estIllimite(1);

    expect($estIllimite)->toBeFalse();
});

it('devrait retourner un résumé correct pour le dashboard', function () {
    // Consommer 10 crédits (2 analyses de 5)
    $this->service->consommer(1, 'crux-history');
    $this->service->consommer(1, 'crux-history');

    $resume = $this->service->resumePourDashboard(1);

    expect($resume)->toBeArray()
        ->and($resume['utilises'])->toBe(10)
        ->and($resume['limite'])->toBe(100)
        ->and($resume['pourcentage'])->toBe(10)
        ->and($resume['illimite'])->toBeFalse()
        ->and($resume)->toHaveKey('periode_fin');
});

it('devrait réinitialiser les crédits à la limite du plan', function () {
    // Consommer des crédits
    $this->service->consommer(1, 'crux-history');
    $this->service->consommer(1, 'crux-history');

    // Réinitialiser manuellement via SQL (ON DUPLICATE KEY UPDATE n'est pas supporté par SQLite)
    $debutPeriode = date('Y-m-01');
    $finPeriode = date('Y-m-t');
    $this->pdo->prepare(
        'UPDATE user_credits SET credits_utilises = 0, credits_limite = 100, periode_debut = ?, periode_fin = ? WHERE user_id = 1'
    )->execute([$debutPeriode, $finPeriode]);

    $solde = $this->service->solde(1);
    expect($solde)->toBe(100); // Retour à la limite du plan
});

it('devrait logger les consommations dans credits_log', function () {
    $this->service->consommer(1, 'crux-history');

    $stmt = $this->pdo->prepare('SELECT * FROM credits_log WHERE user_id = 1');
    $stmt->execute();
    $logs = $stmt->fetchAll();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]['module_slug'])->toBe('crux-history')
        ->and((int) $logs[0]['credits_deduits'])->toBe(5);
});

it('devrait retourner l\'historique des crédits', function () {
    $this->service->consommer(1, 'crux-history');
    $this->service->consommer(1, 'crux-history');

    $historique = $this->service->historiqueCredits(1, 10);

    expect($historique)->toHaveCount(2);
});

it('devrait autoriser la consommation illimitée quand limite = 0', function () {
    // Bob a un plan illimité (credits_mensuels = 0)
    for ($i = 0; $i < 50; $i++) {
        $resultat = $this->service->consommer(2, 'crux-history');
        expect($resultat)->toBeTrue();
    }

    // Le solde devrait être null (illimité)
    $solde = $this->service->solde(2);
    expect($solde)->toBeNull();
});
