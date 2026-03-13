<?php

use Platform\Http\Request;

beforeEach(function () {
    // Nettoyer les headers entre chaque test
    unset(
        $_SERVER['HTTP_X_REQUESTED_WITH'],
        $_SERVER['HTTP_ACCEPT'],
        $_SERVER['HTTP_CONTENT_TYPE'],
    );
});

// === estRequeteAjax ===

test('estRequeteAjax détecte X-Requested-With: XMLHttpRequest', function () {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $req = new Request();

    expect($req->estRequeteAjax())->toBeTrue();
});

test('estRequeteAjax détecte Accept: application/json', function () {
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $req = new Request();

    expect($req->estRequeteAjax())->toBeTrue();
});

test('estRequeteAjax détecte Accept: application/json parmi d\'autres types', function () {
    $_SERVER['HTTP_ACCEPT'] = 'application/json, text/plain, */*';
    $req = new Request();

    expect($req->estRequeteAjax())->toBeTrue();
});

test('estRequeteAjax détecte Content-Type: application/json', function () {
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json; charset=utf-8';
    $req = new Request();

    expect($req->estRequeteAjax())->toBeTrue();
});

test('estRequeteAjax retourne false pour une requête navigateur classique', function () {
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
    $req = new Request();

    expect($req->estRequeteAjax())->toBeFalse();
});

test('estRequeteAjax retourne false sans headers', function () {
    $req = new Request();

    expect($req->estRequeteAjax())->toBeFalse();
});

test('estRequeteAjax retourne false pour un form POST classique', function () {
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
    $req = new Request();

    expect($req->estRequeteAjax())->toBeFalse();
});

// === estRequeteSSE ===

test('estRequeteSSE détecte Accept: text/event-stream', function () {
    $_SERVER['HTTP_ACCEPT'] = 'text/event-stream';
    $req = new Request();

    expect($req->estRequeteSSE())->toBeTrue();
});

test('estRequeteSSE retourne false pour une requête normale', function () {
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
    $req = new Request();

    expect($req->estRequeteSSE())->toBeFalse();
});

test('estRequeteSSE retourne false sans header Accept', function () {
    $req = new Request();

    expect($req->estRequeteSSE())->toBeFalse();
});

// === isAjax (rétro-compatibilité) ===

test('isAjax reste inchangé et détecte uniquement X-Requested-With', function () {
    // Avec Accept: json mais sans X-Requested-With → isAjax = false
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $req = new Request();

    expect($req->isAjax())->toBeFalse();
    expect($req->estRequeteAjax())->toBeTrue();
});

// === ipAnonymisee (RGPD) ===

test('ipAnonymisee tronque le dernier octet IPv4', function () {
    $_SERVER['REMOTE_ADDR'] = '192.168.1.42';
    $req = new Request();

    expect($req->ipAnonymisee())->toBe('192.168.1.0');
});

test('ipAnonymisee gère 0.0.0.0', function () {
    $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
    $req = new Request();

    expect($req->ipAnonymisee())->toBe('0.0.0.0');
});

test('ipAnonymisee gère une IP déjà anonymisée', function () {
    $_SERVER['REMOTE_ADDR'] = '10.0.0.0';
    $req = new Request();

    expect($req->ipAnonymisee())->toBe('10.0.0.0');
});
