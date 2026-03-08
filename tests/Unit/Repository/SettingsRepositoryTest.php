<?php

use Platform\Repository\SettingsRepository;

beforeEach(function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec('CREATE TABLE settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        "group" TEXT NOT NULL,
        "key" TEXT NOT NULL,
        "value" TEXT DEFAULT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE("group", "key")
    )');

    $this->pdo = $pdo;
    $this->repo = new SettingsRepository($pdo);
});

test('obtenir retourne le défaut si clé inexistante', function () {
    expect($this->repo->obtenir('smtp', 'host'))->toBeNull();
    expect($this->repo->obtenir('smtp', 'host', 'localhost'))->toBe('localhost');
});

test('definir puis obtenir retourne la valeur', function () {
    $this->repo->definir('smtp', 'host', 'smtp.example.com');
    expect($this->repo->obtenir('smtp', 'host'))->toBe('smtp.example.com');
});

test('definir met à jour une valeur existante', function () {
    $this->repo->definir('smtp', 'host', 'old.com');
    $this->repo->definir('smtp', 'host', 'new.com');
    expect($this->repo->obtenir('smtp', 'host'))->toBe('new.com');

    $count = $this->pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    expect((int) $count)->toBe(1);
});

test('obtenirGroupe retourne toutes les clés du groupe', function () {
    $this->repo->definir('smtp', 'host', 'smtp.example.com');
    $this->repo->definir('smtp', 'port', '587');
    $this->repo->definir('notifications', 'admin_email', 'admin@test.com');

    $smtp = $this->repo->obtenirGroupe('smtp');
    expect($smtp)->toBe(['host' => 'smtp.example.com', 'port' => '587']);
});

test('definirGroupe définit plusieurs clés', function () {
    $this->repo->definirGroupe('smtp', [
        'host' => 'mail.example.com',
        'port' => '465',
    ]);

    expect($this->repo->obtenir('smtp', 'host'))->toBe('mail.example.com');
    expect($this->repo->obtenir('smtp', 'port'))->toBe('465');
});

test('supprimer supprime une clé', function () {
    $this->repo->definir('smtp', 'host', 'test.com');
    $this->repo->supprimer('smtp', 'host');
    expect($this->repo->obtenir('smtp', 'host'))->toBeNull();
});

test('obtenir retourne le défaut si valeur vide', function () {
    $this->repo->definir('smtp', 'host', '');
    expect($this->repo->obtenir('smtp', 'host', 'fallback'))->toBe('fallback');
});
