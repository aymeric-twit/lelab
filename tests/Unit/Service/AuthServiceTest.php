<?php

use Platform\Auth\PasswordHasher;
use Platform\Database\Connection;
use Platform\Service\AuditLogger;
use Platform\Service\AuthService;

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
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT "user",
        active INTEGER DEFAULT 1,
        domaine TEXT,
        created_at TEXT,
        deleted_at TEXT DEFAULT NULL,
        last_login TEXT,
        totp_enabled INTEGER DEFAULT 0,
        unsubscribe_token TEXT
    )');

    $pdo->exec('CREATE TABLE audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        target_type TEXT,
        target_id INTEGER,
        details TEXT,
        ip_address TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE login_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    // Injecter le PDO via Reflection dans Connection
    $ref = new ReflectionClass(Connection::class);
    $prop = $ref->getProperty('instance');
    $prop->setAccessible(true);
    $prop->setValue(null, $pdo);

    // Reset le singleton AuditLogger
    $refAudit = new ReflectionClass(AuditLogger::class);
    $inst = $refAudit->getProperty('instance');
    $inst->setAccessible(true);
    $inst->setValue(null, null);

    // Insérer des utilisateurs de test
    $hash = PasswordHasher::hash('motdepasse123');

    $pdo->exec("INSERT INTO users (id, username, email, password_hash, role, active, totp_enabled)
        VALUES (1, 'alice', 'alice@test.com', '{$hash}', 'user', 1, 0)");

    $pdo->exec("INSERT INTO users (id, username, email, password_hash, role, active, totp_enabled)
        VALUES (2, 'bob_inactif', 'bob@test.com', '{$hash}', 'user', 0, 0)");

    $pdo->exec("INSERT INTO users (id, username, email, password_hash, role, active, totp_enabled)
        VALUES (3, 'charlie_2fa', 'charlie@test.com', '{$hash}', 'user', 1, 1)");

    // Créer la fonction NOW() pour SQLite (utilisée par UserRepository::updateLastLogin)
    $pdo->sqliteCreateFunction('NOW', function () {
        return date('Y-m-d H:i:s');
    });

    // Démarrer une session pour Auth::loginParId (session_regenerate_id)
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $this->pdo = $pdo;
});

afterEach(function () {
    Connection::reset();
});

test('authentifier retourne succes avec des identifiants valides', function () {
    $service = new AuthService();

    $resultat = $service->authentifier('alice', 'motdepasse123', '127.0.0.1');

    expect($resultat['succes'])->toBeTrue();
    expect($resultat['necessite2fa'])->toBeFalse();
    expect($resultat['userId'])->toBe(1);
    expect($resultat['raison'])->toBeNull();
});

test('authentifier retourne echec avec un mauvais mot de passe', function () {
    $service = new AuthService();

    $resultat = $service->authentifier('alice', 'mauvais_mdp', '127.0.0.1');

    expect($resultat['succes'])->toBeFalse();
    expect($resultat['necessite2fa'])->toBeFalse();
    expect($resultat['userId'])->toBeNull();
    expect($resultat['raison'])->toBe('identifiants');
});

test('authentifier retourne echec pour un utilisateur inactif avec bon mot de passe', function () {
    $service = new AuthService();

    $resultat = $service->authentifier('bob_inactif', 'motdepasse123', '127.0.0.1');

    expect($resultat['succes'])->toBeFalse();
    expect($resultat['necessite2fa'])->toBeFalse();
    expect($resultat['userId'])->toBe(2);
    expect($resultat['raison'])->toBe('inactif');
});

test('authentifier retourne necessite2fa pour un utilisateur avec 2FA activée', function () {
    $service = new AuthService();

    $resultat = $service->authentifier('charlie_2fa', 'motdepasse123', '127.0.0.1');

    expect($resultat['succes'])->toBeTrue();
    expect($resultat['necessite2fa'])->toBeTrue();
    expect($resultat['userId'])->toBe(3);
    expect($resultat['raison'])->toBeNull();
});

test('authentifier retourne echec pour un utilisateur inexistant', function () {
    $service = new AuthService();

    $resultat = $service->authentifier('inconnu', 'motdepasse123', '127.0.0.1');

    expect($resultat['succes'])->toBeFalse();
    expect($resultat['necessite2fa'])->toBeFalse();
    expect($resultat['userId'])->toBeNull();
    expect($resultat['raison'])->toBe('identifiants');
});

test('authentifier enregistre un log dans audit_log en cas d\'échec', function () {
    $service = new AuthService();

    $service->authentifier('alice', 'mauvais_mdp', '192.168.1.1');

    $stmt = $this->pdo->query('SELECT * FROM audit_log WHERE action = "login.failed"');
    $log = $stmt->fetch();

    expect($log)->not->toBeFalse();
    expect($log['ip_address'])->toBe('192.168.1.1');
});
