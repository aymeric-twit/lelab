<?php

use Platform\Enum\ModeAffichage;
use Platform\Enum\QuotaMode;
use Platform\Enum\RouteType;
use Platform\Module\ModuleDescriptor;

function creerDescriptor(array $overrides = []): ModuleDescriptor
{
    $data = array_merge([
        'slug'        => 'test-module',
        'name'        => 'Module Test',
        'description' => 'Un module de test',
        'version'     => '1.0.0',
        'icon'        => 'bi-gear',
        'entry_point' => 'index.php',
        'sort_order'  => 10,
        'quota_mode'  => 'form_submit',
        'default_quota' => 100,
        'routes' => [
            ['path' => 'process.php', 'type' => 'ajax'],
            ['path' => 'stream.php', 'type' => 'stream'],
            ['path' => 'page.php', 'type' => 'page'],
        ],
    ], $overrides);

    return new ModuleDescriptor('/tmp/modules/test', $data);
}

test('il devrait initialiser les propriétés correctement', function () {
    $desc = creerDescriptor();

    expect($desc->slug)->toBe('test-module');
    expect($desc->name)->toBe('Module Test');
    expect($desc->quotaMode)->toBe(QuotaMode::FormSubmit);
    expect($desc->defaultQuota)->toBe(100);
});

test('il devrait utiliser QuotaMode::None par défaut', function () {
    $desc = creerDescriptor(['quota_mode' => null]);
    expect($desc->quotaMode)->toBe(QuotaMode::None);
});

test('il devrait gérer un quota_mode invalide', function () {
    $desc = creerDescriptor(['quota_mode' => 'invalide']);
    expect($desc->quotaMode)->toBe(QuotaMode::None);
});

test('getEntryFile retourne le bon chemin', function () {
    $desc = creerDescriptor();
    expect($desc->getEntryFile())->toBe('/tmp/modules/test/index.php');
});

test('hasSubRoute détecte les routes existantes', function () {
    $desc = creerDescriptor();
    expect($desc->hasSubRoute('process.php'))->toBeTrue();
    expect($desc->hasSubRoute('inexistant.php'))->toBeFalse();
});

test('getRouteType retourne le bon type', function () {
    $desc = creerDescriptor();
    expect($desc->getRouteType('process.php'))->toBe(RouteType::Ajax);
    expect($desc->getRouteType('stream.php'))->toBe(RouteType::Stream);
    expect($desc->getRouteType('page.php'))->toBe(RouteType::Page);
});

test('getRouteType retourne Page par défaut', function () {
    $desc = creerDescriptor();
    expect($desc->getRouteType('inexistant.php'))->toBe(RouteType::Page);
});

// Tests ModeAffichage

test('il devrait utiliser ModeAffichage::Embedded par défaut', function () {
    $desc = creerDescriptor();
    expect($desc->modeAffichage)->toBe(ModeAffichage::Embedded);
    expect($desc->passthroughAll)->toBeFalse();
});

test('display_mode iframe est correctement reconnu', function () {
    $desc = creerDescriptor(['display_mode' => 'iframe']);
    expect($desc->modeAffichage)->toBe(ModeAffichage::Iframe);
    expect($desc->passthroughAll)->toBeFalse();
});

test('display_mode passthrough est correctement reconnu', function () {
    $desc = creerDescriptor(['display_mode' => 'passthrough']);
    expect($desc->modeAffichage)->toBe(ModeAffichage::Passthrough);
    expect($desc->passthroughAll)->toBeTrue();
});

test('display_mode invalide retombe sur Embedded', function () {
    $desc = creerDescriptor(['display_mode' => 'invalide']);
    expect($desc->modeAffichage)->toBe(ModeAffichage::Embedded);
});

test('rétrocompat : passthrough_all=true sans display_mode donne Passthrough', function () {
    $desc = creerDescriptor(['passthrough_all' => true]);
    expect($desc->modeAffichage)->toBe(ModeAffichage::Passthrough);
    expect($desc->passthroughAll)->toBeTrue();
});

test('display_mode a la priorité sur passthrough_all', function () {
    $desc = creerDescriptor(['display_mode' => 'iframe', 'passthrough_all' => true]);
    expect($desc->modeAffichage)->toBe(ModeAffichage::Iframe);
    expect($desc->passthroughAll)->toBeFalse();
});
