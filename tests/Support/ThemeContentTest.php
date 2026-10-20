<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Models\Menu;
use Igniter\Frontend\Models\Banner;
use Igniter\Frontend\Models\CaptchaSettings;
use Igniter\Frontend\Models\Slider;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Models\Theme as ThemeModel;
use Igniter\Pages\Classes\MenuManager;
use Igniter\Pages\Models\Menu as StaticMenu;
use Igniter\System\Models\Settings;
use Igniter\Storefront\Support\ThemeContent;
use Mockery;
use RuntimeException;

function glassThemeRecord(): ThemeModel
{
    return ThemeModel::query()->firstOrCreate(['code' => 'storefront-fixture'], [
        'name' => 'Glass Theme',
        'version' => '1.0.0',
        'status' => 1,
    ]);
}

// Static menus

it('syncs and resolves the footer menu', function(): void {
    glassThemeRecord();
    StaticMenu::query()->where('theme_code', 'storefront-fixture')->delete();

    $menu = ThemeContent::footerMenu();

    expect($menu[0]['title'])->toBe('Order')
        ->and($menu[0]['url'])->toBeNull()
        ->and($menu[0]['items'][0])->toBe(['title' => 'Order online', 'url' => '/order', 'items' => []]);
});

it('returns no links for a menu the theme does not define', function(): void {
    glassThemeRecord();

    expect(ThemeContent::menu('missing-menu'))->toBe([]);
});

it('returns no links when there is no active theme', function(): void {
    $manager = Mockery::mock(resolve(ThemeManager::class))->makePartial();
    $manager->shouldReceive('getActiveTheme')->andReturnNull();
    app()->instance(ThemeManager::class, $manager);

    expect(ThemeContent::menu('main-menu'))->toBe([])
        ->and(ThemeContent::value('hero_title', 'fallback'))->toBe('fallback');
});

it('returns no links when a menu cannot be resolved', function(): void {
    glassThemeRecord();
    ThemeContent::menu('main-menu');
    $this->mock(MenuManager::class)->shouldReceive('generateReferences')->andThrow(new RuntimeException('broken'));

    expect(ThemeContent::menu('main-menu'))->toBe([]);
});

it('keeps links to other sites as they are', function(): void {
    $relative = (fn(?string $url): ?string => static::relativeUrl($url))->bindTo(null, ThemeContent::class);

    expect($relative(null))->toBeNull()
        ->and($relative(url('/')))->toBe('/')
        ->and($relative(url('/order')))->toBe('/order')
        ->and($relative('https://example.org/menu'))->toBe('https://example.org/menu');
});

// Site-wide content

it('shares social links and the cookie banner', function(): void {
    setThemeSettings([
        'social' => [
            ['network' => 'instagram', 'url' => 'https://instagram.com/glass'],
            ['network' => 'x', 'url' => ''],
            'not-a-link',
        ],
        'enable_gdpr' => true,
        'gdpr_cookie_message' => 'We use cookies.',
        'gdpr_accept_text' => 'OK',
        'gdpr_more_info_text' => 'Read more',
        'gdpr_more_info_link' => 'privacy-policy',
    ]);

    $site = ThemeContent::site();

    expect($site['social'])->toBe([['network' => 'instagram', 'url' => 'https://instagram.com/glass']])
        ->and($site['cookieBanner'])->toBe([
            'message' => 'We use cookies.',
            'acceptText' => 'OK',
            'moreInfoText' => 'Read more',
            'moreInfoUrl' => '/privacy-policy',
        ])
        ->and($site['captcha'])->toBeNull();
});

it('uses an uploaded logo and favicon and can hide the restaurant name', function(): void {
    Settings::set('site_logo', 'brand/restaurant.png');
    setThemeSettings([
        'logo_image' => 'brand/theme-logo.png',
        'favicon' => 'brand/favicon.png',
        'logo_display' => 'logo',
    ]);

    $brand = ThemeContent::brand();

    expect($brand['logo'])->toContain('brand/theme-logo.png')
        ->and($brand['favicon'])->toContain('brand/favicon.png')
        ->and($brand['showName'])->toBeFalse();
});

it('uses the restaurant logo when the theme has not uploaded one', function(): void {
    Settings::set('site_logo', 'brand/restaurant.png');
    setThemeSettings(['logo_image' => '', 'favicon' => 'no_photo.png']);

    $brand = ThemeContent::brand();

    expect($brand['logo'])->toContain('brand/restaurant.png')
        ->and($brand['favicon'])->toContain('brand/restaurant.png')
        ->and($brand['showName'])->toBeTrue();
});

it('leaves the logo empty so the flame icon is used when nothing is uploaded', function(): void {
    Settings::set('site_logo', 'no_photo.png');
    setThemeSettings(['logo_image' => null, 'favicon' => null, 'logo_display' => 'beside']);

    expect(ThemeContent::brand())->toBe([
        'logo' => null,
        'favicon' => null,
        'showName' => true,
    ]);
});

it('hides the cookie banner when it is turned off', function(): void {
    setThemeSettings(['enable_gdpr' => false]);

    expect(ThemeContent::site('cookieBanner'))->toBeNull();
});

it('shares the reCAPTCHA site key when keys are set', function(): void {
    CaptchaSettings::set(['api_site_key' => 'site-key', 'api_secret_key' => 'secret-key']);

    expect(ThemeContent::captcha())->toMatchArray(['siteKey' => 'site-key'])
        ->and(ThemeContent::captchaRules())->toBe(['g-recaptcha-response' => ['recaptcha']]);
});

// Homepage

it('builds the homepage slides, banners and featured dishes', function(): void {
    $location = currentLocation();
    $slider = Slider::query()->create(['name' => 'Hero', 'code' => 'hero']);
    $slider->newMediaInstance()->addFromRaw(
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='),
        'slide.png',
        'images',
    );
    $slider->media()->first()?->setCustomProperty('title', 'Fresh')->save();
    Banner::query()->create([
        'name' => 'Promo', 'code' => 'promo', 'type' => 'image', 'click_url' => 'order',
        'alt_text' => 'Promo', 'image_code' => ['banner.jpg'], 'status' => 1,
    ]);
    Banner::query()->create([
        'name' => 'Custom', 'code' => 'custom', 'type' => 'custom', 'click_url' => 'https://example.org',
        'custom_code' => '<p>Hello</p><script>alert(1)</script>', 'status' => 1,
    ]);
    Banner::query()->create(['name' => 'Plain', 'code' => 'plain', 'type' => 'image', 'status' => 1]);
    $first = Menu::factory()->create(['menu_name' => 'First']);
    $second = Menu::factory()->create(['menu_name' => 'Second']);
    $first->locations()->attach($location);
    $second->locations()->attach($location);
    setThemeSettings([
        'hero_title' => 'Welcome',
        'hero_text' => 'Order now',
        'slider_code' => 'hero',
        'slider_interval' => 1,
        'banner_codes' => ['custom', 'missing', 'promo', 'plain'],
        'featured_title' => 'Picks',
        'featured_items' => [(string)$second->getKey(), (string)$first->getKey(), '999999'],
        'featured_limit' => 5,
    ]);

    $home = ThemeContent::home($location, 'delivery');

    expect($home['heroTitle'])->toBe('Welcome')
        ->and($home['slideInterval'])->toBe(2000)
        ->and($home['slides'])->toHaveCount(1)
        ->and($home['slides'][0]['title'])->toBe('Fresh')
        ->and(array_column($home['banners'], 'code'))->toBe(['custom', 'promo', 'plain'])
        ->and($home['banners'][0])->toMatchArray(['type' => 'custom', 'url' => 'https://example.org', 'markup' => '<p>Hello</p>'])
        ->and($home['banners'][1])->toMatchArray(['type' => 'image', 'url' => '/order', 'markup' => null])
        ->and($home['banners'][1]['images'])->toHaveCount(1)
        ->and($home['banners'][2]['url'])->toBeNull()
        ->and($home['featuredTitle'])->toBe('Picks')
        ->and(array_column($home['featured'], 'name'))->toBe(['Second', 'First']);
});

it('skips homepage content that is not set up', function(): void {
    setThemeSettings(['slider_code' => 'missing', 'banner_codes' => [], 'featured_items' => []]);

    $home = ThemeContent::home(null, 'delivery');

    expect($home['slides'])->toBe([])
        ->and($home['banners'])->toBe([])
        ->and($home['featured'])->toBe([]);
});
