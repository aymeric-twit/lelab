<?php

use Platform\Service\PluginEnvService;

beforeEach(function () {
    // BDD SQLite en mémoire
    $this->pdo = new PDO('sqlite::memory:');
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Créer la table modules
    $this->pdo->exec('CREATE TABLE modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL,
        enabled INTEGER DEFAULT 1,
        cles_env TEXT DEFAULT NULL,
        desinstalle_le TEXT DEFAULT NULL
    )');

    // Module actif avec des clés d'environnement
    $this->pdo->exec("INSERT INTO modules (slug, enabled, cles_env)
        VALUES ('analytics', 1, '[\"GOOGLE_API_KEY\", \"ANALYTICS_ID\"]')");

    // Module actif avec d'autres clés
    $this->pdo->exec("INSERT INTO modules (slug, enabled, cles_env)
        VALUES ('search-console', 1, '[\"GSC_TOKEN\", \"GOOGLE_API_KEY\"]')");

    // Module désactivé (ne doit pas apparaître)
    $this->pdo->exec("INSERT INTO modules (slug, enabled, cles_env)
        VALUES ('desactive', 0, '[\"SECRET_KEY\"]')");

    // Module sans clés d'environnement
    $this->pdo->exec("INSERT INTO modules (slug, enabled, cles_env)
        VALUES ('simple', 1, NULL)");
});

test('clesAutorisees retourne les clés des modules actifs', function () {
    $service = new PluginEnvService($this->pdo);

    $cles = $service->clesAutorisees();

    expect($cles)->toContain('GOOGLE_API_KEY');
    expect($cles)->toContain('ANALYTICS_ID');
    expect($cles)->toContain('GSC_TOKEN');
});

test('clesAutorisees déduplique les clés partagées entre modules', function () {
    $service = new PluginEnvService($this->pdo);

    $cles = $service->clesAutorisees();

    // GOOGLE_API_KEY est dans les deux modules actifs, mais ne doit apparaître qu'une fois
    $occurrences = array_count_values($cles);
    expect($occurrences['GOOGLE_API_KEY'])->toBe(1);
});

test('clesAutorisees exclut les clés des modules désactivés', function () {
    $service = new PluginEnvService($this->pdo);

    $cles = $service->clesAutorisees();

    expect($cles)->not->toContain('SECRET_KEY');
});

test('clesAutorisees retourne un tableau vide si aucun module n\'a de clés', function () {
    // Vider les modules avec clés
    $this->pdo->exec("DELETE FROM modules");
    $this->pdo->exec("INSERT INTO modules (slug, enabled, cles_env) VALUES ('vide', 1, NULL)");

    $service = new PluginEnvService($this->pdo);

    $cles = $service->clesAutorisees();

    expect($cles)->toBeEmpty();
});

test('resoudreCle lit la valeur depuis $_ENV', function () {
    $service = new PluginEnvService($this->pdo);

    // Stocker une valeur dans $_ENV
    $_ENV['MA_CLE_TEST'] = 'valeur_secrete';

    $valeur = $service->resoudreCle('MA_CLE_TEST');

    expect($valeur)->toBe('valeur_secrete');

    // Nettoyage
    unset($_ENV['MA_CLE_TEST']);
});

test('resoudreCle retourne une chaîne vide si la clé n\'existe pas', function () {
    $service = new PluginEnvService($this->pdo);

    // S'assurer que la clé n'existe nulle part
    unset($_ENV['CLE_INEXISTANTE']);
    putenv('CLE_INEXISTANTE');

    $valeur = $service->resoudreCle('CLE_INEXISTANTE');

    expect($valeur)->toBe('');
});
