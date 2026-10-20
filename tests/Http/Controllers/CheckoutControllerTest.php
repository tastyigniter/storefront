<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\PayRegister\Models\Payment;
use Igniter\PayRegister\Payments\Cod;
use Igniter\System\Models\Country;
use Igniter\System\Models\Settings;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

afterEach(function(): void {
    Event::forget('igniter.orange.validateCheckout');
});

function checkoutDetails(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
        'telephone' => '07700 900000',
        'comment' => 'Ring the bell',
        'termsAgreed' => 1,
        'payment' => 'cod',
    ], $overrides);
}

/**
 * Wrap the real order manager so single methods can be stubbed.
 */
function spyOrderManager(): MockInterface
{
    $manager = Mockery::mock(resolve(OrderManager::class))->makePartial();
    app()->instance(OrderManager::class, $manager);

    return $manager;
}

beforeEach(function(): void {
    $this->travelTo(now()->setTime(12, 0));
    $this->location = currentLocation();
    Location::updateOrderType(LocationModel::COLLECTION);
});

it('shows the checkout page', function(): void {
    addMenuToCart();

    $this->get(route('igniter.theme.checkout.checkout'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Checkout/Index')
            ->where('fields.0.name', 'first_name')
            ->where('fields.0.required', true)
            ->where('payments.0.code', 'cod')
            ->where('selectedPayment', 'cod')
            ->where('delivery', false)
            ->has('fulfilmentOptions', 2));
});

it('preselects the payment chosen earlier in the session', function(): void {
    // An empty basket has nothing to pay, which clears the payment.
    addMenuToCart();
    Payment::factory()->create(['code' => 'other', 'class_name' => Cod::class, 'priority' => 99]);
    resolve(OrderManager::class)->setCurrentPaymentCode('other');

    $this->get(route('igniter.theme.checkout.checkout'))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('selectedPayment', 'other'));
});

it('sends guests to sign in when guest orders are off', function(): void {
    Settings::set('guest_order', 0);

    $this->get(route('igniter.theme.checkout.checkout'))
        ->assertRedirect(route('igniter.theme.account.login'));
});

it('sends customers home when there is no location', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    $this->get(route('igniter.theme.checkout.checkout'))
        ->assertRedirect(route('igniter.theme.home'))
        ->assertSessionHas('error', 'Choose a location before checkout.');
});

it('shows the receipt when the session order is already paid', function(): void {
    $order = Order::factory()->create([
        'customer_id' => null,
        'location_id' => $this->location->getKey(),
        'processed' => 1,
        'status_id' => 1,
    ]);
    resolve(OrderManager::class)->setCurrentOrderId($order->getKey());

    $this->get(route('igniter.theme.checkout.checkout'))
        ->assertRedirect(route('igniter.theme.checkout.success', ['hash' => $order->hash]));
});

it('selects a payment method', function(): void {
    addMenuToCart();

    $this->postJson(route('igniter.theme.checkout.payment'), ['payment' => 'cod'])
        ->assertOk()
        ->assertJsonPath('client', null);

    expect(resolve(OrderManager::class)->loadOrder()->payment)->toBe('cod');
});

it('saves the payment on an order with nothing to pay yet', function(): void {
    $this->postJson(route('igniter.theme.checkout.payment'), ['payment' => 'cod'])->assertOk();

    expect(resolve(OrderManager::class)->loadOrder()->getOriginal('payment'))->toBe('cod');
});

it('rejects an unknown payment method', function(): void {
    $this->postJson(route('igniter.theme.checkout.payment'), ['payment' => 'nope'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'That payment method is not available.');
});

it('reports a payment method that fails to load', function(): void {
    spyOrderManager()->shouldReceive('applyCurrentPaymentFee')->andThrow(new RuntimeException('down'));

    $this->postJson(route('igniter.theme.checkout.payment'), ['payment' => 'cod'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This payment method could not be loaded. Try another one.');
});

it('will not delete a saved card that does not exist', function(): void {
    actingAsCustomer();

    $this->postJson(route('igniter.theme.checkout.payment-profile.delete'), ['payment' => 'cod'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'That saved card could not be found.');
});

it('deletes a saved card', function(): void {
    $customer = actingAsCustomer();
    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->shouldReceive('paymentProfileExists')->with(Mockery::on(fn($value) => $value->is($customer)))->andReturnTrue();
    $payment->shouldReceive('deletePaymentProfile')->once();
    $payment->shouldReceive('getGatewayObject')->andReturn(new Cod);
    spyOrderManager()->shouldReceive('getPayment')->with('cod')->andReturn($payment);

    $this->postJson(route('igniter.theme.checkout.payment-profile.delete'), ['payment' => 'cod'])
        ->assertOk()
        ->assertJsonPath('client', null);
});

it('reports a saved card that cannot be deleted', function(): void {
    actingAsCustomer();
    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->shouldReceive('paymentProfileExists')->andReturnTrue();
    $payment->shouldReceive('deletePaymentProfile')->andThrow(new RuntimeException('gateway down'));
    spyOrderManager()->shouldReceive('getPayment')->andReturn($payment);

    $this->postJson(route('igniter.theme.checkout.payment-profile.delete'), ['payment' => 'cod'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The saved card could not be removed. Please try again.');
});

it('prepares the order before a card is charged', function(): void {
    addMenuToCart();

    $this->postJson(route('igniter.theme.checkout.prepare'), checkoutDetails())
        ->assertOk()
        ->assertJsonPath('client', null);

    expect(resolve(OrderManager::class)->loadOrder())
        ->first_name->toBe('Jane')
        ->payment->toBe('cod');
});

it('does not prepare an order for a guest when guest orders are off', function(): void {
    Settings::set('guest_order', 0);

    $this->postJson(route('igniter.theme.checkout.prepare'), checkoutDetails())
        ->assertForbidden()
        ->assertJsonPath('message', 'Sign in to place your order.');
});

it('explains why an order cannot be prepared', function(): void {
    $this->postJson(route('igniter.theme.checkout.prepare'), checkoutDetails())
        ->assertStatus(422)
        ->assertJsonPath('message', lang('igniter.cart::default.checkout.alert_no_menu_to_order'));
});

it('hides unexpected errors while preparing an order', function(): void {
    addMenuToCart();
    Event::listen('igniter.orange.validateCheckout', fn() => throw new RuntimeException('extension failed'));

    $this->postJson(route('igniter.theme.checkout.prepare'), checkoutDetails())
        ->assertStatus(422)
        ->assertJsonPath('message', 'Something went wrong. Please try again.');
});

it('validates the checkout form', function(): void {
    $this->postJson(route('igniter.theme.checkout.prepare'), ['termsAgreed' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'telephone', 'termsAgreed']);
});

it('places a cash order and shows the receipt', function(): void {
    addMenuToCart();

    $response = $this->post(route('igniter.theme.checkout.store'), checkoutDetails());

    $order = Order::query()->where('email', 'jane@example.test')->latest('order_id')->first();
    $response->assertRedirect(route('igniter.theme.checkout.success', ['hash' => $order->hash]));
    expect($order->isPaymentProcessed())->toBeTrue();
});

it('sends the customer to an offsite gateway', function(): void {
    addMenuToCart();
    spyOrderManager()->shouldReceive('processPayment')
        ->withArgs(fn(Order $order, array $data): bool => $data['successPage'] === 'checkout/success/'.$order->hash
            && $data['cancelPage'] === 'checkout')
        ->andReturn(redirect()->away('https://pay.example.test/session'));

    $this->post(route('igniter.theme.checkout.store'), checkoutDetails())
        ->assertRedirect('https://pay.example.test/session');
});

it('sends guests to sign in when placing an order with guest orders off', function(): void {
    Settings::set('guest_order', 0);

    $this->post(route('igniter.theme.checkout.store'), checkoutDetails())
        ->assertRedirect(route('igniter.theme.account.login'));
});

it('asks for a delivery address on delivery orders', function(): void {
    Location::updateOrderType(LocationModel::DELIVERY);
    addMenuToCart();

    $this->from(route('igniter.theme.checkout.checkout'))
        ->post(route('igniter.theme.checkout.store'), checkoutDetails())
        ->assertSessionHasErrors(['form' => 'Add your delivery address in the order details to continue.']);
});

it('uses the delivery address from the order details', function(): void {
    Location::updateOrderType(LocationModel::DELIVERY);
    Location::putSession('searchQuery', '1 Test Street');
    Settings::set('country_id', Country::query()->value('country_id'));
    addMenuToCart();

    $this->post(route('igniter.theme.checkout.store'), checkoutDetails())->assertSessionHasNoErrors();

    $order = Order::query()->where('email', 'jane@example.test')->latest('order_id')->first();
    expect($order->address?->address_1)->toBe('1 Test Street');
});

it('shows the order receipt', function(): void {
    $customer = actingAsCustomer();
    $order = Order::factory()->create([
        'customer_id' => $customer->getKey(),
        'location_id' => $this->location->getKey(),
        'order_total' => 25,
    ]);

    $this->get(route('igniter.theme.checkout.success', ['hash' => $order->hash]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Checkout/Success')
            ->where('order.hash', $order->hash)
            ->where('order.location', $this->location->location_name)
            ->where('meta.title', 'Order #'.$order->getKey().' confirmed - '.setting('site_name', 'Restaurant'))
            ->missing('review'));
});

it('returns 404 for an unknown receipt', function(): void {
    $this->get(route('igniter.theme.checkout.success', ['hash' => 'missing']))->assertNotFound();
});
