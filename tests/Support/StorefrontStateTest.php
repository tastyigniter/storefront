<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\CartItem;
use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Facades\Cart;
use Igniter\Cart\Models\CartSettings;
use Igniter\Cart\Models\Menu;
use Igniter\Storefront\Support\Money;
use Igniter\Storefront\Support\StorefrontState;
use Igniter\User\Models\Address;
use RuntimeException;

beforeEach(function(): void {
    $this->location = currentLocation();
});

it('shares the signed-in customer with their addresses', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create([
        'customer_id' => $customer->getKey(),
        'address_1' => '1 Test Street',
        'address_2' => null,
        'city' => 'Leeds',
        'postcode' => 'LS1',
    ]);

    $shared = StorefrontState::shared();

    expect($shared['customer']['id'])->toBe($customer->getKey())
        ->and($shared['customer']['addresses'][0])->toMatchArray([
            'id' => $address->getKey(),
            'label' => '1 Test Street, Leeds, LS1',
        ])
        ->and($shared['location']['id'])->toBe($this->location->getKey())
        ->and($shared['location']['image'])->toBeNull();
});

it('shares fixed tip presets and the tip applied', function(): void {
    CartSettings::set([
        'enable_tipping' => 1,
        'tip_value_type' => 'F',
        'tip_amounts' => [['value' => 2, 'priority' => 1], ['value' => 5, 'priority' => 2]],
    ]);
    addMenuToCart();
    resolve(CartManager::class)->applyCondition('tip', ['amount' => 5, 'isCustom' => false]);

    $tip = StorefrontState::shared('cart.tip');

    expect($tip)->toBe([
        'percentage' => false,
        'presets' => [
            ['value' => 2.0, 'label' => Money::label(2)],
            ['value' => 5.0, 'label' => Money::label(5)],
        ],
        'amount' => 5.0,
        'isCustom' => false,
    ]);
});

it('labels percentage tip presets', function(): void {
    CartSettings::set([
        'enable_tipping' => 1,
        'tip_value_type' => 'P',
        'tip_amounts' => [['value' => 12.5, 'priority' => 1], ['value' => 10, 'priority' => 2]],
    ]);

    $presets = StorefrontState::shared('cart.tip.presets');

    expect(array_column($presets, 'label'))->toBe(['12.5%', '10%']);
});

it('describes basket lines with their options', function(): void {
    $menu = Menu::factory()->create();
    resolve(CartManager::class)->cartInstance($this->location->getKey());
    Cart::add([
        'id' => $menu->getKey(),
        'name' => 'Burger',
        'price' => 10,
        'comment' => 'No ice',
        'options' => [[
            'id' => 7,
            'name' => 'Drinks',
            'values' => [
                ['id' => 70, 'name' => 'Coke', 'price' => 1, 'qty' => 2],
                ['id' => 71, 'name' => 'Fanta', 'price' => 1],
            ],
        ]],
    ], 1);

    $line = StorefrontState::shared('cart.lines.0');

    expect($line)->toMatchArray([
        'menuId' => $menu->getKey(),
        'name' => 'Burger',
        'qty' => 1,
        'comment' => 'No ice',
        'selected' => [7 => [['id' => 70, 'qty' => 2], ['id' => 71, 'qty' => 1]]],
        'options' => [['name' => 'Drinks', 'values' => ['2× Coke', 'Fanta']]],
    ]);
});

it('reads plain option values too', function(): void {
    $item = new class(1, 'Burger', 10) extends CartItem
    {
        public function toArray(): array
        {
            return array_merge(parent::toArray(), [
                'options' => [(object)['id' => 3, 'name' => 'Sauce', 'values' => ['Ketchup', ['id' => 4, 'name' => 'Mayo']]]],
            ]);
        }
    };
    $line = (fn(CartItem $item): array => static::line($item))->bindTo(null, StorefrontState::class);

    expect($line($item))->toMatchArray([
        'selected' => [3 => [['id' => 0, 'qty' => 1], ['id' => 4, 'qty' => 1]]],
        'options' => [['name' => 'Sauce', 'values' => ['Ketchup', 'Mayo']]],
    ]);
});

it('shares an empty basket when the basket cannot be read', function(): void {
    app()->bind(CartManager::class, fn() => throw new RuntimeException('cart down'));

    expect(StorefrontState::shared('cart'))->toMatchArray([
        'count' => 0,
        'lines' => [],
        'checkout' => ['blocked' => true, 'reason' => 'Something went wrong. Please try again.', 'minimum' => null],
    ]);
});
