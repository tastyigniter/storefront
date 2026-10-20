<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Main\Classes\ThemeManager;
use Igniter\Storefront\Support\ThemeGate;
use Mockery;
use RuntimeException;

it('is active for the glass theme', function(): void {
    expect(ThemeGate::active())->toBeTrue();
});

it('is inactive without an active theme', function(): void {
    $manager = Mockery::mock(resolve(ThemeManager::class))->makePartial();
    $manager->shouldReceive('getActiveTheme')->andReturnNull();
    app()->instance(ThemeManager::class, $manager);

    expect(ThemeGate::active())->toBeFalse();
});

it('is inactive when the theme cannot be loaded', function(): void {
    app()->bind(ThemeManager::class, fn() => throw new RuntimeException('no database'));

    expect(ThemeGate::active())->toBeFalse();
});
