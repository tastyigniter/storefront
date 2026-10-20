<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Classes\CartManager;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Exception\FlashException;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Storefront;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Whether the basket can go to checkout right now, and why not.
 * Mirrors the Orange cart box: closed location, minimum order, delivery unavailable.
 */
class CheckoutStatus
{
    use ReadsKey;

    /**
     * @return ($key is null ? array{blocked: bool, reason: ?string, minimum: ?string} : mixed)
     */
    public static function current(?string $key = null, mixed $default = null): mixed
    {
        $status = self::status();

        return self::read($status, $key, $default);
    }

    /**
     * @return array{blocked: bool, reason: ?string, minimum: ?string}
     */
    protected static function status(): array
    {
        try {
            $manager = resolve(CartManager::class);
            $cart = $manager->getCart();
            $location = Location::current() ?? Location::currentOrDefault();

            if (!$location instanceof LocationModel) {
                return self::blocked(Storefront::lang('alert.choose_location_order'));
            }

            $minimumTotal = (float)Location::minimumOrderTotal();
            $minimum = $minimumTotal > 0 ? Money::label($minimumTotal) : null;

            if ($cart->count() === 0) {
                return ['blocked' => true, 'reason' => null, 'minimum' => $minimum];
            }

            if (Location::checkNoOrderTypeAvailable() || !Location::checkOrderTime()) {
                return self::blocked(Location::hasLaterSchedule()
                    ? Storefront::lang('alert.closed_later')
                    : Storefront::lang('alert.closed_now'), $minimum);
            }

            if ($manager->cartTotalIsBelowMinimumOrder()) {
                return self::blocked(Storefront::lang('alert.below_minimum', [
                    'amount' => Money::label(max(0, $minimumTotal - (float)$cart->subtotal())),
                    'minimum' => $minimum,
                    'type' => strtolower((string)(Location::getOrderType()?->getLabel() ?? Location::orderType())),
                ]), $minimum);
            }

            if ($manager->deliveryChargeIsUnavailable()) {
                return self::blocked(Storefront::lang('alert.delivery_unavailable'), $minimum);
            }

            // Extensions can stop checkout with a reason, as with the Orange checkout,
            // e.g. a group order waiting for guests to submit their orders.
            try {
                Event::dispatch('igniter.orange.checkCheckoutSecurity');
            } catch (ApplicationException|FlashException $exception) {
                return self::blocked($exception->getMessage(), $minimum);
            }

            return ['blocked' => false, 'reason' => null, 'minimum' => $minimum];
        } catch (Throwable $exception) {
            report($exception);

            return self::blocked(Storefront::lang('alert.checkout_unavailable'));
        }
    }

    protected static function blocked(string $reason, ?string $minimum = null): array
    {
        return ['blocked' => true, 'reason' => $reason, 'minimum' => $minimum];
    }
}
