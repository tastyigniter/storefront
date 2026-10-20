<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Local\Models\Review;
use Igniter\Local\Models\ReviewSettings;
use Igniter\Reservation\Models\Reservation;

function review(array $overrides = []): array
{
    return array_merge([
        'comment' => 'Lovely food',
        'quality' => 5,
        'delivery' => 4,
        'service' => 5,
    ], $overrides);
}

beforeEach(function(): void {
    ReviewSettings::set('allow_reviews', 1);
    $this->location = currentLocation();
});

it('leaves a review for a completed order', function(): void {
    $customer = actingAsCustomer();
    $order = paidOrder($customer);
    completeOrder($order);

    $this->from(route('igniter.theme.checkout.success', ['hash' => $order->hash]))
        ->post(route('igniter.theme.review.store', ['type' => 'order', 'hash' => $order->hash]), review())
        ->assertSessionHas('success', lang('igniter.local::default.review.alert_review_success'));

    expect(Review::query()->where('customer_id', $customer->getKey())->first())
        ->quality->toBe(5)
        ->review_text->toBe('Lovely food');
});

it('reviews a reservation without a delivery rating', function(): void {
    $customer = actingAsCustomer();
    $reservation = Reservation::factory()->create(['customer_id' => $customer->getKey(), 'location_id' => $this->location->getKey()]);
    $reservation->addStatusHistory((int)setting('confirmed_reservation_status'));

    $this->post(route('igniter.theme.review.store', ['type' => 'reservation', 'hash' => $reservation->hash]), review(['delivery' => null]));

    expect(Review::query()->where('customer_id', $customer->getKey())->value('delivery'))->toBe(0);
});

it('asks guests to sign in before reviewing', function(): void {
    $order = paidOrder();

    $this->post(route('igniter.theme.review.store', ['type' => 'order', 'hash' => $order->hash]), review())
        ->assertSessionHasErrors(['form' => lang('igniter.local::default.review.alert_expired_login')]);
});

it('refuses reviews when they are turned off', function(): void {
    ReviewSettings::set('allow_reviews', 0);
    actingAsCustomer();

    $this->post(route('igniter.theme.review.store', ['type' => 'order', 'hash' => 'any']), review())
        ->assertSessionHasErrors(['form' => lang('igniter.local::default.review.alert_review_disabled')]);
});

it('refuses a review for an order it cannot find', function(): void {
    actingAsCustomer();

    $this->post(route('igniter.theme.review.store', ['type' => 'order', 'hash' => 'missing']), review())
        ->assertSessionHasErrors(['form' => lang('igniter.local::default.review.alert_review_not_found')]);
});

it('validates the review', function(): void {
    $this->post(route('igniter.theme.review.store', ['type' => 'order', 'hash' => 'any']), [])
        ->assertSessionHasErrors([
            'comment' => 'Please tell us a little about your experience.',
            'quality' => 'Please rate the food quality.',
            'service' => 'Please rate the service.',
        ]);
});
