<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Models\CartSettings;
use Igniter\Cart\Models\Menu;
use Igniter\Coupons\Models\Coupon;
use Igniter\Storefront\Actions\UpdateCart;
use RuntimeException;

function cart()
{
    return resolve(CartManager::class)->getCart();
}

beforeEach(function(): void {
    currentLocation();
});

it('adds a dish to the basket', function(): void {
    $menu = Menu::factory()->create(['menu_price' => 12]);

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.store'), [
            'menu_id' => $menu->getKey(),
            'quantity' => 2,
            'comment' => 'No onions',
        ])
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHasNoErrors();

    $item = cart()->content()->first();
    expect($item->id)->toBe($menu->getKey())
        ->and($item->qty)->toBe(2)
        ->and($item->comment)->toBe('No onions');
});

it('updates a basket line when a row id is sent', function(): void {
    $menu = Menu::factory()->create(['menu_price' => 12]);
    $rowId = addMenuToCart($menu);

    $this->post(route('igniter.theme.cart.store'), [
        'menu_id' => $menu->getKey(),
        'row_id' => $rowId,
        'quantity' => 3,
    ])->assertSessionHasNoErrors();

    expect(cart()->count())->toBe(3)
        ->and(cart()->content())->toHaveCount(1);
});

it('shows an error when the dish cannot be added', function(): void {
    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.store'), ['menu_id' => 999999])
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHasErrors('form');
});

it('shows a general error when something unexpected fails', function(): void {
    $this->mock(UpdateCart::class)->shouldReceive('add')->andThrow(new RuntimeException('boom'));

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.store'), ['menu_id' => 1])
        ->assertSessionHasErrors(['form' => 'Something went wrong. Please try again.']);
});

it('returns a dish as json for the options modal', function(): void {
    $menu = Menu::factory()->create(['menu_name' => 'Jollof rice']);

    $this->getJson(route('igniter.theme.cart.item', ['menuId' => $menu->getKey()]))
        ->assertOk()
        ->assertJsonPath('id', $menu->getKey())
        ->assertJsonPath('name', 'Jollof rice');
});

it('returns 404 for a dish that does not exist', function(): void {
    $this->getJson(route('igniter.theme.cart.item', ['menuId' => 999999]))->assertNotFound();
});

it('steps a line quantity up and down', function(): void {
    $rowId = addMenuToCart(quantity: 2);

    $this->patch(route('igniter.theme.cart.update', ['rowId' => $rowId]), ['action' => 'plus'])->assertSessionHasNoErrors();
    expect(cart()->count())->toBe(3);

    $rowId = cart()->content()->first()->rowId;
    $this->patch(route('igniter.theme.cart.update', ['rowId' => $rowId]), ['action' => 'minus'])->assertSessionHasNoErrors();
    expect(cart()->count())->toBe(2);
});

it('removes a line when its quantity is set to zero', function(): void {
    $rowId = addMenuToCart();

    $this->patch(route('igniter.theme.cart.update', ['rowId' => $rowId]), ['quantity' => 0])->assertSessionHasNoErrors();

    expect(cart()->count())->toBe(0);
});

it('sets a line quantity and comment', function(): void {
    $rowId = addMenuToCart();

    $this->patch(route('igniter.theme.cart.update', ['rowId' => $rowId]), [
        'quantity' => 4,
        'comment' => 'Extra spicy',
    ])->assertSessionHasNoErrors();

    $item = cart()->content()->first();
    expect($item->qty)->toBe(4)
        ->and($item->comment)->toBe('Extra spicy');
});

it('leaves a line alone when nothing changes', function(): void {
    $rowId = addMenuToCart(quantity: 2);

    $this->patch(route('igniter.theme.cart.update', ['rowId' => $rowId]), [])->assertSessionHasNoErrors();

    expect(cart()->count())->toBe(2);
});

it('removes a line', function(): void {
    $rowId = addMenuToCart();

    $this->delete(route('igniter.theme.cart.destroy', ['rowId' => $rowId]))->assertSessionHasNoErrors();

    expect(cart()->count())->toBe(0);
});

it('shows an error when removing a line that is gone', function(): void {
    $this->delete(route('igniter.theme.cart.destroy', ['rowId' => 'missing']))->assertSessionHasErrors('form');
});

it('applies and clears a tip', function(): void {
    CartSettings::set(['enable_tipping' => 1, 'tip_value_type' => 'F']);
    addMenuToCart();

    $this->post(route('igniter.theme.cart.tip'), ['amount' => 5, 'custom' => true])->assertSessionHasNoErrors();

    $tip = cart()->getCondition('tip');
    expect($tip->getMetaData('amount'))->toEqual(5)
        ->and($tip->getMetaData('isCustom'))->toBeTrue();

    $this->post(route('igniter.theme.cart.tip'), ['amount' => 0, 'custom' => true])->assertSessionHasNoErrors();

    expect(cart()->getCondition('tip')->getMetaData('isCustom'))->toBeFalse();
});

it('validates the tip amount', function(): void {
    $this->post(route('igniter.theme.cart.tip'), ['amount' => -1])->assertSessionHasErrors('amount');
});

it('applies and removes a coupon', function(): void {
    Coupon::factory()->create(['code' => 'SAVE10', 'type' => 'F', 'discount' => 1, 'min_total' => 0]);
    addMenuToCart();

    $this->post(route('igniter.theme.cart.coupon'), ['code' => 'SAVE10'])->assertSessionHasNoErrors();
    expect(cart()->getCondition('coupon')->getMetaData('code'))->toBe('SAVE10');

    $this->delete(route('igniter.theme.cart.coupon.remove'))->assertSessionHasNoErrors();
    expect(cart()->getCondition('coupon')->getMetaData('code'))->toBeEmpty();
});

it('removes an applied gift card', function(): void {
    $giftup = cart()->getCondition('giftup');
    if ($giftup) {
        $giftup->setMetaData(['code' => 'GIFT100']);
    }

    $this->delete(route('igniter.theme.cart.gift-card.remove'))->assertSessionHasNoErrors();

    expect((string)(cart()->getCondition('giftup')?->getMetaData('code') ?? ''))->toBe('');
});
