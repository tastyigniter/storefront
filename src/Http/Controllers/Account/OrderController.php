<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\Hours;
use Igniter\Storefront\Support\Money;
use Igniter\Storefront\Support\PageMeta;
use Igniter\Storefront\Support\Pagination;
use Igniter\Storefront\Support\Reviews;
use Igniter\User\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrderController
{
    use PresentsStorefront;
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        $page = Pagination::query(Auth::customer()->orders()->latest('order_id'), fn(Order $order): array => [
            'hash' => $order->hash,
            'id' => (int)$order->getKey(),
            'total' => Money::label($order->order_total),
            'type' => $order->order_type,
            'date' => optional($order->order_date)->toDateString() ?? (string)$order->order_date,
            'time' => Hours::clock($order->order_time),
            'status' => (string)$order->status_name,
        ]);

        return Inertia::render('Account/Orders', [
            'orders' => $page['items'],
            'pagination' => $page['pagination'],
        ]);
    }

    public function show(string $hash): Response
    {
        $order = resolve(OrderManager::class)->getOrderByHash($hash, Auth::customer());
        abort_unless($order instanceof Order, 404);
        $order->load(['menus', 'totals', 'location']);

        return Inertia::render('Account/Order', [
            'order' => $this->orderPayload($order),
            'review' => Reviews::present('order', $order),
            ...$this->orderContactPayload($order),
            'meta' => PageMeta::make(
                PageMeta::line('meta.order_number', ['id' => $order->getKey()]),
                PageMeta::line('meta.account_order_description'),
            ),
        ]);
    }
}
