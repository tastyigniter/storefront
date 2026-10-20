<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Carbon\Carbon;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Support\Hours;
use Igniter\Storefront\Support\Schedules;

beforeEach(function(): void {
    $this->travelTo(now()->setTime(12, 0));
    $this->location = currentLocation();
});

it('offers no timeslots for ASAP-only order types', function(): void {
    locationSettings($this->location, 'collection', ['time_restriction' => 1]);
    Location::setCurrent($this->location->fresh());

    $collection = collect(Schedules::fulfilmentOptions())->firstWhere('code', 'collection');

    expect($collection['days'])->toBe([])
        ->and($collection['asap'])->toBeTrue();
});

it('has no order types without a location', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    expect(Schedules::fulfilmentOptions())->toBe([])
        ->and(Schedules::fulfilment('orderTypeLabel'))->toBe('Delivery');
});

it('describes an order scheduled later in the week', function(): void {
    $when = now()->addDays(3)->setTime(18, 30);
    Location::putSession('delivery-timeslot', ['dateTime' => $when, 'isAsap' => false]);

    $fulfilment = Schedules::fulfilment();

    expect($fulfilment)->toMatchArray([
        'isAsap' => false,
        'date' => $when->toDateString(),
        'time' => '18:30',
        'timeLabel' => $when->isoFormat('ddd D MMM').', 6:30 PM',
    ]);
});

it('labels orders for today and tomorrow', function(): void {
    Location::putSession('delivery-timeslot', ['dateTime' => now()->setTime(15, 0), 'isAsap' => false]);
    expect(Schedules::fulfilment('timeLabel'))->toBe('Today, 3:00 PM');

    Location::putSession('delivery-timeslot', ['dateTime' => now()->addDay()->setTime(9, 15), 'isAsap' => false]);
    expect(Schedules::fulfilment('timeLabel'))->toBe('Tomorrow, 9:15 AM');
});

it('formats clock times', function(): void {
    expect(Hours::clock(Carbon::parse('2026-01-01 07:05')))->toBe('07:05')
        ->and(Hours::clock('2026-01-01 19:45:00'))->toBe('19:45')
        ->and(Hours::clock('08:30'))->toBe('08:30');
});
