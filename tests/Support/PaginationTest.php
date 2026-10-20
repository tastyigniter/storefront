<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Models\Order;
use Igniter\Storefront\Support\Pagination;
use Illuminate\Pagination\Paginator;

it('uses 25 per page unless the theme sets another limit', function(): void {
    expect(Pagination::perPage())->toBe(25);

    setThemeSettings(['page_limit' => '10']);
    expect(Pagination::perPage())->toBe(10);

    setThemeSettings(['page_limit' => 0]);
    expect(Pagination::perPage())->toBe(Pagination::DEFAULT_PER_PAGE);
});

it('pages through a list built in PHP', function(): void {
    setThemeSettings(['page_limit' => 2]);
    Paginator::currentPageResolver(fn(): int => 2);

    expect(Pagination::items(['a', 'b', 'c', 'd', 'e']))->toBe([
        'items' => ['c', 'd'],
        'pagination' => ['currentPage' => 2, 'lastPage' => 3, 'perPage' => 2, 'total' => 5, 'from' => 3, 'to' => 4],
    ]);
});

it('maps a page of a query', function(): void {
    currentLocation();
    setThemeSettings(['page_limit' => 2]);
    paidOrder();
    paidOrder();
    paidOrder();

    $page = Pagination::query(Order::query()->orderBy('order_id'), fn(Order $order): int => (int)$order->getKey());

    expect($page['items'])->toHaveCount(2)
        ->and($page['pagination'])->toMatchArray(['currentPage' => 1, 'lastPage' => 2, 'total' => 3, 'from' => 1, 'to' => 2]);
});

it('reports an empty page as zero to zero', function(): void {
    expect(Pagination::items([])['pagination'])->toMatchArray(['lastPage' => 1, 'total' => 0, 'from' => 0, 'to' => 0]);
});
