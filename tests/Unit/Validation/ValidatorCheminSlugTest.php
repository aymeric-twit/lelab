<?php

use Platform\Validation\Validator;

test('il devrait valider un chemin existant', function () {
    $v = new Validator();
    expect($v->valider(['chemin' => '/tmp'], ['chemin' => 'chemin']))->toBeTrue();
});

test('il devrait rejeter un chemin inexistant', function () {
    $v = new Validator();
    expect($v->valider(['chemin' => '/chemin/totalement/inexistant'], ['chemin' => 'chemin']))->toBeFalse();
    expect($v->erreurs()['chemin'][0])->toContain('répertoire');
});

test('il devrait accepter un chemin vide', function () {
    $v = new Validator();
    expect($v->valider(['chemin' => ''], ['chemin' => 'chemin']))->toBeTrue();
});

test('il devrait valider un slug correct', function () {
    $v = new Validator();
    expect($v->valider(['slug' => 'mon-outil'], ['slug' => 'slug']))->toBeTrue();
    expect($v->valider(['slug' => 'crux'], ['slug' => 'slug']))->toBeTrue();
    expect($v->valider(['slug' => 'kg-entities'], ['slug' => 'slug']))->toBeTrue();
    expect($v->valider(['slug' => 'a1'], ['slug' => 'slug']))->toBeTrue();
});

test('il devrait rejeter un slug invalide', function () {
    $v1 = new Validator();
    expect($v1->valider(['slug' => 'Mon-Outil'], ['slug' => 'slug']))->toBeFalse();

    $v2 = new Validator();
    expect($v2->valider(['slug' => '-commence-par-tiret'], ['slug' => 'slug']))->toBeFalse();

    $v3 = new Validator();
    expect($v3->valider(['slug' => 'finit-par-tiret-'], ['slug' => 'slug']))->toBeFalse();

    $v4 = new Validator();
    expect($v4->valider(['slug' => 'a'], ['slug' => 'slug']))->toBeFalse();

    $v5 = new Validator();
    expect($v5->valider(['slug' => 'avec espaces'], ['slug' => 'slug']))->toBeFalse();
});

test('il devrait accepter un slug vide', function () {
    $v = new Validator();
    expect($v->valider(['slug' => ''], ['slug' => 'slug']))->toBeTrue();
});
