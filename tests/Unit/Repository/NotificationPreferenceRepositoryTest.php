<?php

use Platform\Repository\NotificationPreferenceRepository;

beforeEach(function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec('CREATE TABLE user_notification_preferences (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type_notification TEXT NOT NULL,
        actif INTEGER NOT NULL DEFAULT 1,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, type_notification)
    )');

    $this->pdo = $pdo;
    $this->repo = new NotificationPreferenceRepository($pdo);
});

test('estDesactive retourne false si aucune préférence', function () {
    expect($this->repo->estDesactive(1, 'bienvenue'))->toBeFalse();
});

test('estDesactive retourne true si désactivé', function () {
    $this->pdo->exec("INSERT INTO user_notification_preferences (user_id, type_notification, actif) VALUES (1, 'bienvenue', 0)");
    expect($this->repo->estDesactive(1, 'bienvenue'))->toBeTrue();
});

test('estDesactive retourne false si activé explicitement', function () {
    $this->pdo->exec("INSERT INTO user_notification_preferences (user_id, type_notification, actif) VALUES (1, 'bienvenue', 1)");
    expect($this->repo->estDesactive(1, 'bienvenue'))->toBeFalse();
});

test('obtenirPreferences retourne un tableau vide si aucune préférence', function () {
    expect($this->repo->obtenirPreferences(1))->toBe([]);
});

test('obtenirPreferences retourne toutes les préférences', function () {
    $this->pdo->exec("INSERT INTO user_notification_preferences (user_id, type_notification, actif) VALUES (1, 'bienvenue', 1)");
    $this->pdo->exec("INSERT INTO user_notification_preferences (user_id, type_notification, actif) VALUES (1, 'quota_80', 0)");

    $prefs = $this->repo->obtenirPreferences(1);
    expect($prefs)->toBe(['bienvenue' => true, 'quota_80' => false]);
});

test('mettreAJour insère une nouvelle préférence', function () {
    $this->repo->mettreAJour(1, 'bienvenue', false);
    expect($this->repo->estDesactive(1, 'bienvenue'))->toBeTrue();
});

test('mettreAJour met à jour une préférence existante', function () {
    $this->repo->mettreAJour(1, 'bienvenue', false);
    $this->repo->mettreAJour(1, 'bienvenue', true);
    expect($this->repo->estDesactive(1, 'bienvenue'))->toBeFalse();
});

test('mettreAJourMultiple met à jour plusieurs préférences', function () {
    $this->repo->mettreAJourMultiple(1, [
        'bienvenue' => false,
        'quota_80' => true,
        'quota_100' => false,
    ]);

    $prefs = $this->repo->obtenirPreferences(1);
    expect($prefs)->toHaveCount(3);
    expect($prefs['bienvenue'])->toBeFalse();
    expect($prefs['quota_80'])->toBeTrue();
    expect($prefs['quota_100'])->toBeFalse();
});

test('les préférences sont isolées par utilisateur', function () {
    $this->repo->mettreAJour(1, 'bienvenue', false);
    $this->repo->mettreAJour(2, 'bienvenue', true);

    expect($this->repo->estDesactive(1, 'bienvenue'))->toBeTrue();
    expect($this->repo->estDesactive(2, 'bienvenue'))->toBeFalse();
});
