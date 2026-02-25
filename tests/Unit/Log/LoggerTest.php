<?php

use Platform\Log\Logger;

test('il devrait écrire un fichier de log', function () {
    $tmpDir = sys_get_temp_dir() . '/seo-platform-test-logs-' . uniqid();
    Logger::init($tmpDir);

    Logger::error('Erreur de test', ['module' => 'crux']);

    $fichier = $tmpDir . '/platform-' . date('Y-m-d') . '.log';
    expect(file_exists($fichier))->toBeTrue();

    $contenu = file_get_contents($fichier);
    expect($contenu)->toContain('ERROR');
    expect($contenu)->toContain('Erreur de test');
    expect($contenu)->toContain('crux');

    // Nettoyage
    unlink($fichier);
    rmdir($tmpDir);
});

test('il devrait créer le répertoire de logs s\'il n\'existe pas', function () {
    $tmpDir = sys_get_temp_dir() . '/seo-platform-test-logs-nouveau-' . uniqid();
    expect(is_dir($tmpDir))->toBeFalse();

    Logger::init($tmpDir);
    Logger::info('Test création répertoire');

    expect(is_dir($tmpDir))->toBeTrue();

    // Nettoyage
    $fichier = $tmpDir . '/platform-' . date('Y-m-d') . '.log';
    if (file_exists($fichier)) {
        unlink($fichier);
    }
    rmdir($tmpDir);
});
