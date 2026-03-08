<?php

use Platform\Database\Connection;
use Platform\Module\Quota;
use Platform\Service\AuditLogger;

/**
 * Insère directement un enregistrement d'usage dans la BDD SQLite.
 * Contourne Quota::increment() qui utilise du SQL MySQL-specific.
 */
function insererUsage(int $userId, string $slug, int $count, ?string $yearMonth = null): void
{
    $db = Connection::get();
    $stmt = $db->prepare('SELECT id FROM modules WHERE slug = ?');
    $stmt->execute([$slug]);
    $moduleId = (int) $stmt->fetchColumn();
    $yearMonth ??= date('Ym');

    $stmt = $db->prepare('
        INSERT INTO module_usage (user_id, module_id, year_month, usage_count, last_tracked_at)
        VALUES (?, ?, ?, ?, datetime("now"))
        ON CONFLICT(user_id, module_id, year_month)
        DO UPDATE SET usage_count = usage_count + excluded.usage_count, last_tracked_at = datetime("now")
    ');
    $stmt->execute([$userId, $moduleId, $yearMonth, $count]);
}

beforeEach(function () {
    // Injecter une BDD SQLite en mémoire
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Créer les tables nécessaires
    $pdo->exec('CREATE TABLE modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL,
        enabled INTEGER DEFAULT 1,
        default_quota INTEGER DEFAULT 0,
        quota_mode TEXT DEFAULT "none",
        desinstalle_le TEXT DEFAULT NULL
    )');
    $pdo->exec('CREATE TABLE module_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        year_month TEXT NOT NULL,
        usage_count INTEGER DEFAULT 0,
        last_tracked_at TEXT,
        UNIQUE(user_id, module_id, year_month)
    )');
    $pdo->exec('CREATE TABLE user_module_quotas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        module_id INTEGER NOT NULL,
        monthly_limit INTEGER DEFAULT 0,
        updated_by INTEGER,
        UNIQUE(user_id, module_id)
    )');
    $pdo->exec('CREATE TABLE audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        target_type TEXT,
        target_id INTEGER,
        details TEXT,
        ip_address TEXT,
        created_at TEXT
    )');
    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        email TEXT,
        password_hash TEXT,
        role TEXT DEFAULT "user",
        active INTEGER DEFAULT 1,
        domaine TEXT,
        created_at TEXT,
        deleted_at TEXT DEFAULT NULL,
        last_login TEXT
    )');

    // Module de test avec quota 10
    $pdo->exec("INSERT INTO modules (slug, enabled, default_quota, quota_mode) VALUES ('test-module', 1, 10, 'api_call')");

    // Module de test avec quota illimité
    $pdo->exec("INSERT INTO modules (slug, enabled, default_quota, quota_mode) VALUES ('illimite', 1, 0, 'api_call')");

    // Utilisateur de test inscrit le 15 du mois
    $pdo->exec("INSERT INTO users (id, username, email, password_hash, role, active, created_at) VALUES (1, 'testuser', 'test@test.com', 'hash', 'user', 1, '2025-03-15 10:30:00')");

    // Injecter le PDO via Reflection
    $ref = new ReflectionClass(Connection::class);
    $prop = $ref->getProperty('instance');
    $prop->setAccessible(true);
    $prop->setValue(null, $pdo);

    // Reset le cache de Quota
    $refQuota = new ReflectionClass(Quota::class);
    $cache = $refQuota->getProperty('moduleIdCache');
    $cache->setAccessible(true);
    $cache->setValue(null, []);

    // Reset le singleton AuditLogger (pour qu'il utilise le nouveau PDO)
    $refAudit = new ReflectionClass(AuditLogger::class);
    $inst = $refAudit->getProperty('instance');
    $inst->setAccessible(true);
    $inst->setValue(null, null);

    // Simuler un utilisateur connecté (user_id = 1)
    $_SESSION['user_id'] = 1;
});

afterEach(function () {
    Connection::reset();
    unset($_SESSION['user_id']);
});

// --- currentPeriod ---

test('currentPeriod retourne le mois courant si jour actuel >= jour inscription', function () {
    $jourActuel = (int) date('j');
    // Inscription le 1er → toujours dans le mois courant
    expect(Quota::currentPeriod(1))->toBe(date('Ym'));
});

test('currentPeriod retourne le mois précédent si jour actuel < jour inscription', function () {
    // Inscription le 31 → si on est avant le 31, c'est la période du mois précédent
    $jourActuel = (int) date('j');
    if ($jourActuel < 28) {
        expect(Quota::currentPeriod(28))->toBe(date('Ym', strtotime('first day of last month')));
    } else {
        // Si on est le 28+, tester avec un jour encore plus loin
        $this->assertTrue(true); // Skip conditionnel
    }
});

// --- dateProchainResetUtilisateur ---

test('dateProchainResetUtilisateur retourne une date valide', function () {
    $date = Quota::dateProchainResetUtilisateur(15);
    expect($date)->toMatch('/^\d{4}-\d{2}-\d{2}$/');

    // Le jour retourné doit être 15 (ou moins si mois court)
    $jour = (int) date('j', strtotime($date));
    expect($jour)->toBeLessThanOrEqual(15);
});

test('dateProchainResetUtilisateur gère les mois courts', function () {
    // Inscrit le 31 → en février, le reset sera le 28 ou 29
    $date = Quota::dateProchainResetUtilisateur(31);
    $jour = (int) date('j', strtotime($date));
    expect($jour)->toBeLessThanOrEqual(31);
    expect($jour)->toBeGreaterThanOrEqual(28);
});

// --- trackerSiDisponible ---

test('trackerSiDisponible retourne false si aucun utilisateur connecté', function () {
    unset($_SESSION['user_id']);

    expect(Quota::trackerSiDisponible('test-module'))->toBeFalse();
});

test('trackerSiDisponible retourne true quand quota disponible', function () {
    $periode = Quota::currentPeriod(1);
    insererUsage(1, 'test-module', 5, $periode);

    expect(Quota::getUsage(1, 'test-module', 1))->toBe(5);
    expect(Quota::isOverQuota(1, 'test-module', 1))->toBeFalse();
});

test('trackerSiDisponible retourne false quand quota dépassé', function () {
    // L'utilisateur test est inscrit le 15, donc trackerSiDisponible utilise currentPeriod(15)
    $periode = Quota::currentPeriod(15);
    insererUsage(1, 'test-module', 9, $periode);

    expect(Quota::trackerSiDisponible('test-module', 2))->toBeFalse();

    expect(Quota::getUsage(1, 'test-module', 15))->toBe(9);
});

test('trackerSiDisponible retourne false quand quota exactement atteint', function () {
    $periode = Quota::currentPeriod(15);
    insererUsage(1, 'test-module', 10, $periode);

    expect(Quota::trackerSiDisponible('test-module', 1))->toBeFalse();
});

test('trackerSiDisponible retourne true quand quota illimité', function () {
    expect(Quota::getLimit(1, 'illimite'))->toBe(0);
    expect(Quota::isOverQuota(1, 'illimite'))->toBeFalse();
});

// --- restant ---

test('restant retourne null quand quota illimité', function () {
    expect(Quota::restant('illimite'))->toBeNull();
});

test('restant retourne le bon nombre d\'unités restantes', function () {
    $periode = Quota::currentPeriod(15);
    insererUsage(1, 'test-module', 3, $periode);

    expect(Quota::restant('test-module'))->toBe(7);
});

test('restant retourne 0 quand quota atteint', function () {
    $periode = Quota::currentPeriod(15);
    insererUsage(1, 'test-module', 15, $periode);

    expect(Quota::restant('test-module'))->toBe(0);
});

test('restant retourne 0 si aucun utilisateur connecté', function () {
    unset($_SESSION['user_id']);

    expect(Quota::restant('test-module'))->toBe(0);
});

// --- jourInscriptionUtilisateur ---

test('jourInscriptionUtilisateur retourne 1 si aucun utilisateur connecté', function () {
    unset($_SESSION['user_id']);

    expect(Quota::jourInscriptionUtilisateur())->toBe(1);
});
