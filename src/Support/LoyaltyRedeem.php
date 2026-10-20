<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Facades\Cart;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;
use Igniter\Storefront\Storefront;
use Igniter\User\Facades\Auth;

/**
 * Redeem loyalty points against the basket. Earning stays with the extension's
 * own listeners.
 */
class LoyaltyRedeem
{
    use ReadsKey;

    /** @var array<string, mixed>|null */
    public static ?array $state = null;

    /** @var array<string, mixed>|null */
    public static ?array $history = null;

    public static function installed(): bool
    {
        return class_exists('IgniterLabs\\LoyaltyPoints\\Models\\LoyaltySettings');
    }

    /**
     * @return ($key is null ? array{available: float, minimum: float, applied: float, canApply: bool, cartEmpty: bool, signedIn: bool}|null : mixed)
     */
    public static function state(?string $key = null, mixed $default = null): mixed
    {
        if (self::$state !== null) {
            $state = self::normalise(self::$state);
        } else {
            $state = self::installed() ? self::loadState() : null;
        }

        return self::read($state, $key, $default);
    }

    /**
     * @return array{earned: float, redeemed: float, pending: float, available: float, rows: list<array{date: string, type: string, earned: string, used: string, status: string}>, pagination?: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int, to: int}}|null
     */
    public static function history(): ?array
    {
        if (self::$history !== null) {
            return self::$history;
        }

        return self::installed() ? self::loadHistory() : null;
    }

    /**
     * @param array{available: float, minimum: float, applied: float, canApply: bool, cartEmpty: bool, signedIn: bool} $state
     */
    public static function redeem(int $amount, array $state): void
    {
        self::guard($amount, $state);
        self::commit($amount);
    }

    public static function clear(): void
    {
        if (self::state() === null) {
            abort(404);
        }

        self::remove();
    }

    /**
     * @param array{available: float, minimum: float, applied: float, canApply: bool, cartEmpty: bool, signedIn: bool} $state
     */
    public static function guard(int $amount, array $state): void
    {
        if (!$state['signedIn']) {
            throw new ApplicationException(Storefront::lang('alert.loyalty_login'));
        }

        if ($amount < 1) {
            throw new ApplicationException(Storefront::lang('alert.loyalty_amount'));
        }

        if ($state['cartEmpty']) {
            throw new ApplicationException(Storefront::lang('alert.loyalty_empty'));
        }

        if ($amount > $state['available']) {
            throw new ApplicationException(Storefront::lang('alert.loyalty_points'));
        }

        if ($state['available'] < $state['minimum'] || $amount < $state['minimum']) {
            throw new ApplicationException(Storefront::lang('alert.loyalty_minimum', [
                'minimum' => (int)$state['minimum'],
            ]));
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array{available: float, minimum: float, applied: float, canApply: bool, cartEmpty: bool, signedIn: bool}
     */
    public static function normalise(array $state): array
    {
        return [
            'available' => (float)$state['available'],
            'minimum' => (float)$state['minimum'],
            'applied' => (float)$state['applied'],
            'canApply' => (bool)$state['canApply'],
            'cartEmpty' => (bool)$state['cartEmpty'],
            'signedIn' => (bool)$state['signedIn'],
        ];
    }

    /**
     * @return array{available: float, minimum: float, applied: float, canApply: bool, cartEmpty: bool, signedIn: bool}|null
     *
     * @codeCoverageIgnore
     */
    protected static function loadState(): ?array
    {
        $settings = 'IgniterLabs\\LoyaltyPoints\\Models\\LoyaltySettings';
        if (!$settings::isEnabled()) {
            return null;
        }

        $customer = Auth::customer();
        $signedIn = (bool)$customer;
        $specific = (bool)$settings::isLocationSpecific();
        $manager = resolve('IgniterLabs\\LoyaltyPoints\\Classes\\LoyaltyManager');
        $available = $signedIn
            ? (float)$manager->getAvailablePoints((int)$customer->customer_id, $specific ? Location::getId() : null)
            : 0.0;
        $minimum = (float)$manager->getSetting('spending_minimum', 1, $specific ? Location::current() : null);
        $condition = Cart::getCondition('loyalty');
        $applied = $condition ? (float)$condition->getMetaData('amount', 0) : 0.0;
        $cartEmpty = resolve(CartManager::class)->getCart()->content()->isEmpty();

        return self::normalise([
            'available' => $available,
            'minimum' => $minimum,
            'applied' => $applied,
            'canApply' => $signedIn && !$cartEmpty && $available >= $minimum && $available > 0,
            'cartEmpty' => $cartEmpty,
            'signedIn' => $signedIn,
        ]);
    }

    /**
     * @codeCoverageIgnore
     */
    protected static function commit(int $amount): void
    {
        if (!self::installed()) {
            return;
        }

        resolve(CartManager::class)->applyCondition('loyalty', ['amount' => $amount]);
    }

    /**
     * @codeCoverageIgnore
     */
    protected static function remove(): void
    {
        if (!self::installed()) {
            return;
        }

        resolve(CartManager::class)->removeCondition('loyalty');
    }

    /**
     * @return array{earned: float, redeemed: float, pending: float, available: float, rows: list<array{date: string, type: string, earned: string, used: string, status: string}>, pagination?: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int, to: int}}
     *
     * @codeCoverageIgnore
     */
    protected static function loadHistory(): array
    {
        $history = 'IgniterLabs\\LoyaltyPoints\\Models\\LoyaltyHistory';
        $settings = 'IgniterLabs\\LoyaltyPoints\\Models\\LoyaltySettings';
        $customer = Auth::customer();
        $customerId = (int)($customer->customer_id ?? 0);
        $base = fn() => $history::query()->applyEnabled()->applyCustomer($customerId);
        $specific = (bool)$settings::isLocationSpecific();
        $pending = $base()
            ->where('points_earned', '>', 0)
            ->where('valid_from', '>', now())
            ->where('valid_to', '>=', now())
            ->when($specific, fn($query) => $query->where('location_id', Location::getId()))
            ->sum('points_earned');

        $page = Pagination::query($base()->orderByDesc('date_used'), fn($row): array => [
            'date' => optional($row->date_used ?? $row->created_at)->format('j M Y') ?? '',
            'type' => (string)($row->type_formatted ?? $row->type),
            'earned' => (string)$row->points_earned,
            'used' => (string)$row->points_used,
            'status' => (string)($row->status_formatted ?? ''),
        ]);

        return [
            'earned' => (float)$base()->where('points_earned', '>', 0)->sum('points_earned'),
            'redeemed' => (float)$base()->where('points_used', '>', 0)->sum('points_used'),
            'pending' => (float)$pending,
            'available' => (float)self::state('available', 0),
            'rows' => $page['items'],
            'pagination' => $page['pagination'],
        ];
    }
}
