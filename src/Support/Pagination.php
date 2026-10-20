<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Pages through the storefront's lists (orders, reservations, points, addresses, locations).
 * The page size is the theme's "Items per page" setting; Vue hides the links on a single page.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 25;

    public static function perPage(): int
    {
        $perPage = (int)ThemeContent::value('page_limit', self::DEFAULT_PER_PAGE);

        return $perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE;
    }

    /**
     * A page of a query, mapped for Vue.
     *
     * @return array{items: list<mixed>, pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int, to: int}}
     */
    public static function query(mixed $query, callable $map): array
    {
        /** @var Builder $query */
        $paginator = $query->paginate(self::perPage());

        return [
            'items' => array_values(array_map($map, $paginator->items())),
            'pagination' => self::meta($paginator),
        ];
    }

    /**
     * A page of a list built in PHP, such as the location directory sorted by distance.
     *
     * @param list<mixed> $items
     * @return array{items: list<mixed>, pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int, to: int}}
     */
    public static function items(array $items): array
    {
        $perPage = self::perPage();
        $page = Paginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $perPage, $page);

        return [
            'items' => array_values($paginator->items()),
            'pagination' => self::meta($paginator),
        ];
    }

    /**
     * @return array{currentPage: int, lastPage: int, perPage: int, total: int, from: int, to: int}
     */
    protected static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => (int)$paginator->firstItem(),
            'to' => (int)$paginator->lastItem(),
        ];
    }
}
