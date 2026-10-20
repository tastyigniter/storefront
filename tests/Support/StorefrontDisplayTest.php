<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Facades\Cart;
use Igniter\Cart\Models\Menu;
use Igniter\Local\Models\Review;
use Igniter\Local\Models\ReviewSettings;
use Igniter\Reservation\Models\Reservation;
use Igniter\Storefront\Support\StorefrontState;
use Igniter\Storefront\Support\ThemeContent;
use Inertia\Testing\AssertableInertia;

beforeEach(function(): void {
    $this->location = currentLocation();
});

it('shows dish and category images unless the theme turns them off', function(): void {
    expect(ThemeContent::site('display'))->toBe(['menuImages' => true, 'categoryImages' => true]);

    setThemeSettings(['show_menu_images' => '0', 'show_category_images' => 0]);

    expect(ThemeContent::site('display'))->toBe(['menuImages' => false, 'categoryImages' => false]);
});

it('shares a thumbnail with each basket line', function(): void {
    $menu = Menu::factory()->create();
    resolve(CartManager::class)->cartInstance($this->location->getKey());
    Cart::add(['id' => $menu->getKey(), 'name' => 'Burger', 'price' => 10], 1);

    expect(StorefrontState::shared('cart.lines.0'))->toHaveKey('image', null);
});

it('shares the review score and the order lead times of the current location', function(): void {
    ReviewSettings::set('allow_reviews', 1);
    Review::query()->insert([
        ['location_id' => $this->location->getKey(), 'quality' => 5, 'delivery' => 4, 'service' => 3, 'review_status' => 1, 'reviewable_type' => 'orders', 'reviewable_id' => 1, 'customer_id' => 1, 'author' => 'Ann', 'review_text' => 'Good'],
        ['location_id' => $this->location->getKey(), 'quality' => 5, 'delivery' => 5, 'service' => 5, 'review_status' => 0, 'reviewable_type' => 'orders', 'reviewable_id' => 2, 'customer_id' => 2, 'author' => 'Bob', 'review_text' => 'Great'],
    ]);

    $location = StorefrontState::shared('location');

    expect($location['rating']['count'])->toBe(1)
        ->and($location['rating']['score'])->toBeGreaterThan(0.0)
        ->and($location['leadTimes'])->toHaveKeys(['delivery', 'collection'])
        ->and($location['leadTimes']['delivery'])->toBeInt();
});

it('leaves the rating out when reviews are turned off', function(): void {
    ReviewSettings::set('allow_reviews', 0);

    expect(StorefrontState::shared('location.rating'))->toBeNull();
});

it('includes the restaurant address on the booking confirmation', function(): void {
    $reservation = Reservation::factory()->create(['location_id' => $this->location->getKey()]);

    $this->get(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('reservation.locationAddress', StorefrontState::address($this->location)));
});
