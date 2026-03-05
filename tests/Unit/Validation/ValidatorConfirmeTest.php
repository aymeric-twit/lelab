<?php

use Platform\Validation\Validator;

test('il devrait valider la confirmation de mot de passe', function () {
    $v = new Validator();
    $resultat = $v->valider(
        ['password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1'],
        ['password' => 'confirme']
    );
    expect($resultat)->toBeTrue();
});

test('il devrait échouer si la confirmation ne correspond pas', function () {
    $v = new Validator();
    $resultat = $v->valider(
        ['password' => 'Abcdefg1', 'password_confirmation' => 'Different1'],
        ['password' => 'confirme']
    );
    expect($resultat)->toBeFalse();
    expect($v->premiereErreur())->toContain('confirmation');
});

test('il devrait échouer si la confirmation est absente', function () {
    $v = new Validator();
    $resultat = $v->valider(
        ['password' => 'Abcdefg1'],
        ['password' => 'confirme']
    );
    expect($resultat)->toBeFalse();
});

test('il devrait passer si la valeur est vide', function () {
    $v = new Validator();
    $resultat = $v->valider(
        ['password' => '', 'password_confirmation' => ''],
        ['password' => 'confirme']
    );
    expect($resultat)->toBeTrue();
});

test('il devrait combiner confirme avec mot_de_passe', function () {
    $v = new Validator();
    $resultat = $v->valider(
        ['password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1'],
        ['password' => 'requis|mot_de_passe|confirme']
    );
    expect($resultat)->toBeTrue();
});

test('il devrait échouer confirme + mot_de_passe avec mauvaise confirmation', function () {
    $v = new Validator();
    $resultat = $v->valider(
        ['password' => 'Abcdefg1', 'password_confirmation' => 'Wrong'],
        ['password' => 'requis|mot_de_passe|confirme']
    );
    expect($resultat)->toBeFalse();
});
