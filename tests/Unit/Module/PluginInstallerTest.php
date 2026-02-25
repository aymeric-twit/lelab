<?php

use Platform\Module\PluginInstaller;

test('detecterModuleJson lit un fichier module.json valide', function () {
    $tmpDir = sys_get_temp_dir() . '/plugin-test-' . uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/module.json', json_encode([
        'slug' => 'test-plugin',
        'name' => 'Test Plugin',
        'version' => '2.0.0',
    ]));

    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);
    $result = $installer->detecterModuleJson($tmpDir);

    expect($result)->toBeArray();
    expect($result['slug'])->toBe('test-plugin');
    expect($result['name'])->toBe('Test Plugin');
    expect($result['version'])->toBe('2.0.0');

    unlink($tmpDir . '/module.json');
    rmdir($tmpDir);
});

test('detecterModuleJson retourne null sans module.json', function () {
    $tmpDir = sys_get_temp_dir() . '/plugin-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);
    $result = $installer->detecterModuleJson($tmpDir);

    expect($result)->toBeNull();

    rmdir($tmpDir);
});

test('detecterModuleJson retourne null si slug absent', function () {
    $tmpDir = sys_get_temp_dir() . '/plugin-test-' . uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/module.json', json_encode(['name' => 'Sans Slug']));

    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);
    $result = $installer->detecterModuleJson($tmpDir);

    expect($result)->toBeNull();

    unlink($tmpDir . '/module.json');
    rmdir($tmpDir);
});

test('detecterPointEntree trouve index.php', function () {
    $tmpDir = sys_get_temp_dir() . '/plugin-test-' . uniqid();
    mkdir($tmpDir, 0755, true);
    touch($tmpDir . '/index.php');

    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);

    expect($installer->detecterPointEntree($tmpDir))->toBe('index.php');

    unlink($tmpDir . '/index.php');
    rmdir($tmpDir);
});

test('detecterPointEntree trouve adapter.php', function () {
    $tmpDir = sys_get_temp_dir() . '/plugin-test-' . uniqid();
    mkdir($tmpDir, 0755, true);
    touch($tmpDir . '/adapter.php');

    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);

    expect($installer->detecterPointEntree($tmpDir))->toBe('adapter.php');

    unlink($tmpDir . '/adapter.php');
    rmdir($tmpDir);
});

test('detecterPointEntree trouve public/index.php', function () {
    $tmpDir = sys_get_temp_dir() . '/plugin-test-' . uniqid();
    mkdir($tmpDir . '/public', 0755, true);
    touch($tmpDir . '/public/index.php');

    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);

    expect($installer->detecterPointEntree($tmpDir))->toBe('public/index.php');

    unlink($tmpDir . '/public/index.php');
    rmdir($tmpDir . '/public');
    rmdir($tmpDir);
});

test('detecterPointEntree retourne index.php par défaut', function () {
    $tmpDir = sys_get_temp_dir() . '/plugin-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);

    expect($installer->detecterPointEntree($tmpDir))->toBe('index.php');

    rmdir($tmpDir);
});

test('validerChemin retourne erreur si répertoire inexistant', function () {
    $db = $this->createMock(PDO::class);
    $installer = new PluginInstaller($db);

    $erreur = $installer->validerChemin('/chemin/totalement/inexistant');
    expect($erreur)->toContain("n'existe pas");
});
