<?php

use Platform\Database\Connection;
use Platform\Service\NotificationService;

beforeEach(function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        email TEXT,
        role TEXT DEFAULT "user",
        active INTEGER DEFAULT 1,
        deleted_at TEXT DEFAULT NULL
    )');
    $pdo->exec('CREATE TABLE modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL,
        name TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE email_notifications_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type_notification TEXT NOT NULL,
        module_slug TEXT DEFAULT NULL,
        year_month TEXT DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, type_notification, module_slug, year_month)
    )');

    $pdo->exec("INSERT INTO users (username, email, role) VALUES ('testuser', 'test@example.com', 'user')");
    $pdo->exec("INSERT INTO users (username, email, role) VALUES ('admin', 'admin@example.com', 'admin')");
    $pdo->exec("INSERT INTO modules (slug, name) VALUES ('suggest', 'Google Suggest')");

    // Injecter le PDO via Reflection
    $ref = new ReflectionClass(Connection::class);
    $prop = $ref->getProperty('instance');
    $prop->setAccessible(true);
    $prop->setValue(null, $pdo);

    NotificationService::resetInstance();

    $this->pdo = $pdo;
});

afterEach(function () {
    Connection::reset();
    NotificationService::resetInstance();
});

test('dejaEnvoye retourne false quand aucune notification envoyée', function () {
    $service = new NotificationService($this->pdo);
    $ref = new ReflectionMethod($service, 'dejaEnvoye');
    $ref->setAccessible(true);

    expect($ref->invoke($service, 1, 'quota_80', 'suggest'))->toBeFalse();
});

test('marquerEnvoye puis dejaEnvoye retourne true', function () {
    $service = new NotificationService($this->pdo);

    $marquer = new ReflectionMethod($service, 'marquerEnvoye');
    $marquer->setAccessible(true);
    $marquer->invoke($service, 1, 'quota_80', 'suggest');

    $deja = new ReflectionMethod($service, 'dejaEnvoye');
    $deja->setAccessible(true);
    expect($deja->invoke($service, 1, 'quota_80', 'suggest'))->toBeTrue();
});

test('dejaEnvoye distingue les types de notification', function () {
    $service = new NotificationService($this->pdo);

    $marquer = new ReflectionMethod($service, 'marquerEnvoye');
    $marquer->setAccessible(true);
    $marquer->invoke($service, 1, 'quota_80', 'suggest');

    $deja = new ReflectionMethod($service, 'dejaEnvoye');
    $deja->setAccessible(true);

    expect($deja->invoke($service, 1, 'quota_80', 'suggest'))->toBeTrue();
    expect($deja->invoke($service, 1, 'quota_100', 'suggest'))->toBeFalse();
});

test('dejaEnvoye distingue les modules', function () {
    $service = new NotificationService($this->pdo);

    $marquer = new ReflectionMethod($service, 'marquerEnvoye');
    $marquer->setAccessible(true);
    $marquer->invoke($service, 1, 'quota_80', 'suggest');

    $deja = new ReflectionMethod($service, 'dejaEnvoye');
    $deja->setAccessible(true);

    expect($deja->invoke($service, 1, 'quota_80', 'suggest'))->toBeTrue();
    expect($deja->invoke($service, 1, 'quota_80', 'kg-entities'))->toBeFalse();
});

test('marquerEnvoye ne duplique pas (INSERT OR IGNORE)', function () {
    $service = new NotificationService($this->pdo);

    $marquer = new ReflectionMethod($service, 'marquerEnvoye');
    $marquer->setAccessible(true);

    // Appeler deux fois ne doit pas lever d'exception
    $marquer->invoke($service, 1, 'quota_80', 'suggest');
    $marquer->invoke($service, 1, 'quota_80', 'suggest');

    $count = $this->pdo->query('SELECT COUNT(*) FROM email_notifications_log')->fetchColumn();
    expect((int) $count)->toBe(1);
});

test('getNomModule retourne le nom depuis la BDD', function () {
    $service = new NotificationService($this->pdo);

    $method = new ReflectionMethod($service, 'getNomModule');
    $method->setAccessible(true);

    expect($method->invoke($service, 'suggest'))->toBe('Google Suggest');
});

test('getNomModule retourne le slug si module inconnu', function () {
    $service = new NotificationService($this->pdo);

    $method = new ReflectionMethod($service, 'getNomModule');
    $method->setAccessible(true);

    expect($method->invoke($service, 'inconnu'))->toBe('inconnu');
});

test('formaterMois formate correctement', function () {
    $service = new NotificationService($this->pdo);

    $method = new ReflectionMethod($service, 'formaterMois');
    $method->setAccessible(true);

    expect($method->invoke($service, '202601'))->toBe('janvier 2026');
    expect($method->invoke($service, '202512'))->toBe('décembre 2025');
    expect($method->invoke($service, '202308'))->toBe('août 2023');
});

test('getAdminEmails retourne les emails admin depuis la BDD', function () {
    $service = new NotificationService($this->pdo);

    $method = new ReflectionMethod($service, 'getAdminEmails');
    $method->setAccessible(true);

    $emails = $method->invoke($service);
    expect($emails)->toBe(['admin@example.com']);
});

test('il devrait rendre tous les nouveaux templates email', function () {
    $templates = [
        'bienvenue' => ['username' => 'test', 'lienPlateforme' => 'https://example.com'],
        'changement-mot-de-passe' => ['username' => 'test', 'dateChangement' => '01/01/2026', 'ip' => '127.0.0.1'],
        'suppression-compte' => ['username' => 'test', 'dateEffective' => '01/01/2026'],
        'admin-nouvel-inscrit' => ['username' => 'test', 'email' => 'test@example.com', 'dateInscription' => '01/01/2026', 'lienAdmin' => 'https://example.com/admin'],
        'alerte-quota-80' => ['username' => 'test', 'nomModule' => 'Suggest', 'usage' => 80, 'limite' => 100, 'pourcentage' => 80, 'dateReset' => '2026-02-01', 'lienPlateforme' => 'https://example.com'],
        'alerte-quota-100' => ['username' => 'test', 'nomModule' => 'Suggest', 'usage' => 100, 'limite' => 100, 'dateReset' => '2026-02-01', 'lienPlateforme' => 'https://example.com'],
        'reset-quotas' => ['username' => 'test', 'moisPrecedent' => 'janvier 2026', 'resumeModules' => [['nom' => 'Suggest', 'usage' => 50, 'limite' => 100]], 'lienPlateforme' => 'https://example.com'],
    ];

    foreach ($templates as $template => $variables) {
        $html = \Platform\Service\EmailTemplate::rendre($template, $variables);
        expect($html)->toContain('Le lab');
        expect($html)->toContain('test');
    }
});
