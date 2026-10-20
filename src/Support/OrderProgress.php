<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Models\Order;

/**
 * Where an order is on its way: received, preparing, then delivered or collected.
 * Uses the same status groups as the Orange progress bars (Settings > Order).
 */
final class OrderProgress
{
    public const int RECEIVED = 1;

    public const int PREPARING = 2;

    public const int COMPLETED = 3;

    /**
     * The stage reached, or null when the order was cancelled or has no status yet.
     */
    public static function stage(Order $order): ?int
    {
        $statusId = (int)$order->status_id;
        if ($statusId < 1 || $order->isCanceled()) {
            return null;
        }

        if (in_array($statusId, self::statuses('completed_order_status'), true)) {
            return self::COMPLETED;
        }

        if (in_array($statusId, self::statuses('processing_order_status'), true)) {
            return self::PREPARING;
        }

        return self::RECEIVED;
    }

    /**
     * The comment for the current status. A comment the customer wasn't notified of stays internal,
     * so the status's own default comment shows instead (as Orange does).
     */
    public static function comment(Order $order): ?string
    {
        $history = $order->status_history
            ->sortByDesc(fn($entry): int => (int)$entry->getKey())
            ->first(fn($entry): bool => (int)$entry->status_id === (int)$order->status_id);

        $comment = $history?->notify ? $history->comment : $order->status?->status_comment;
        $comment = trim(html_entity_decode(strip_tags((string)$comment)));

        return $comment !== '' ? $comment : null;
    }

    /**
     * @return list<int>
     */
    protected static function statuses(string $key): array
    {
        return array_map(intval(...), array_values((array)setting($key, [])));
    }
}
