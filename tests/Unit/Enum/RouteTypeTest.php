<?php

use Platform\Enum\RouteType;

test('RouteType a les bonnes valeurs', function () {
    expect(RouteType::Page->value)->toBe('page');
    expect(RouteType::Ajax->value)->toBe('ajax');
    expect(RouteType::Stream->value)->toBe('stream');
});

test('RouteType::estPassthrough pour Ajax et Stream', function () {
    expect(RouteType::Ajax->estPassthrough())->toBeTrue();
    expect(RouteType::Stream->estPassthrough())->toBeTrue();
    expect(RouteType::Page->estPassthrough())->toBeFalse();
});
