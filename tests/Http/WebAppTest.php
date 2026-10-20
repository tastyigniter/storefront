<?php

declare(strict_types=1);

use Igniter\System\Models\Settings;
use Igniter\Storefront\Storefront;

it('describes the storefront as an installable app', function(): void {
    Settings::set('site_name', 'Kitchen');

    $this->get(route('igniter.theme.webmanifest'))
        ->assertOk()
        ->assertHeader('content-type', 'application/manifest+json')
        ->assertJsonPath('name', 'Kitchen')
        ->assertJsonPath('short_name', 'Kitchen')
        ->assertJsonPath('start_url', '/')
        ->assertJsonPath('scope', '/')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('theme_color', Storefront::config()->defaultBackground)
        ->assertJsonPath('background_color', Storefront::config()->defaultBackground)
        ->assertJsonPath('icons.0.sizes', '192x192')
        ->assertJsonPath('icons.1.sizes', '512x512')
        ->assertJsonPath('icons.2.purpose', 'maskable');
});

it('uses the theme name when the restaurant has none', function(): void {
    Settings::set('site_name', '');

    $this->get(route('igniter.theme.webmanifest'))
        ->assertOk()
        ->assertJsonPath('name', 'Restaurant')
        ->assertJsonPath('short_name', 'Restaurant');
});

it('shortens a long restaurant name for the home screen', function(): void {
    Settings::set('site_name', 'The Very Long Kitchen Name');

    $this->get(route('igniter.theme.webmanifest'))
        ->assertOk()
        ->assertJsonPath('short_name', 'The Very Lon');
});

it('uses the page colour and one uploaded icon', function(): void {
    setThemeSettings([
        'color_background' => '#142033',
        'pwa_icon' => 'brand/home-screen.png',
    ]);

    $response = $this->get(route('igniter.theme.webmanifest'))
        ->assertOk()
        ->assertJsonPath('theme_color', '#142033')
        ->assertJsonPath('background_color', '#142033');

    $icons = $response->json('icons');

    expect($icons)->toHaveCount(3)
        ->and($icons[0]['src'])->toContain('brand/home-screen.png')
        ->and($icons[0]['sizes'])->toBe('192x192')
        ->and($icons[1]['sizes'])->toBe('512x512')
        ->and($icons[2]['purpose'])->toBe('maskable')
        ->and($icons[0]['src'])->toBe($icons[1]['src'])
        ->and($icons[1]['src'])->toBe($icons[2]['src']);
});

it('names the type of an uploaded home screen icon', function(string $file, string $type): void {
    setThemeSettings(['pwa_icon' => $file]);

    $this->get(route('igniter.theme.webmanifest'))
        ->assertOk()
        ->assertJsonPath('icons.0.type', $type)
        ->assertJsonPath('icons.2.type', $type);
})->with([
    ['brand/icon.png', 'image/png'],
    ['brand/icon.jpg', 'image/jpeg'],
    ['brand/icon.jpeg', 'image/jpeg'],
    ['brand/icon.webp', 'image/webp'],
    ['brand/icon.svg', 'image/svg+xml'],
    ['brand/icon.gif', 'image/gif'],
]);

it('serves the service worker for the whole site', function(): void {
    $this->get(route('igniter.theme.service-worker'))
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertHeader('content-type', 'application/javascript; charset=UTF-8')
        ->assertSee('glass-offline-v2', false)
        ->assertSee('/offline.html', false)
        ->assertSee('/checkout', false);
});

it('serves the offline page in the page colour', function(): void {
    setThemeSettings(['color_background' => '#142033', 'color_ring' => '#8fd3c8']);

    $this->get(route('igniter.theme.offline'))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSee("You're offline", false)
        ->assertSee('#142033', false)
        ->assertSee('#8fd3c8', false)
        ->assertDontSee('#2a1a30', false);
});
