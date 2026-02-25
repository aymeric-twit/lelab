<?php

use Platform\Module\ModuleRegistry;

beforeEach(function () {
    // Reset le registre entre chaque test via Reflection
    $ref = new ReflectionClass(ModuleRegistry::class);
    $modules = $ref->getProperty('modules');
    $modules->setAccessible(true);
    $modules->setValue(null, []);
    $loaded = $ref->getProperty('loaded');
    $loaded->setAccessible(true);
    $loaded->setValue(null, false);
});

test('discover charge les modules depuis le filesystem', function () {
    $tmpDir = sys_get_temp_dir() . '/modules-test-' . uniqid();
    mkdir($tmpDir . '/mon-outil', 0755, true);
    file_put_contents($tmpDir . '/mon-outil/module.json', json_encode([
        'slug' => 'mon-outil',
        'name' => 'Mon Outil',
    ]));

    ModuleRegistry::discover($tmpDir);

    expect(ModuleRegistry::get('mon-outil'))->not->toBeNull();
    expect(ModuleRegistry::get('mon-outil')->name)->toBe('Mon Outil');
    expect(ModuleRegistry::all())->toHaveCount(1);

    unlink($tmpDir . '/mon-outil/module.json');
    rmdir($tmpDir . '/mon-outil');
    rmdir($tmpDir);
});

test('discover ignore le répertoire _template', function () {
    $tmpDir = sys_get_temp_dir() . '/modules-test-' . uniqid();
    mkdir($tmpDir . '/_template', 0755, true);
    file_put_contents($tmpDir . '/_template/module.json', json_encode([
        'slug' => 'template',
        'name' => 'Template',
    ]));

    ModuleRegistry::discover($tmpDir);

    expect(ModuleRegistry::get('template'))->toBeNull();
    expect(ModuleRegistry::all())->toHaveCount(0);

    unlink($tmpDir . '/_template/module.json');
    rmdir($tmpDir . '/_template');
    rmdir($tmpDir);
});

test('get retourne null pour un slug inexistant', function () {
    ModuleRegistry::discover(sys_get_temp_dir());
    expect(ModuleRegistry::get('inexistant'))->toBeNull();
});
