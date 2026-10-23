<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\PlaceOrderRequest;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\StorefrontRoutes;
use IgniterLabs\DineIn\Support\Courses;
use IgniterLabs\DineIn\Support\Seating;
use IgniterLabs\DineIn\Support\Tabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DineInController extends Controller
{
    use ResolvesStorefrontPage;

    public function course(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'row_id' => ['required', 'string', 'max:64'],
            'course' => ['required', 'in:now,with_mains,hold'],
        ]);

        Courses::remember($data['row_id'], $data['course']);

        return redirect()->back();
    }

    public function sendToKitchen(Request $request): RedirectResponse
    {
        if (Location::orderType() !== 'dinein') {
            throw new ApplicationException(lang('igniterlabs.dinein::default.alert_table_required'));
        }

        $data = class_exists(PlaceOrderRequest::class)
            ? $request->validate((new PlaceOrderRequest)->rules())
            : $request->validate([
                'first_name' => ['nullable', 'string', 'max:128'],
                'last_name' => ['nullable', 'string', 'max:128'],
                'email' => ['nullable', 'email', 'max:96'],
                'telephone' => ['nullable', 'string', 'max:32'],
                'comment' => ['nullable', 'string', 'max:500'],
            ]);

        unset($data['payment']);

        session()->forget(Seating::PAYING_BILL);

        $order = Tabs::sendToKitchen($data);
        resolve(OrderManager::class)->setCurrentOrderId($order->getKey());

        flash()->success(lang('igniterlabs.dinein::default.alert_sent_to_kitchen'));

        return redirect()->to(StorefrontRoutes::order());
    }

    public function requests(Request $request): RedirectResponse
    {
        $kind = (string)$request->validate([
            'kind' => ['required', 'in:waiter,water,bill'],
        ])['kind'];

        Tabs::request($kind);

        $message = $kind === 'bill'
            ? lang('igniterlabs.dinein::default.alert_bill_requested')
            : lang('igniterlabs.dinein::default.alert_request_sent');

        flash()->success($message);

        return redirect()->back();
    }

    public function pay(): RedirectResponse
    {
        if (Location::orderType() !== 'dinein') {
            throw new ApplicationException(lang('igniterlabs.dinein::default.alert_table_required'));
        }

        if (!DineInTables::openBill() instanceof Order) {
            throw new ApplicationException(lang('igniterlabs.dinein::default.alert_tab_not_open'));
        }

        DineInTables::prepareOpenBillCheckout();

        return redirect()->route('igniter.theme.checkout.checkout');
    }
}
