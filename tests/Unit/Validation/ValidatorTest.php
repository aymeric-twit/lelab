<?php

use Platform\Validation\Validator;

test('il devrait valider un champ requis', function () {
    $v = new Validator();
    expect($v->valider(['nom' => ''], ['nom' => 'requis']))->toBeFalse();
    expect($v->valider(['nom' => 'Jean'], ['nom' => 'requis']))->toBeTrue();
});

test('il devrait valider un email', function () {
    $v = new Validator();
    expect($v->valider(['email' => 'invalide'], ['email' => 'email']))->toBeFalse();
    expect($v->valider(['email' => 'test@example.com'], ['email' => 'email']))->toBeTrue();
});

test('il devrait valider un email vide comme valide', function () {
    $v = new Validator();
    expect($v->valider(['email' => ''], ['email' => 'email']))->toBeTrue();
});

test('il devrait valider la longueur minimale', function () {
    $v = new Validator();
    expect($v->valider(['nom' => 'ab'], ['nom' => 'min:3']))->toBeFalse();
    expect($v->valider(['nom' => 'abc'], ['nom' => 'min:3']))->toBeTrue();
});

test('il devrait valider la longueur maximale', function () {
    $v = new Validator();
    expect($v->valider(['nom' => 'abcdef'], ['nom' => 'max:5']))->toBeFalse();
    expect($v->valider(['nom' => 'abcde'], ['nom' => 'max:5']))->toBeTrue();
});

test('il devrait valider la force du mot de passe', function () {
    $v = new Validator();

    // Trop court
    expect($v->valider(['mdp' => 'Ab1'], ['mdp' => 'mot_de_passe']))->toBeFalse();

    // Pas de majuscule
    $v2 = new Validator();
    expect($v2->valider(['mdp' => 'abcdefg1'], ['mdp' => 'mot_de_passe']))->toBeFalse();

    // Pas de chiffre
    $v3 = new Validator();
    expect($v3->valider(['mdp' => 'Abcdefgh'], ['mdp' => 'mot_de_passe']))->toBeFalse();

    // Valide
    $v4 = new Validator();
    expect($v4->valider(['mdp' => 'Abcdefg1'], ['mdp' => 'mot_de_passe']))->toBeTrue();
});

test('il devrait valider la règle in', function () {
    $v = new Validator();
    expect($v->valider(['role' => 'admin'], ['role' => 'in:admin,user']))->toBeTrue();
    expect($v->valider(['role' => 'root'], ['role' => 'in:admin,user']))->toBeFalse();
});

test('il devrait combiner plusieurs règles', function () {
    $v = new Validator();
    expect($v->valider(
        ['nom' => ''],
        ['nom' => 'requis|min:3|max:50']
    ))->toBeFalse();
});

test('premiereErreur retourne la première erreur', function () {
    $v = new Validator();
    $v->valider(['nom' => ''], ['nom' => 'requis']);
    expect($v->premiereErreur())->toContain('requis');
});

test('premiereErreur retourne null si pas d\'erreur', function () {
    $v = new Validator();
    $v->valider(['nom' => 'Jean'], ['nom' => 'requis']);
    expect($v->premiereErreur())->toBeNull();
});
