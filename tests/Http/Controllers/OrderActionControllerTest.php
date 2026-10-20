<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Order;
use Igniter\User\Models\Customer;

beforeEach(function(): void {
    $this->travelTo(now()->setTime(12, 0));
    $this->location = currentLocation();
    $this->customer = actingAsCustomer();
});

function orderWithMenu(Order $order, ?Menu $menu): void
{
    $order->menus()->create([
        'menu_id' => $menu?->getKey() ?? 999999,
        'name' => $menu?->menu_name ?? 'Removed dish',
        'quantity' => 2,
        'price' => 10,
        'subtotal' => 20,
        'option_values' => [],
        'comment' => 'Well done',
    ]);
}

it('puts the dishes from a past order back in the basket', function(): void {
    $menu = Menu::factory()->create(['menu_price' => 10]);
    $order = paidOrder($this->customer);
    orderWithMenu($order, $menu);

    $this->post(route('igniter.theme.order.reorder', ['hash' => $order->hash]))
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHas('success');

    $cart = resolve(CartManager::class)->cartInstance($this->location->getKey());
    expect($cart->content()->first()->id)->toBe($menu->getKey())
        ->and($cart->count())->toBe(2);
});

it('explains dishes that could not be reordered', function(): void {
    $order = paidOrder($this->customer);
    orderWithMenu($order, null);

    $this->from(route('igniter.theme.account.orders'))
        ->post(route('igniter.theme.order.reorder', ['hash' => $order->hash]))
        ->assertSessionHasErrors('form');
});

it('reorders even when the order location is gone', function(): void {
    $order = paidOrder($this->customer);
    $order->location_id = 999999;
    $order->saveQuietly();

    $this->post(route('igniter.theme.order.reorder', ['hash' => $order->hash]))
        ->assertRedirect(route('igniter.theme.order'));
});

it("will not reorder another customer's order", function(): void {
    auth('igniter-customer')->logout();
    $order = paidOrder(Customer::factory()->create());

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.order.reorder', ['hash' => $order->hash]))
        ->assertSessionHasErrors(['form' => lang('storefront.fixture::default.alert.order_not_found')]);
});

it('reorders a guest order from its link', function(): void {
    auth('igniter-customer')->logout();
    $menu = Menu::factory()->create(['menu_price' => 10]);
    $order = paidOrder();
    orderWithMenu($order, $menu);

    $this->post(route('igniter.theme.order.reorder', ['hash' => $order->hash]))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('will not act on an unpaid order', function(): void {
    $order = paidOrder($this->customer, ['processed' => 0]);

    $this->from(route('igniter.theme.account.orders'))
        ->post(route('igniter.theme.order.reorder', ['hash' => $order->hash]))
        ->assertSessionHasErrors(['form' => 'That order could not be found.']);
});

it('cancels an order inside the cancellation window', function(): void {
    $this->location->settings()->create(['item' => 'delivery', 'data' => ['cancellation_timeout' => 10]]);
    $order = paidOrder($this->customer, [
        'order_type' => 'delivery',
        'order_date' => now()->toDateString(),
        'order_time' => now()->addHour()->format('H:i:s'),
    ]);

    $this->from(route('igniter.theme.account.order', ['hash' => $order->hash]))
        ->post(route('igniter.theme.order.cancel', ['hash' => $order->hash]))
        ->assertSessionHas('success', lang('igniter.cart::default.orders.alert_cancel_success'));

    expect($order->fresh()->isCanceled())->toBeTrue();
});

it('asks a guest to confirm the order email before cancelling', function(): void {
    $this->location->settings()->create(['item' => 'delivery', 'data' => ['cancellation_timeout' => 10]]);
    $order = paidOrder(null, [
        'email' => 'guest@example.test',
        'order_type' => 'delivery',
        'order_date' => now()->toDateString(),
        'order_time' => now()->addHour()->format('H:i:s'),
    ]);

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.order.cancel', ['hash' => $order->hash]))
        ->assertSessionHasErrors(['email' => lang('storefront.fixture::default.alert.cancel_email_mismatch')]);

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.order.cancel', ['hash' => $order->hash]), ['email' => 'wrong@example.test'])
        ->assertSessionHasErrors(['email' => lang('storefront.fixture::default.alert.cancel_email_mismatch')]);

    expect($order->fresh()->isCanceled())->toBeFalse();

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.order.cancel', ['hash' => $order->hash]), ['email' => 'Guest@Example.test'])
        ->assertSessionHas('success', lang('igniter.cart::default.orders.alert_cancel_success'));

    expect($order->fresh()->isCanceled())->toBeTrue();
});

it("will not cancel another customer's order by repeating their email", function(): void {
    $this->location->settings()->create(['item' => 'delivery', 'data' => ['cancellation_timeout' => 10]]);
    $other = Customer::factory()->create();
    $order = paidOrder($other, [
        'email' => 'other@example.test',
        'order_type' => 'delivery',
        'order_date' => now()->toDateString(),
        'order_time' => now()->addHour()->format('H:i:s'),
    ]);

    $this->from(route('igniter.theme.home'))
        ->post(route('igniter.theme.order.cancel', ['hash' => $order->hash]), ['email' => 'other@example.test'])
        ->assertSessionHasErrors(['form' => lang('storefront.fixture::default.alert.order_not_found')]);

    expect($order->fresh()->isCanceled())->toBeFalse();
});

it('refuses to cancel an order outside the cancellation window', function(): void {
    $order = paidOrder($this->customer);

    $this->from(route('igniter.theme.account.order', ['hash' => $order->hash]))
        ->post(route('igniter.theme.order.cancel', ['hash' => $order->hash]))
        ->assertSessionHasErrors(['form' => lang('igniter.cart::default.orders.alert_cancel_failed')]);
});

it('reports a missing or unpaid order when cancelling', function(): void {
    $order = paidOrder($this->customer, ['processed' => 0]);

    foreach (['missing', $order->hash] as $hash) {
        $this->from(route('igniter.theme.home'))
            ->post(route('igniter.theme.order.cancel', ['hash' => $hash]))
            ->assertSessionHasErrors(['form' => lang('storefront.fixture::default.alert.order_not_found')]);
    }
});
