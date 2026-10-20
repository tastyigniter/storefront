<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\Review;
use Igniter\Local\Models\ReviewSettings;
use Igniter\Reservation\Classes\BookingManager;
use Igniter\Reservation\Models\Reservation;
use Igniter\User\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Leave-a-review state for an order or reservation, following the Orange LeaveReview component:
 * reviews are allowed once the order or reservation is completed, one per customer.
 */
class Reviews
{
    public const array TYPES = ['order', 'reservation'];

    public static function find(string $type, string $hash): Order|Reservation|null
    {
        $reviewable = match ($type) {
            'order' => resolve(OrderManager::class)->getOrderByHash($hash, Auth::customer()),
            'reservation' => resolve(BookingManager::class)->getReservationByHash($hash, Auth::customer()),
            default => null,
        };

        return $reviewable instanceof Order || $reviewable instanceof Reservation ? $reviewable : null;
    }

    public static function present(string $type, Order|Reservation $reviewable): ?array
    {
        if (!class_exists(ReviewSettings::class) || !ReviewSettings::allowReviews()) {
            return null;
        }

        $review = Review::query()->whereReviewable($reviewable)->first();

        return [
            'type' => $type,
            'hash' => (string)$reviewable->hash,
            'signedIn' => Auth::customer() !== null,
            'completed' => $reviewable->isCompleted(),
            'hints' => array_values(ReviewSettings::getHints()),
            'existing' => $review instanceof Review ? [
                'quality' => (int)$review->quality,
                'delivery' => (int)$review->delivery,
                'service' => (int)$review->service,
                'comment' => (string)$review->review_text,
                'approved' => (bool)$review->review_status,
            ] : null,
        ];
    }

    public static function enabled(): bool
    {
        return class_exists(ReviewSettings::class) && ReviewSettings::allowReviews();
    }

    /**
     * The average of every rating left in the location's approved reviews, and how many there are.
     *
     * @return array{score: float, count: int}
     */
    public static function summary(LocationModel $location): array
    {
        $row = Review::query()->where('location_id', $location->getKey())->isApproved()
            ->selectRaw('COUNT(*) as reviews')
            ->selectRaw('COALESCE(SUM(NULLIF(quality, 0)), 0) + COALESCE(SUM(NULLIF(delivery, 0)), 0) + COALESCE(SUM(NULLIF(service, 0)), 0) as points')
            ->selectRaw('COUNT(NULLIF(quality, 0)) + COUNT(NULLIF(delivery, 0)) + COUNT(NULLIF(service, 0)) as ratings')
            ->toBase()
            ->first();

        $ratings = (int)($row->ratings ?? 0);

        return [
            'score' => $ratings > 0 ? round((float)$row->points / $ratings, 1) : 0.0,
            'count' => (int)($row->reviews ?? 0),
        ];
    }

    /**
     * A page of a location's approved reviews, newest first, with the average for each
     * rating. Authors are shortened to a first name and initial.
     */
    public static function forLocation(LocationModel $location, int $page = 1, int $perPage = 10): array
    {
        $query = Review::query()->where('location_id', $location->getKey())->isApproved();

        $averages = (clone $query)
            ->selectRaw('AVG(NULLIF(quality, 0)) as quality, AVG(NULLIF(delivery, 0)) as delivery, AVG(NULLIF(service, 0)) as service')
            ->toBase()
            ->first();

        $paginator = $query->orderByDesc('created_at')->orderByDesc('review_id')->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'total' => $paginator->total(),
            'averages' => collect(['quality', 'delivery', 'service'])
                ->mapWithKeys(fn(string $key): array => [$key => $averages?->{$key} === null ? null : round((float)$averages->{$key}, 1)])
                ->all(),
            'reviews' => collect($paginator->items())->map(fn(Review $review): array => [
                'id' => (int)$review->getKey(),
                'author' => self::author((string)$review->author),
                'rating' => self::average($review),
                'quality' => (int)$review->quality,
                'delivery' => (int)$review->delivery,
                'service' => (int)$review->service,
                'comment' => (string)$review->review_text,
                'date' => $review->created_at?->isoFormat('D MMM YYYY') ?? '',
            ])->values()->all(),
            'nextPage' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
        ];
    }

    protected static function author(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = (string)array_shift($parts);
        $last = (string)array_pop($parts);

        return trim($first.($last !== '' ? ' '.Str::upper(Str::substr($last, 0, 1)).'.' : ''));
    }

    /** The mean of the ratings a reviewer gave, ignoring ones left blank. */
    protected static function average(Review $review): float
    {
        $ratings = array_filter([(int)$review->quality, (int)$review->delivery, (int)$review->service], fn(int $value): bool => $value > 0);

        return $ratings === [] ? 0.0 : round(array_sum($ratings) / count($ratings), 1);
    }
}
