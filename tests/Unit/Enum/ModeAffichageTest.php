<?php

use Platform\Enum\ModeAffichage;

test('ModeAffichage a les bonnes valeurs', function () {
    expect(ModeAffichage::Embedded->value)->toBe('embedded');
    expect(ModeAffichage::Iframe->value)->toBe('iframe');
    expect(ModeAffichage::Passthrough->value)->toBe('passthrough');
});

test('ModeAffichage::estIframe retourne true uniquement pour Iframe', function () {
    expect(ModeAffichage::Iframe->estIframe())->toBeTrue();
    expect(ModeAffichage::Embedded->estIframe())->toBeFalse();
    expect(ModeAffichage::Passthrough->estIframe())->toBeFalse();
});

test('ModeAffichage::estPassthrough retourne true uniquement pour Passthrough', function () {
    expect(ModeAffichage::Passthrough->estPassthrough())->toBeTrue();
    expect(ModeAffichage::Embedded->estPassthrough())->toBeFalse();
    expect(ModeAffichage::Iframe->estPassthrough())->toBeFalse();
});

test('ModeAffichage::estEmbedded retourne true uniquement pour Embedded', function () {
    expect(ModeAffichage::Embedded->estEmbedded())->toBeTrue();
    expect(ModeAffichage::Iframe->estEmbedded())->toBeFalse();
    expect(ModeAffichage::Passthrough->estEmbedded())->toBeFalse();
});

test('ModeAffichage::label retourne un libellé en français', function () {
    expect(ModeAffichage::Embedded->label())->toBe('Intégré (extractParts)');
    expect(ModeAffichage::Iframe->label())->toBe('Iframe (app complète)');
    expect(ModeAffichage::Passthrough->label())->toBe('Passthrough (sans layout)');
});

test('ModeAffichage::tryFrom gère les valeurs inconnues', function () {
    expect(ModeAffichage::tryFrom('embedded'))->toBe(ModeAffichage::Embedded);
    expect(ModeAffichage::tryFrom('iframe'))->toBe(ModeAffichage::Iframe);
    expect(ModeAffichage::tryFrom('passthrough'))->toBe(ModeAffichage::Passthrough);
    expect(ModeAffichage::tryFrom('inexistant'))->toBeNull();
});
