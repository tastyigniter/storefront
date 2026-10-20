<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Models\Ingredient;
use Igniter\Cart\Models\Mealtime;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOption;
use Igniter\Cart\Models\MenuItemOptionValue;
use Igniter\Cart\Models\MenuOption;
use Igniter\Cart\Models\MenuOptionValue;
use Igniter\Storefront\Support\MenuCatalog;

beforeEach(function(): void {
    $this->location = currentLocation();
});

it('puts dishes without a category in a menu group', function(): void {
    $menu = Menu::factory()->create(['menu_name' => 'Loose dish']);
    $menu->locations()->attach($this->location);

    $groups = MenuCatalog::grouped($this->location, 'delivery', 'loose');

    expect($groups)->toHaveCount(1)
        ->and($groups[0])->toMatchArray(['id' => 0, 'name' => 'Menu', 'slug' => 'menu'])
        ->and($groups[0]['items'][0]['name'])->toBe('Loose dish');
});

it('describes dish options, allergens and mealtimes', function(): void {
    $menu = Menu::factory()->create(['menu_description' => '<p>Big &amp; tasty</p>', 'minimum_qty' => 0]);
    $nuts = Ingredient::factory()->create(['name' => 'Nuts', 'is_allergen' => true, 'status' => true]);
    $salt = Ingredient::factory()->create(['name' => 'Salt', 'is_allergen' => false, 'status' => true]);
    $menu->ingredients()->attach([$nuts->getKey(), $salt->getKey()]);
    $menu->mealtimes()->attach([
        Mealtime::factory()->create(['mealtime_name' => 'Brunch', 'start_time' => '10:00', 'end_time' => '12:00', 'mealtime_status' => true])->getKey(),
        Mealtime::factory()->create(['mealtime_name' => 'Hidden', 'mealtime_status' => false])->getKey(),
    ]);

    $option = MenuOption::factory()->create(['option_name' => 'Sauce', 'display_type' => 'checkbox']);
    $ketchup = MenuOptionValue::factory()->create(['option_id' => $option->getKey(), 'name' => 'Ketchup', 'price' => 0]);
    $satay = MenuOptionValue::factory()->create(['option_id' => $option->getKey(), 'name' => 'Satay', 'price' => 1.5]);
    $satay->ingredients()->attach($nuts->getKey());
    $itemOption = MenuItemOption::factory()->create([
        'menu_id' => $menu->getKey(),
        'option_id' => $option->getKey(),
        'is_required' => true,
        'min_selected' => 1,
        'max_selected' => 2,
    ]);
    MenuItemOptionValue::factory()->create(['menu_option_id' => $itemOption->getKey(), 'option_value_id' => $ketchup->getKey()]);
    MenuItemOptionValue::factory()->create(['menu_option_id' => $itemOption->getKey(), 'option_value_id' => $satay->getKey()]);

    $item = MenuCatalog::find($this->location, $menu->getKey());

    expect($item)->toMatchArray([
        'description' => 'Big & tasty',
        'ingredients' => ['Nuts', 'Salt'],
        'allergens' => ['Nuts'],
        'minimumQty' => 1,
        'mealtimes' => [['name' => 'Brunch', 'description' => 'daily 10:00 am - 12:00 pm']],
    ])
        ->and($item['options'][0])->toMatchArray([
            'name' => 'Sauce',
            'displayType' => 'checkbox',
            'required' => true,
            'minSelected' => 1,
            'maxSelected' => 2,
        ])
        ->and($item['options'][0]['values'][0])->toMatchArray(['name' => 'Ketchup', 'priceLabel' => '', 'allergens' => []])
        ->and($item['options'][0]['values'][1])->toMatchArray(['name' => 'Satay', 'price' => 1.5, 'allergens' => ['Nuts']]);
});

it('finds nothing for a disabled dish', function(): void {
    $menu = Menu::factory()->create(['menu_status' => false]);

    expect(MenuCatalog::find($this->location, $menu->getKey()))->toBeNull();
});
