<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Pages\Models\Page;
use Igniter\Reservation\Models\Reservation;
use Igniter\Storefront\Support\PageMeta;
use Igniter\System\Models\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function(): void {
    Settings::set('site_name', 'Glass Kitchen');
    $this->location = currentLocation(['location_name' => 'Soho']);
});

it('uses the site name and description on the homepage', function(): void {
    setThemeSettings(['meta_description' => '<p>Seasonal   plates,  delivered.</p>']);

    expect(PageMeta::forRoute('igniter.theme.home'))->toBe([
        'title' => 'Glass Kitchen',
        'description' => 'Seasonal plates, delivered.',
    ]);
});

it('titles storefront pages with the site name', function(): void {
    expect(PageMeta::forRoute('igniter.theme.order'))->toBe([
        'title' => 'Menu - Glass Kitchen',
        'description' => 'Browse the menu and order online from Soho for delivery or collection.',
    ])
        ->and(PageMeta::forRoute('igniter.theme.booking')['description'])->toBe('Reserve a table at Soho. Pick a date, time and party size.')
        ->and(PageMeta::forRoute('igniter.theme.account.address')['title'])->toBe('Your addresses - Glass Kitchen');
});

it('has a title for every storefront page', function(string $route, string $title): void {
    expect(PageMeta::forRoute('igniter.theme.'.$route)['title'])->toBe($title.' - Glass Kitchen');
})->with([
    ['checkout.checkout', 'Checkout'],
    ['checkout.success', 'Order confirmed'],
    ['booking.success', 'Booking confirmed'],
    ['contact', 'Contact us'],
    ['account.login', 'Sign in'],
    ['account.register', 'Create an account'],
    ['account.forgot', 'Forgot password'],
    ['account.reset', 'Reset password'],
    ['account.account', 'Your account'],
    ['account.orders', 'Your orders'],
    ['account.order', 'Order details'],
    ['account.reservations', 'Your reservations'],
    ['account.reservation', 'Reservation details'],
]);

it('falls back to the site details for unknown routes and without a location', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    expect(PageMeta::forRoute(null))->toBe(['title' => 'Glass Kitchen', 'description' => PageMeta::siteDescription()])
        ->and(PageMeta::forRoute('igniter.theme.contact')['description'])->toBe('Questions, feedback or a special request? Get in touch at Glass Kitchen.');
});

it('uses the default description when the theme has none', function(): void {
    setThemeSettings(['meta_description' => '']);

    expect(PageMeta::siteDescription())->toBe('Order online for delivery or collection, or book a table.');
});

it('keeps descriptions short and does not repeat the site name', function(): void {
    $meta = PageMeta::make('Glass Kitchen', str_repeat('word ', 60));

    expect($meta['title'])->toBe('Glass Kitchen')
        ->and(mb_strlen($meta['description']))->toBeLessThanOrEqual(161);
});

it('prints the title and description in the page head', function(): void {
    $this->get(route('igniter.theme.contact'))
        ->assertOk()
        ->assertSee('<title>Contact us - Glass Kitchen</title>', false)
        ->assertSee('<meta name="description" content="Questions, feedback or a special request? Get in touch at Soho." data-inertia="description">', false)
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('meta.title', 'Contact us - Glass Kitchen'));
});

it('titles booking confirmations with the booking number', function(): void {
    $reservation = Reservation::factory()->create(['location_id' => $this->location->getKey()]);

    $this->get(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('meta.title', 'Booking #'.$reservation->getKey().' confirmed - Glass Kitchen'));
});

it('describes static pages from their meta description or content', function(): void {
    $page = Page::query()->where('status', true)->firstOrFail();
    $page->meta_description = 'All about us';
    $page->save();

    $this->get('/'.$page->permalink_slug)
        ->assertInertia(fn(AssertableInertia $inertia): AssertableInertia => $inertia
            ->where('meta.title', $page->title.' - Glass Kitchen')
            ->where('meta.description', 'All about us'));
});
