<?php

use Platform\Module\ApiCreditsTracker;

test('currentWeekPeriod retourne le format ISO 8601', function () {
    $periode = ApiCreditsTracker::currentWeekPeriod();

    // Format : YYYYWnn (ex: 2026W11)
    expect($periode)->toMatch('/^\d{4}W\d{2}$/');
});

test('currentWeekPeriod correspond à la semaine courante', function () {
    $attendu = date('o\WW');
    expect(ApiCreditsTracker::currentWeekPeriod())->toBe($attendu);
});

test('resetCache vide le cache statique', function () {
    // Appeler resetCache ne doit pas lever d'exception
    ApiCreditsTracker::resetCache();

    // Après reset, les appels suivants doivent fonctionner
    expect(ApiCreditsTracker::currentWeekPeriod())->toMatch('/^\d{4}W\d{2}$/');
});

test('tracker avec amount négatif ou zéro ne fait rien', function () {
    // Ne doit pas lever d'exception même sans DB
    ApiCreditsTracker::tracker('TEST_KEY', 0);
    ApiCreditsTracker::tracker('TEST_KEY', -1);
})->throwsNoExceptions();

test('getUsage retourne 0 quand la table n\'existe pas', function () {
    $usage = ApiCreditsTracker::getUsage('CLE_INEXISTANTE');
    expect($usage)->toBe(0);
});

test('getUsagePourPeriode retourne 0 quand la table n\'existe pas', function () {
    $usage = ApiCreditsTracker::getUsagePourPeriode('CLE_INEXISTANTE', '202603');
    expect($usage)->toBe(0);
});

test('purger retourne 0 quand la table n\'existe pas', function () {
    $supprimees = ApiCreditsTracker::purger(6);
    expect($supprimees)->toBe(0);
});
