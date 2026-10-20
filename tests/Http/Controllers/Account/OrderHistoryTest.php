<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers\Account;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOption;
use Igniter\Cart\Models\MenuOption;
use Igniter\Cart\Models\Order;
use Igniter\Local\Models\ReviewSettings;
use Igniter\PayRegister\Models\Payment;
use Igniter\PayRegister\Payments\Cod;
use Igniter\Reservation\Models\Reservation;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\User\Models\Address;
use Inertia\Testing\AssertableInertia;

beforeEach(function(): void {
    $this->location = currentLocation();
    $this->customer = actingAsCustomer();
});

it("lists the customer's orders", function(): void {
    $order = paidOrder($this->customer, ['order_time' => '18:15:00']);
    paidOrder();

    $this->get(route('igniter.theme.account.orders'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Orders')
            ->has('orders', 1)
            ->where('orders.0.hash', $order->hash)
            ->where('orders.0.time', '18:15'));
});

it('shows an order with its options and totals', function(): void {
    $order = paidOrder($this->customer);
    $menu = Menu::factory()->create();
    $option = MenuItemOption::factory()->create([
        'menu_id' => $menu->getKey(),
        'option_id' => MenuOption::factory()->create(['option_name' => 'Drinks'])->getKey(),
    ]);
    $orderMenu = $order->menus()->create([
        'menu_id' => $menu->getKey(),
        'name' => 'Burger',
        'quantity' => 1,
        'price' => 10,
        'subtotal' => 10,
        'option_values' => [],
    ]);
    $order->menu_options()->createMany([
        ['order_menu_id' => $orderMenu->getKey(), 'order_option_name' => 'Coke', 'order_option_price' => 1, 'quantity' => 2, 'menu_option_id' => $option->getKey(), 'menu_option_value_id' => 1],
        ['order_menu_id' => $orderMenu->getKey(), 'order_option_name' => 'Fanta', 'order_option_price' => 1, 'quantity' => 1, 'menu_option_id' => $option->getKey(), 'menu_option_value_id' => 2],
    ]);
    $order->addOrderTotals([
        ['code' => 'total', 'title' => 'Order Total', 'value' => 12, 'priority' => 127],
        ['code' => 'tip', 'title' => 'Tip', 'value' => 2, 'priority' => 8],
        ['code' => 'subtotal', 'title' => 'Sub Total', 'value' => 10, 'priority' => 0],
    ]);

    $this->get(route('igniter.theme.account.order', ['hash' => $order->hash]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Order')
            ->where('order.menus.0.name', 'Burger')
            ->where('order.menus.0.options.0.values', ['2× Coke', 'Fanta'])
            ->where('order.totals.0.title', 'Sub Total')
            ->where('order.totals.1.title', 'Tip')
            ->where('order.totals.2.title', 'Order Total')
            ->where('meta.title', 'Order #'.$order->getKey().' - '.setting('site_name', 'Restaurant'))
            ->where('order.canReorder', true)
            ->where('order.canCancel', false));
});

it('offers a review on the account order', function(): void {
    ReviewSettings::set('allow_reviews', 1);
    $order = paidOrder($this->customer);

    $this->get(route('igniter.theme.account.order', ['hash' => $order->hash]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('review.type', 'order')
            ->where('review.hash', $order->hash)
            ->where('review.signedIn', true));
});

it('shows delivery, payment and contact details on the account order', function(): void {
    Payment::create(['name' => 'Cash on delivery', 'code' => 'glass_cod', 'class_name' => Cod::class, 'status' => 1]);
    $this->location->update(['location_telephone' => '020 7946 0000', 'location_address_1' => '2 Market Row', 'location_city' => 'London']);
    $address = Address::factory()->create(['customer_id' => $this->customer->getKey(), 'address_1' => '1 High Street', 'city' => 'Leeds']);
    $order = paidOrder($this->customer, [
        'order_type' => 'delivery',
        'address_id' => $address->getKey(),
        'payment' => 'glass_cod',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
        'telephone' => '0123',
        'comment' => 'Leave at the door',
    ]);

    $this->get(route('igniter.theme.account.order', ['hash' => $order->hash]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('order.payment', 'Cash on delivery')
            ->where('order.deliveryAddress', fn(string $value): bool => str_contains($value, '1 High Street'))
            ->where('order.placed.date', $order->created_at->toDateString())
            ->where('order.progress', 1)
            ->where('order.comment', 'Leave at the door')
            ->where('contact', ['name' => 'Jane Doe', 'email' => 'jane@example.test', 'telephone' => '0123'])
            ->where('restaurant.address', '2 Market Row, London')
            ->where('restaurant.telephone', '020 7946 0000')
            ->where('restaurant.orderUrl', route('igniter.theme.order', ['location' => $this->location->permalink_slug])));
});

it('leaves the address off collection orders and the restaurant off orders without a location', function(): void {
    $order = paidOrder($this->customer, ['order_type' => 'collection']);

    $this->get(route('igniter.theme.account.order', ['hash' => $order->hash]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('order.deliveryAddress', null)
            ->where('order.payment', null));

    $presenter = new class
    {
        use PresentsStorefront;

        public function contact(Order $order): array
        {
            return $this->orderContactPayload($order);
        }
    };
    $order->setRelation('location', null);

    expect($presenter->contact($order)['restaurant'])->toBeNull();
});

it('pages the order and reservation history', function(): void {
    setThemeSettings(['page_limit' => 2]);
    $orders = collect(range(1, 3))->map(fn(): mixed => paidOrder($this->customer));
    Reservation::factory()->count(3)->create(['customer_id' => $this->customer->getKey(), 'location_id' => $this->location->getKey()]);

    $this->get(route('igniter.theme.account.orders', ['page' => 2]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->has('orders', 1)
            ->where('orders.0.hash', $orders->first()->hash)
            ->where('pagination.currentPage', 2)
            ->where('pagination.lastPage', 2)
            ->where('pagination.total', 3));

    $this->get(route('igniter.theme.account.reservations'))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->has('reservations', 2)
            ->where('pagination.lastPage', 2));
});

it("returns 404 for another customer's order", function(): void {
    $order = paidOrder();

    $this->get(route('igniter.theme.account.order', ['hash' => $order->hash]))->assertNotFound();
});

it("lists the customer's reservations", function(): void {
    $reservation = Reservation::factory()->create([
        'customer_id' => $this->customer->getKey(),
        'location_id' => $this->location->getKey(),
        'reserve_time' => '20:00:00',
    ]);

    $this->get(route('igniter.theme.account.reservations'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Reservations')
            ->has('reservations', 1)
            ->where('reservations.0.hash', $reservation->hash)
            ->where('reservations.0.time', '20:00'));
});

it('shows a reservation', function(): void {
    $reservation = Reservation::factory()->create([
        'customer_id' => $this->customer->getKey(),
        'location_id' => $this->location->getKey(),
        'telephone' => '0123',
    ]);

    $this->get(route('igniter.theme.account.reservation', ['hash' => $reservation->hash]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Reservation')
            ->where('reservation.telephone', '0123')
            ->where('meta.title', 'Reservation #'.$reservation->getKey().' - '.setting('site_name', 'Restaurant'))
            ->where('reservation.location', $this->location->location_name));
});

it("returns 404 for another customer's reservation", function(): void {
    $reservation = Reservation::factory()->create(['location_id' => $this->location->getKey()]);

    $this->get(route('igniter.theme.account.reservation', ['hash' => $reservation->hash]))->assertNotFound();
});
