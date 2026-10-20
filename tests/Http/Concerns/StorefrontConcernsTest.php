<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Concerns;

use Carbon\Carbon;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Controllers\HomeController;
use Igniter\Storefront\Support\Hours;

it('formats times from dates, datetimes and clock strings', function(): void {
    $presenter = new class
    {
        use PresentsStorefront;

        public function format(mixed $time): string
        {
            return Hours::clock($time);
        }
    };

    expect($presenter->format(Carbon::parse('2026-01-01 07:05:00')))->toBe('07:05')
        ->and($presenter->format('2026-01-01 19:45:00'))->toBe('19:45')
        ->and($presenter->format('08:30:00'))->toBe('08:30');
});

it('gives theme middleware a storefront page', function(): void {
    $page = (new HomeController)->getPage();

    expect($page->getKey())->toBe('storefront')
        ->and($page->settings)->toBeNull()
        ->and(class_uses(HomeController::class))->toContain(ResolvesStorefrontPage::class);
});
