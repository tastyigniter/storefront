<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\CancelConfirmation;
use Igniter\Storefront\Support\StorefrontRoutes;
use Igniter\User\Facades\Auth;
use Illuminate\Http\Request;

/**
 * Reorder and cancel, following the Orange order preview component.
 */
class OrderActionController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function reorder(string $hash): mixed
    {
        return $this->attempt(function() use ($hash) {
            $order = $this->processedOrder($hash);
            $cartManager = resolve(CartManager::class);

            // Restore into the cart of the location the order came from, then order from there.
            if ($order->location) {
                Location::setCurrent($order->location);
            }

            $currentInstance = $cartManager->getCart()->currentInstance();
            $cartManager->cartInstance((int)$order->location_id);
            $notes = $cartManager->restoreWithOrderMenus($order->getOrderMenus());
            $cartManager->getCart()->instance($currentInstance);

            if ($notes) {
                throw new ApplicationException(implode(' ', $notes));
            }

            return redirect()->to(StorefrontRoutes::order())
                ->with('success', sprintf(lang('igniter.cart::default.orders.alert_reorder_success'), $order->getKey()));
        });
    }

    public function cancel(Request $request, string $hash): mixed
    {
        return $this->attempt(function() use ($request, $hash) {
            $order = resolve(OrderManager::class)->getOrderByHash($hash);
            if (!$order instanceof Order || !$order->isPaymentProcessed()) {
                throw new ApplicationException(Storefront::lang('alert.order_not_found'));
            }

            CancelConfirmation::authorize(
                $order->customer_id ? (int)$order->customer_id : null,
                $request->input('email'),
                $order->email,
                Storefront::lang('alert.order_not_found'),
            );

            if ($order->isCanceled() || !$order->isCancelable() || !$order->markAsCanceled()) {
                throw new ApplicationException(lang('igniter.cart::default.orders.alert_cancel_failed'));
            }

            return back()->with('success', lang('igniter.cart::default.orders.alert_cancel_success'));
        });
    }

    protected function processedOrder(string $hash): Order
    {
        $customer = Auth::customer();
        $order = resolve(OrderManager::class)->getOrderByHash($hash, $customer);
        if (!$order instanceof Order || !$order->isPaymentProcessed()) {
            throw new ApplicationException(Storefront::lang('alert.order_not_found'));
        }

        // A guest link can reorder a guest order. A registered customer's order needs that customer.
        if ($order->customer_id && (int)$order->customer_id !== (int)($customer?->getKey() ?? 0)) {
            throw new ApplicationException(Storefront::lang('alert.order_not_found'));
        }

        return $order;
    }
}
