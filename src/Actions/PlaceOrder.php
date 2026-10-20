<?php

declare(strict_types=1);

namespace Igniter\Storefront\Actions;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\CheckoutStatus;
use Igniter\Storefront\Support\DeliveryAddress;
use Igniter\User\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;

class PlaceOrder
{
    public function handle(array $data): mixed
    {
        if (!$this->canCheckout()) {
            return redirect()->guest(route('igniter.theme.account.login'));
        }

        [$order, $data] = $this->prepare($data);

        // Offsite gateways (Stripe Checkout, PayPal, Mollie) send the customer back with
        // page_url($successPage / $cancelPage). Orange passes theme page names; Glass has no
        // theme pages, so pass paths, which page_url returns unchanged.
        $data['successPage'] = 'checkout/success/'.$order->hash;
        $data['cancelPage'] = 'checkout';

        $result = resolve(OrderManager::class)->processPayment($order->refresh(), $data);

        if ($result instanceof RedirectResponse) {
            return Inertia::location($result->getTargetUrl());
        }

        return redirect()->route('igniter.theme.checkout.success', ['hash' => $order->hash]);
    }

    /**
     * Validate the cart and save the order without taking payment.
     * Card gateways call this before the browser confirms the card.
     *
     * @return array{0: Order, 1: array}
     */
    public function prepare(array $data): array
    {
        $data = $this->withDeliveryAddress($data);

        $cartManager = resolve(CartManager::class);
        $cartManager->validateContents();
        $cartManager->validateLocation();
        $cartManager->validateOrderTime();

        $status = CheckoutStatus::current();
        if ($status['blocked']) {
            throw new ApplicationException($status['reason'] ?? Storefront::lang('alert.basket_empty'));
        }

        $orders = resolve(OrderManager::class);
        if (!empty($data['payment'])) {
            $orders->applyCurrentPaymentFee($data['payment']);
        }

        $order = $orders->getOrder();

        // Same hook the Orange checkout fires, so extensions can validate their own fields.
        Event::dispatch('igniter.orange.validateCheckout', [$data, $order]);

        unset($data['termsAgreed']);
        $orders->saveOrder($order, $data);

        return [$order, $data];
    }

    public function canCheckout(): bool
    {
        $location = Location::current() ?? Location::currentOrDefault();

        return Auth::customer() || ($location instanceof LocationModel && $location->allowGuestOrder());
    }

    /**
     * Delivery orders use the address chosen in the order details modal.
     */
    protected function withDeliveryAddress(array $data): array
    {
        unset($data['address_id'], $data['address_1'], $data['address_2'], $data['city'], $data['state'], $data['postcode'], $data['country_id']);

        if (!Location::orderTypeIsDelivery()) {
            return $data;
        }

        $address = DeliveryAddress::current();
        if (!$address) {
            throw new ApplicationException(Storefront::lang('alert.add_delivery_address'));
        }

        return array_merge($data, $address);
    }
}
