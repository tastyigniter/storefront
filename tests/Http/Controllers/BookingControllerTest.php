<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\ReviewSettings;
use Igniter\Reservation\Models\Reservation;
use Igniter\User\Models\Customer;
use Igniter\Storefront\Actions\PlaceReservation;
use Inertia\Testing\AssertableInertia;
use RuntimeException;

function booking(array $overrides = []): array
{
    return array_merge([
        'guest' => 2,
        'date' => now()->addDays(2)->toDateString(),
        'time' => '12:00',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
        'telephone' => '07700900000',
        'comment' => 'Window seat please',
    ], $overrides);
}

beforeEach(function(): void {
    $this->travelTo(now()->setTime(9, 0));
    $this->location = currentLocation();
});

it('shows the booking page with the schedule', function(): void {
    $this->get(route('igniter.theme.booking', ['guests' => 4]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Booking/Index')
            ->where('schedule.guests', 4)
            ->has('schedule.availableDates')
            ->has('schedule.times'));
});

it('sends customers home when there is no location to book', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    $this->get(route('igniter.theme.booking'))
        ->assertRedirect(route('igniter.theme.home'))
        ->assertSessionHas('error', 'Choose a location before booking.');
});

it('books a table', function(): void {
    $response = $this->post(route('igniter.theme.booking.store'), booking());

    $reservation = Reservation::query()->where('email', 'jane@example.test')->first();
    expect($reservation)->not->toBeNull()
        ->and($reservation->guest_num)->toBe(2)
        ->and($reservation->comment)->toBe('Window seat please');
    $response->assertRedirect(route('igniter.theme.booking.success', ['hash' => $reservation->hash]));
});

it("books with the signed-in customer's email", function(): void {
    $customer = actingAsCustomer();

    $this->post(route('igniter.theme.booking.store'), booking(['email' => 'other@example.test']));

    expect(Reservation::query()->where('email', $customer->email)->exists())->toBeTrue();
});

it('shows a general error when booking fails', function(): void {
    $this->mock(PlaceReservation::class)->shouldReceive('handle')->andThrow(new RuntimeException('down'));

    $this->from(route('igniter.theme.booking'))
        ->post(route('igniter.theme.booking.store'), booking())
        ->assertSessionHasErrors(['form' => 'Something went wrong. Please try again.']);
});

it('rejects dates outside the advance booking window', function(): void {
    $this->post(route('igniter.theme.booking.store'), booking(['date' => now()->subDay()->toDateString()]))
        ->assertSessionHasErrors(['date' => 'That date is too soon to book. Pick another date from the calendar.']);

    $this->post(route('igniter.theme.booking.store'), booking(['date' => now()->addYears(2)->toDateString()]))
        ->assertSessionHasErrors(['date' => 'That date is too far ahead to book. Pick another date from the calendar.']);
});

it('shows the booking confirmation', function(): void {
    ReviewSettings::set('allow_reviews', 1);
    $reservation = Reservation::factory()->create([
        'location_id' => $this->location->getKey(),
        'reserve_time' => '19:30:00',
        'guest_num' => 3,
    ]);

    $this->get(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Booking/Success')
            ->where('reservation.guests', 3)
            ->where('reservation.time', '19:30')
            ->where('review.type', 'reservation'));
});

it('returns 404 for an unknown booking', function(): void {
    $this->get(route('igniter.theme.booking.success', ['hash' => 'missing']))->assertNotFound();
});

it('has no booking address at /book', function(): void {
    $this->get('/book')->assertNotFound();
    $this->get('/book/success/abc')->assertNotFound();
});

it('asks a guest to confirm the booking email before cancelling', function(): void {
    locationSettings($this->location, 'booking', ['cancellation_timeout' => 60]);
    $reservation = Reservation::factory()->create([
        'location_id' => $this->location->getKey(),
        'customer_id' => null,
        'email' => 'guest@example.test',
        'reserve_date' => now()->addDays(2)->toDateString(),
        'reserve_time' => '19:30:00',
    ]);

    $this->get(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('reservation.confirmEmail', true)
            ->where('reservation.canCancel', true));

    $this->from(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->post(route('igniter.theme.reservation.cancel', ['hash' => $reservation->hash]), ['email' => 'wrong@example.test'])
        ->assertSessionHasErrors(['email' => lang('storefront.fixture::default.alert.cancel_email_mismatch')]);

    expect($reservation->fresh()->isCanceled())->toBeFalse();

    $this->from(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->post(route('igniter.theme.reservation.cancel', ['hash' => $reservation->hash]), ['email' => 'guest@example.test'])
        ->assertSessionHas('success', lang('igniter.reservation::default.alert_cancel_success'));

    expect($reservation->fresh()->isCanceled())->toBeTrue();
});

it('will not cancel a booking that belongs to another customer', function(): void {
    locationSettings($this->location, 'booking', ['cancellation_timeout' => 60]);
    actingAsCustomer();
    $other = Customer::factory()->create();
    $reservation = Reservation::factory()->create([
        'location_id' => $this->location->getKey(),
        'customer_id' => $other->getKey(),
        'email' => 'other@example.test',
        'reserve_date' => now()->addDays(2)->toDateString(),
        'reserve_time' => '19:30:00',
    ]);

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.reservation.cancel', ['hash' => $reservation->hash]), ['email' => 'other@example.test'])
        ->assertSessionHasErrors(['form' => lang('storefront.fixture::default.alert.booking_not_found')]);

    expect($reservation->fresh()->isCanceled())->toBeFalse();
});

it('lets the customer who booked cancel without treating the email as a mismatch', function(): void {
    locationSettings($this->location, 'booking', ['cancellation_timeout' => 60]);
    $customer = actingAsCustomer();
    $reservation = Reservation::factory()->create([
        'location_id' => $this->location->getKey(),
        'customer_id' => $customer->getKey(),
        'email' => $customer->email,
        'reserve_date' => now()->addDays(2)->toDateString(),
        'reserve_time' => '19:30:00',
    ]);

    $this->from(route('igniter.theme.booking.success', ['hash' => $reservation->hash]))
        ->post(route('igniter.theme.reservation.cancel', ['hash' => $reservation->hash]))
        ->assertSessionHas('success', lang('igniter.reservation::default.alert_cancel_success'));

    expect($reservation->fresh()->isCanceled())->toBeTrue();
});

it('reports a missing or already cancelled booking', function(): void {
    locationSettings($this->location, 'booking', ['cancellation_timeout' => 60]);
    $customer = actingAsCustomer();
    $reservation = Reservation::factory()->create([
        'location_id' => $this->location->getKey(),
        'customer_id' => $customer->getKey(),
        'email' => $customer->email,
        'reserve_date' => now()->addDays(2)->toDateString(),
        'reserve_time' => '19:30:00',
    ]);
    $reservation->markAsCanceled();

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.reservation.cancel', ['hash' => 'missing']))
        ->assertSessionHasErrors(['form' => lang('storefront.fixture::default.alert.booking_not_found')]);

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.reservation.cancel', ['hash' => $reservation->hash]))
        ->assertSessionHasErrors(['form' => lang('igniter.reservation::default.alert_cancel_failed')]);
});
