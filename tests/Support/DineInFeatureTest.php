<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Models\Category;
use Igniter\Cart\Models\Menu;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Reservation\Models\DiningArea;
use Igniter\Reservation\Models\DiningTable;
use Igniter\Reservation\Models\Reservation;
use Igniter\Storefront\Actions\Fulfilment;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\MenuCatalog;
use Igniter\Storefront\Support\Schedules;

beforeEach(function(): void {
    $this->location = currentLocation();
    DineInTables::$enabled = true;
});

afterEach(function(): void {
    DineInTables::$enabled = false;
    session()->forget([
        'igniterlabs_dinein.category_ids',
        'igniterlabs_dinein.locked',
        'igniterlabs_dinein.area_id',
        'igniterlabs_dinein.table_id',
        'igniterlabs_dinein.guest_count',
    ]);
});

it('hides every category outside the table menu', function(): void {
    $starters = Category::factory()->create(['name' => 'Starters', 'priority' => 1]);
    $mains = Category::factory()->create(['name' => 'Mains', 'priority' => 2]);
    $starter = Menu::factory()->create(['menu_name' => 'Soup']);
    $main = Menu::factory()->create(['menu_name' => 'Pie']);
    $starter->locations()->attach($this->location);
    $main->locations()->attach($this->location);
    $starter->categories()->sync([$starters->getKey()]);
    $main->categories()->sync([$mains->getKey()]);

    session(['igniterlabs_dinein.category_ids' => [(int)$starters->getKey()]]);

    $groups = MenuCatalog::grouped($this->location, 'delivery');

    expect(collect($groups)->pluck('id')->all())->toBe([(int)$starters->getKey()]);

    session()->forget('igniterlabs_dinein.category_ids');

    expect(MenuCatalog::grouped($this->location, 'delivery'))->toHaveCount(2);
});

it('refuses to leave dine-in while the table session is locked', function(): void {
    session(['igniterlabs_dinein.locked' => true]);

    expect(fn() => resolve(Fulfilment::class)->update('collection', true, null, null, null, null))
        ->toThrow(ApplicationException::class, 'alert.dinein_locked');

    $codes = collect(Schedules::fulfilmentOptions())->pluck('code');

    expect($codes->contains('delivery'))->toBeFalse()
        ->and($codes->contains('collection'))->toBeFalse();
});

it('exposes the floor plan and refuses a reserved table', function(): void {
    $area = DiningArea::factory()->create([
        'location_id' => $this->location->getKey(),
        'name' => 'Room',
        'floor_plan' => ['stage' => ['x' => 0, 'y' => 0, 'scaleX' => 1, 'scaleY' => 1]],
    ]);
    $free = DiningTable::factory()->create([
        'dining_area_id' => $area->getKey(),
        'name' => 'Free',
        'min_capacity' => 2,
        'max_capacity' => 4,
        'is_enabled' => true,
        'shape' => 'round',
        'seat_layout' => ['x' => 10, 'y' => 20, 'rotation' => 15],
    ]);
    $reserved = DiningTable::factory()->create([
        'dining_area_id' => $area->getKey(),
        'name' => 'Booked',
        'min_capacity' => 2,
        'max_capacity' => 4,
        'is_enabled' => true,
        'seat_layout' => ['x' => 40, 'y' => 20, 'rotation' => 0],
    ]);
    $reservation = Reservation::factory()->create([
        'location_id' => $this->location->getKey(),
        'status_id' => 5,
        'reserve_date' => now()->toDateString(),
        'reserve_time' => now()->format('H:i:s'),
        'duration' => 120,
        'guest_num' => 2,
    ]);
    $reservation->tables()->sync([$reserved->getKey()]);

    $listed = DineInTables::present($this->location);
    $tables = collect($listed['tables'])->keyBy('name');

    expect($listed['areas'][0]['floorPlan'])->not->toBeNull()
        ->and($tables['Free']['available'])->toBeTrue()
        ->and($tables['Free']['seat'])->toMatchArray(['x' => 10.0, 'y' => 20.0, 'rotation' => 15.0, 'shape' => 'round'])
        ->and($tables['Booked']['available'])->toBeFalse();

    expect(fn(): bool => DineInTables::remember((int)$this->location->getKey(), (int)$area->getKey(), (int)$reserved->getKey()))
        ->toThrow(ApplicationException::class, 'alert.table_unavailable');

    expect(DineInTables::remember((int)$this->location->getKey(), (int)$area->getKey(), (int)$free->getKey()))->toBeTrue()
        ->and(session('igniterlabs_dinein.table_id'))->toBe((int)$free->getKey());
});
