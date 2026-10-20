<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Exception\FlashException;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Actions\PlaceOrder;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\PlaceOrderRequest;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\CheckoutFields;
use Igniter\Storefront\Support\GroupOrders;
use Igniter\Storefront\Support\PageMeta;
use Igniter\Storefront\Support\PaymentClients;
use Igniter\Storefront\Support\Schedules;
use Igniter\User\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CheckoutController
{
    use PresentsStorefront;
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(): Response|RedirectResponse
    {
        if (($redirect = $this->redirectWhenSessionOrderPaid()) instanceof RedirectResponse) {
            return $redirect;
        }

        $location = $this->location();
        $customer = Auth::customer();

        if (!$location instanceof LocationModel) {
            return redirect()->route('igniter.theme.home')->with('error', Storefront::lang('alert.choose_location_checkout'));
        }

        if (!$customer && !$location->allowGuestOrder()) {
            return redirect()->guest(route('igniter.theme.account.login'));
        }

        $orders = resolve(OrderManager::class);
        $form = CheckoutFields::make($orders->loadOrder());
        $payments = Schedules::payments($location);

        return Inertia::render('Checkout/Index', [
            'fields' => CheckoutFields::present($form),
            'fulfilmentOptions' => Schedules::fulfilmentOptions(),
            'payments' => $payments,
            // The payment last chosen this session, else the location's default.
            'selectedPayment' => collect($payments)->firstWhere('code', $orders->getCurrentPaymentCode())['code'] ?? null,
            'delivery' => Location::orderTypeIsDelivery(),
        ]);
    }

    /**
     * The checkout session keeps the order after a gateway marks it paid.
     * Opening checkout again should show the receipt for that order.
     */
    protected function redirectWhenSessionOrderPaid(): ?RedirectResponse
    {
        $order = Order::find(resolve(OrderManager::class)->getCurrentOrderId());
        $customerId = Auth::customer()->customer_id ?? null;

        if (!$order instanceof Order || $order->customer_id != $customerId || !$order->isPaymentProcessed()) {
            return null;
        }

        return redirect()->route('igniter.theme.checkout.success', ['hash' => $order->hash]);
    }

    /**
     * Select a payment method and return what its card form needs in the browser.
     */
    public function payment(Request $request): JsonResponse
    {
        $code = (string)$request->input('payment');
        $orders = resolve(OrderManager::class);
        $payment = $orders->getPayment($code);

        if (!$payment) {
            return response()->json(['message' => Storefront::lang('alert.payment_not_available')], 422);
        }

        try {
            $orders->applyCurrentPaymentFee($payment->code);
            $order = $orders->loadOrder();
            $total = resolve(CartManager::class)->getCart()->total();
            if ($order->payment !== $payment->code || (float)$order->order_total !== (float)$total) {
                $order->updateQuietly(['payment' => $payment->code, 'order_total' => $total]);
            }

            return response()->json(['client' => PaymentClients::config($payment, $order)]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => Storefront::lang('alert.payment_not_loaded')], 422);
        }
    }

    /**
     * Forget the customer's saved card for a gateway and return the card form config again.
     */
    public function deletePaymentProfile(Request $request): JsonResponse
    {
        $orders = resolve(OrderManager::class);
        $customer = Auth::customer();
        $payment = $orders->getPayment((string)$request->input('payment'));

        if (!$customer || !$payment || !$payment->paymentProfileExists($customer)) {
            return response()->json(['message' => Storefront::lang('alert.saved_card_missing')], 422);
        }

        try {
            $payment->deletePaymentProfile($customer);

            return response()->json(['client' => PaymentClients::config($payment, $orders->loadOrder())]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => Storefront::lang('alert.saved_card_not_removed')], 422);
        }
    }

    /**
     * Validate and save the order before the browser charges the card.
     */
    public function prepare(PlaceOrderRequest $request, PlaceOrder $action): JsonResponse
    {
        if (!$action->canCheckout()) {
            return response()->json(['message' => Storefront::lang('alert.sign_in_to_order')], 403);
        }

        try {
            [$order] = $action->prepare($request->validated());

            $payment = resolve(OrderManager::class)->getPayment((string)$order->payment);
            $client = $payment ? PaymentClients::config($payment, $order->refresh()) : null;

            return response()->json(['client' => $client]);
        } catch (ApplicationException|FlashException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => Storefront::lang('alert.something_wrong')], 422);
        }
    }

    public function store(PlaceOrderRequest $request, PlaceOrder $action): mixed
    {
        return $this->attempt(fn(): mixed => $action->handle($request->validated()));
    }

    public function success(string $hash): Response
    {
        $order = resolve(OrderManager::class)->getOrderByHash($hash);
        abort_unless($order instanceof Order, 404);

        resolve(OrderManager::class)->clearOrder();
        GroupOrders::finishGuest();

        $order->load(['menus', 'totals', 'location']);

        return Inertia::render('Checkout/Success', [
            'order' => $this->orderPayload($order),
            'meta' => PageMeta::make(
                PageMeta::line('meta.order_confirmed', ['id' => $order->getKey()]),
                PageMeta::line('meta.checkout_success_description'),
            ),
        ]);
    }
}
