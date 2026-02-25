<?php

use Platform\Http\Request;
use Platform\Router;

function creerRequeteFictive(string $method, string $path): Request
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    return new Request();
}

test('il devrait matcher une route GET simple', function () {
    $router = new Router();
    $resultat = null;

    $router->get('/login', function () use (&$resultat) {
        $resultat = 'login';
    });

    $req = creerRequeteFictive('GET', '/login');
    $router->dispatch($req);

    expect($resultat)->toBe('login');
});

test('il devrait capturer les paramètres de route', function () {
    $router = new Router();
    $capturedParams = null;

    $router->get('/users/{id}', function (Request $req, array $params) use (&$capturedParams) {
        $capturedParams = $params;
    });

    $req = creerRequeteFictive('GET', '/users/42');
    $router->dispatch($req);

    expect($capturedParams)->toHaveKey('id');
    expect($capturedParams['id'])->toBe('42');
});

test('il devrait supporter les routes catch-all', function () {
    $router = new Router();
    $capturedParams = null;

    $router->get('/m/{slug}/{sub*}', function (Request $req, array $params) use (&$capturedParams) {
        $capturedParams = $params;
    });

    $req = creerRequeteFictive('GET', '/m/crux/api/stream');
    $router->dispatch($req);

    expect($capturedParams['slug'])->toBe('crux');
    expect($capturedParams['sub'])->toBe('api/stream');
});

test('il devrait retourner 404 pour une route inconnue', function () {
    $router = new Router();
    $router->get('/existe', function () {});

    $req = creerRequeteFictive('GET', '/inexistante');
    ob_start();
    $router->dispatch($req);
    $output = ob_get_clean();

    expect($output)->toContain('404');
});

test('any() devrait matcher GET et POST', function () {
    $router = new Router();
    $compteur = 0;

    $router->any('/api', function () use (&$compteur) {
        $compteur++;
    });

    $router->dispatch(creerRequeteFictive('GET', '/api'));
    $router->dispatch(creerRequeteFictive('POST', '/api'));

    expect($compteur)->toBe(2);
});
