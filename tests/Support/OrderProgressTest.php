<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Admin\Models\Status;
use Igniter\Cart\Models\Order;
use Igniter\Storefront\Support\OrderProgress;
use Igniter\System\Models\Settings;

beforeEach(function(): void {
    currentLocation();
    Settings::set('processing_order_status', ['2']);
    Settings::set('completed_order_status', ['3']);
});

function orderStatus(string $name, ?string $comment = null): Status
{
    return Status::create(['status_name' => $name, 'status_for' => 'order', 'status_comment' => $comment, 'notify_customer' => true]);
}

it('places the order on received, preparing or done by status group', function(): void {
    $order = paidOrder();
    $order->status_id = 1;
    expect(OrderProgress::stage($order))->toBe(OrderProgress::RECEIVED);

    $order->status_id = 2;
    expect(OrderProgress::stage($order))->toBe(OrderProgress::PREPARING);

    $order->status_id = 3;
    expect(OrderProgress::stage($order))->toBe(OrderProgress::COMPLETED);

    $order->status_id = 0;
    expect(OrderProgress::stage($order))->toBeNull();
});

it('has no progress once cancelled', function(): void {
    $order = paidOrder();
    $order->markAsCanceled();

    expect(OrderProgress::stage($order->fresh()))->toBeNull();
});

it('shows the comment the customer was told about', function(): void {
    $order = paidOrder();
    $status = orderStatus('Out for delivery', '<p>Default &amp; dull</p>');

    $order->status_id = $status->getKey();
    expect(OrderProgress::comment($order))->toBe('Default & dull');

    $order->updateOrderStatus($status->getKey(), ['comment' => 'First note', 'notify' => true]);
    $order->updateOrderStatus($status->getKey(), ['comment' => '<b>Rider</b> is two streets away', 'notify' => true]);
    expect(OrderProgress::comment(Order::find($order->getKey())))->toBe('Rider is two streets away');

    $order->updateOrderStatus($status->getKey(), ['comment' => 'Driver ran late again', 'notify' => false]);
    expect(OrderProgress::comment(Order::find($order->getKey())))->toBe('Default & dull');

    $order = Order::find($order->getKey());
    $order->status_id = orderStatus('Quiet')->getKey();
    expect(OrderProgress::comment($order))->toBeNull();
});
