<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Models\Order;
use Igniter\PayRegister\Models\Payment;
use Igniter\PayRegister\Models\PaymentProfile;
use Igniter\PayRegister\Payments\AuthorizeNetAim;
use Igniter\PayRegister\Payments\Cod;
use Igniter\PayRegister\Payments\Square;
use Igniter\PayRegister\Payments\Stripe;
use Igniter\User\Models\Customer;
use Igniter\Storefront\Support\PaymentClients;
use Mockery;

function paymentWith(object $gateway, ?PaymentProfile $profile = null): Payment
{
    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->shouldReceive('getGatewayObject')->andReturn($gateway);
    $payment->shouldReceive('paymentProfileExists')->andReturn($profile instanceof PaymentProfile);
    $payment->shouldReceive('findPaymentProfile')->andReturn($profile);

    return $payment;
}

function gateway(string $class, array $methods): object
{
    $gateway = Mockery::mock($class);
    $gateway->shouldReceive('getHiddenFields')->andReturn(['token' => '']);
    $gateway->shouldReceive('supportsPaymentProfiles')->andReturnTrue();
    foreach ($methods as $method => $value) {
        $gateway->shouldReceive($method)->andReturn($value);
    }

    return $gateway;
}

beforeEach(function(): void {
    currentLocation();
    $this->order = Order::factory()->create(['customer_id' => null, 'order_total' => 12.5]);
});

it('has no card form for other gateways', function(): void {
    expect(PaymentClients::config(paymentWith(new Cod), $this->order))->toBeNull();
});

it('has no card form for Stripe Checkout', function(): void {
    $payment = paymentWith(gateway(Stripe::class, ['isOffsiteMode' => true]));

    expect(PaymentClients::type($payment))->toBeNull();
});

it('configures the Stripe card form', function(): void {
    $payment = paymentWith(gateway(Stripe::class, [
        'isOffsiteMode' => false,
        'getPublishableKey' => 'pk_test',
        'createOrFetchIntent' => 'pi_secret',
        'getStripeJsOptions' => ['locale' => 'en'],
    ]));

    expect(PaymentClients::config($payment, $this->order))->toBe([
        'type' => 'stripe',
        'hidden' => ['token' => ''],
        'profile' => null,
        'canSaveCard' => false,
        'publishableKey' => 'pk_test',
        'clientSecret' => 'pi_secret',
        'options' => ['locale' => 'en'],
    ]);
});

it('offers the saved card instead of a new payment intent', function(): void {
    $customer = Customer::factory()->create();
    $this->order->customer_id = $customer->getKey();
    $this->order->save();
    $profile = new PaymentProfile(['card_brand' => 'visa', 'card_last4' => '4242']);
    $payment = paymentWith(gateway(Stripe::class, [
        'isOffsiteMode' => false,
        'getPublishableKey' => 'pk_test',
        'getStripeJsOptions' => [],
    ]), $profile);

    expect(PaymentClients::config($payment, $this->order->fresh()))->toMatchArray([
        'profile' => ['brand' => 'visa', 'last4' => '4242'],
        'canSaveCard' => true,
        'clientSecret' => null,
    ]);
});

it('configures the Square card form', function(): void {
    $payment = paymentWith(gateway(Square::class, [
        'isTestMode' => true,
        'getAppId' => 'sq-app',
        'getLocationId' => 'sq-location',
    ]));

    expect(PaymentClients::config($payment, $this->order))->toMatchArray([
        'type' => 'square',
        'sdkUrl' => 'https://sandbox.web.squarecdn.com/v1/square.js',
        'applicationId' => 'sq-app',
        'locationId' => 'sq-location',
        'amount' => '12.50',
    ]);
});

it('configures the Authorize.Net card form', function(): void {
    $payment = paymentWith(gateway(AuthorizeNetAim::class, [
        'getEndPoint' => 'https://jstest.authorize.net/v1/Accept.js',
        'getApiLoginID' => 'login',
        'getClientKey' => 'client-key',
    ]));

    expect(PaymentClients::config($payment, $this->order))->toMatchArray([
        'type' => 'authorizenet',
        'endpoint' => 'https://jstest.authorize.net/v1/Accept.js',
        'apiLoginId' => 'login',
        'clientKey' => 'client-key',
    ]);
});
