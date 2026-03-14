<?php

use Platform\Container;

beforeEach(function () {
    // Reset le singleton Container entre chaque test
    $ref = new ReflectionClass(Container::class);
    $prop = $ref->getProperty('instance');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
});

test('set et get créent un singleton', function () {
    $container = Container::instance();

    $container->set('monService', function () {
        return new stdClass();
    });

    $instance1 = $container->get('monService');
    $instance2 = $container->get('monService');

    expect($instance1)->toBeInstanceOf(stdClass::class);
    expect($instance1)->toBe($instance2); // Même instance (singleton)
});

test('get lance RuntimeException pour un service inconnu', function () {
    $container = Container::instance();

    $container->get('service.inexistant');
})->throws(RuntimeException::class, 'non enregistré');

test('has retourne true pour un service enregistré', function () {
    $container = Container::instance();

    $container->set('monService', fn () => new stdClass());

    expect($container->has('monService'))->toBeTrue();
});

test('has retourne false pour un service non enregistré', function () {
    $container = Container::instance();

    expect($container->has('service.inexistant'))->toBeFalse();
});

test('setInstance enregistre directement une instance résolue', function () {
    $container = Container::instance();
    $objet = new stdClass();
    $objet->nom = 'test';

    $container->setInstance('monObjet', $objet);

    expect($container->has('monObjet'))->toBeTrue();
    expect($container->get('monObjet'))->toBe($objet);
    expect($container->get('monObjet')->nom)->toBe('test');
});

test('reset vide toutes les factories et instances', function () {
    $container = Container::instance();

    $container->set('service1', fn () => new stdClass());
    $container->setInstance('service2', new stdClass());

    expect($container->has('service1'))->toBeTrue();
    expect($container->has('service2'))->toBeTrue();

    $container->reset();

    // Après reset, le singleton est null, il faut récupérer une nouvelle instance
    $nouveau = Container::instance();

    expect($nouveau->has('service1'))->toBeFalse();
    expect($nouveau->has('service2'))->toBeFalse();
});

test('set écrase une factory existante et supprime l\'instance en cache', function () {
    $container = Container::instance();

    $container->set('monService', function () {
        $obj = new stdClass();
        $obj->version = 1;
        return $obj;
    });

    $v1 = $container->get('monService');
    expect($v1->version)->toBe(1);

    // Écraser avec une nouvelle factory
    $container->set('monService', function () {
        $obj = new stdClass();
        $obj->version = 2;
        return $obj;
    });

    $v2 = $container->get('monService');
    expect($v2->version)->toBe(2);
});
