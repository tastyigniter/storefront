<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Local\Models\Review;
use Igniter\Local\Models\ReviewSettings;
use Igniter\Storefront\Support\Reviews;

beforeEach(function(): void {
    currentLocation();
});

it('finds nothing for an unknown review type', function(): void {
    expect(Reviews::find('menu', 'hash'))->toBeNull();
});

it('is hidden when reviews are turned off', function(): void {
    ReviewSettings::set('allow_reviews', 0);

    expect(Reviews::present('order', paidOrder()))->toBeNull();
});

it('includes the review already left', function(): void {
    ReviewSettings::set('allow_reviews', 1);
    $customer = actingAsCustomer();
    $order = paidOrder($customer);
    completeOrder($order);
    Review::leaveReview($order, ['quality' => 4, 'delivery' => 3, 'service' => 5, 'review_text' => 'Tasty']);

    expect(Reviews::present('order', $order->fresh()))->toMatchArray([
        'type' => 'order',
        'hash' => $order->hash,
        'signedIn' => true,
        'completed' => true,
        'existing' => [
            'quality' => 4,
            'delivery' => 3,
            'service' => 5,
            'comment' => 'Tasty',
            'approved' => (bool)ReviewSettings::autoApproveReviews(),
        ],
    ]);
});
