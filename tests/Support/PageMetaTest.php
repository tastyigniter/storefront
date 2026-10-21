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
        'title' => 'meta.order_title'.' - Glass Kitchen',
        'description' => 'meta.order_description',
    ])
        ->and(PageMeta::forRoute('igniter.theme.booking')['description'])->toBe('meta.booking_description')
        ->and(PageMeta::forRoute('igniter.theme.account.address')['title'])->toBe('meta.account_address_title'.' - Glass Kitchen');
});

it('has a title for every storefront page', function(string $route, string $title): void {
    expect(PageMeta::forRoute('igniter.theme.'.$route)['title'])->toBe($title.' - Glass Kitchen');
})->with([
    ['checkout.checkout', 'meta.checkout_checkout_title'],
    ['checkout.success', 'meta.checkout_success_title'],
    ['booking.success', 'meta.booking_success_title'],
    ['contact', 'meta.contact_title'],
    ['account.login', 'meta.account_login_title'],
    ['account.register', 'meta.account_register_title'],
    ['account.forgot', 'meta.account_forgot_title'],
    ['account.reset', 'meta.account_reset_title'],
    ['account.account', 'meta.account_account_title'],
    ['account.orders', 'meta.account_orders_title'],
    ['account.order', 'meta.account_order_title'],
    ['account.reservations', 'meta.account_reservations_title'],
    ['account.reservation', 'meta.account_reservation_title'],
]);

it('falls back to the site details for unknown routes and without a location', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    expect(PageMeta::forRoute(null))->toBe(['title' => 'Glass Kitchen', 'description' => PageMeta::siteDescription()])
        ->and(PageMeta::forRoute('igniter.theme.contact')['description'])->toBe('meta.contact_description');
});

it('uses the default description when the theme has none', function(): void {
    setThemeSettings(['meta_description' => '']);

    expect(PageMeta::siteDescription())->toBe('meta.default_description');
});

it('keeps descriptions short and does not repeat the site name', function(): void {
    $meta = PageMeta::make('Glass Kitchen', str_repeat('word ', 60));

    expect($meta['title'])->toBe('Glass Kitchen')
        ->and(mb_strlen($meta['description']))->toBeLessThanOrEqual(161);
});

it('prints the title and description in the page head', function(): void {
    $this->get(route('igniter.theme.contact'))
        ->assertOk()
        ->assertSee('<title>'.'meta.contact_title'.' - Glass Kitchen</title>', false)
        ->assertSee('<meta name="description" content="'.'meta.contact_description'.'" data-inertia="description">', false)
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('meta.title', 'meta.contact_title'.' - Glass Kitchen'));
});

it('titles booking confirmations with the booking number', function(): void {
    $reservation = Reservation::factory()->create(['location_id' => $this->location->getKey()]);

    $this->get(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('meta.title', 'meta.booking_confirmed'.' - Glass Kitchen'));
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
