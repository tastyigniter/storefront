<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Carbon\Carbon;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\WorkingHour;
use Igniter\Storefront\Support\Hours;

it('lists opening hours for today and the week', function(): void {
    $this->travelTo(Carbon::parse('next monday 12:00'));
    $location = LocationModel::factory()->create();
    $location->working_hours()->createMany([
        ['type' => WorkingHour::OPENING, 'weekday' => 0, 'opening_time' => '09:00', 'closing_time' => '14:00', 'status' => 1],
        ['type' => WorkingHour::OPENING, 'weekday' => 0, 'opening_time' => '17:00', 'closing_time' => '22:00', 'status' => 1],
        ['type' => WorkingHour::OPENING, 'weekday' => 1, 'opening_time' => '09:00', 'closing_time' => '22:00', 'status' => 0],
    ]);

    expect(Hours::today($location->fresh()))->toBe('09:00 – 14:00, 17:00 – 22:00');

    $week = Hours::week($location->fresh());
    expect($week)->toHaveCount(7)
        ->and($week[0])->toBe(['day' => 'Mon', 'hours' => '09:00 – 14:00, 17:00 – 22:00'])
        ->and($week[1])->toBe(['day' => 'Tue', 'hours' => 'hours.closed']);
});

it('formats date objects as clock times', function(): void {
    expect(Hours::clock(Carbon::parse('2026-01-01 07:05:00')))->toBe('07:05')
        ->and(Hours::clock('2026-01-01 19:45:00'))->toBe('19:45')
        ->and(Hours::clock('18:30:00'))->toBe('18:30')
        ->and(Hours::clock('08:30'))->toBe('08:30');
});

it('shows a week open around the clock as one row', function(): void {
    $location = LocationModel::factory()->create();
    foreach (range(0, 6) as $weekday) {
        $location->working_hours()->create([
            'type' => WorkingHour::OPENING,
            'weekday' => $weekday,
            'opening_time' => '00:00',
            'closing_time' => $weekday === 6 ? '00:00' : '23:59',
            'status' => 1,
        ]);
    }

    expect(Hours::week($location->fresh()))->toBe([['day' => 'Mon–Sun', 'hours' => 'hours.all_day']])
        ->and(Hours::today($location->fresh()))->toBe('hours.all_day');
});

it('lists each day when only some are open around the clock', function(): void {
    $location = LocationModel::factory()->create();
    $location->working_hours()->create(['type' => WorkingHour::OPENING, 'weekday' => 0, 'opening_time' => '00:00', 'closing_time' => '23:59', 'status' => 1]);

    $week = Hours::week($location->fresh());

    expect($week)->toHaveCount(7)
        ->and($week[0])->toBe(['day' => 'Mon', 'hours' => 'hours.all_day'])
        ->and($week[1]['hours'])->toBe('hours.closed');
});
