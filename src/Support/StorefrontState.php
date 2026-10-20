<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\CartCondition;
use Igniter\Cart\CartItem;
use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\CartSettings;
use Igniter\Cart\Models\Menu;
use Igniter\Flame\Currency\Facades\Currency;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Actions\Fulfilment;
use Igniter\Storefront\Storefront;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Address;
use Igniter\User\Models\Customer;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Throwable;

class StorefrontState
{
    use ReadsKey;

    /**
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public static function shared(?string $key = null, mixed $default = null): mixed
    {
        $current = Location::currentOrDefault();

        $shared = [
            'siteName' => (string)setting('site_name', Storefront::lang('site.name')),
            'currency' => Currency::getDefault()->getCode() ?? 'GBP',
            'customer' => self::customer(),
            'orderType' => (string)(Location::orderType() ?: LocationModel::DELIVERY),
            'postcode' => (string)Location::getSession('searchQuery'),
            'fulfilment' => Schedules::fulfilment(),
            'location' => $current instanceof LocationModel ? self::location($current) : null,
            'locations' => self::locations(),
            'dineIn' => DineInTables::present($current instanceof LocationModel ? $current : null),
            'loyalty' => LoyaltyRedeem::state(),
            'groupOrder' => GroupOrders::present(),
            'maps' => [
                'geocoder' => (string)setting('default_geocoder', 'nominatim'),
                'key' => (string)setting('maps_api_key', ''),
            ],
            'cart' => self::cart(),
        ];

        return self::read($shared, $key, $default);
    }

    public static function address(LocationModel $location): string
    {
        return implode(', ', array_filter([
            $location->location_address_1,
            $location->location_city,
            $location->location_postcode,
        ]));
    }

    public static function location(LocationModel $location, bool $summary = false): array
    {
        $card = [
            'id' => (int)$location->getKey(),
            'name' => $location->location_name,
            'slug' => $location->permalink_slug,
            'address' => self::address($location),
            'offersDelivery' => (bool)$location->hasDelivery(),
            'offersCollection' => (bool)$location->hasCollection(),
            'offersDineIn' => DineInTables::offered($location),
            'hours' => Hours::today($location),
        ];

        if ($summary) {
            return $card;
        }

        return [
            ...$card,
            'telephone' => $location->location_telephone,
            // The location's thumbnail (Restaurant > Locations > Media), square for the booking card.
            'image' => $location->getThumb(['width' => 224, 'height' => 224], 'thumb'),
            'email' => $location->location_email,
            'week' => Hours::week($location),
            'rating' => self::rating($location),
            'leadTimes' => self::leadTimes($location),
        ];
    }

    /**
     * The location's average review score out of 5 and how many approved reviews it has,
     * or null when reviews are turned off.
     *
     * @return array{score: float, count: int}|null
     */
    protected static function rating(LocationModel $location): ?array
    {
        return Reviews::enabled() ? Reviews::summary($location) : null;
    }

    /**
     * Minutes until the soonest delivery or pick-up, per order type the location offers.
     *
     * @return array<string, int>
     */
    protected static function leadTimes(LocationModel $location): array
    {
        try {
            return collect($location->availableOrderTypes())
                ->filter(fn($type, $code): bool => in_array($code, ['delivery', 'collection'], true) && !$type->isDisabled())
                ->map(fn($type): int => (int)$type->getLeadTime())
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function locations(): array
    {
        $locations = [];

        foreach (LocationModel::query()->whereIsEnabled()->with('working_hours')->orderBy('location_name')->get() as $location) {
            if ($location instanceof LocationModel) {
                $locations[] = self::location($location, true);
            }
        }

        return $locations;
    }

    /**
     * @return list<array{id: int, label: string, address1: string, address2: ?string, city: ?string, state: ?string, postcode: ?string, countryId: int}>
     */
    protected static function addresses(Customer $customer): array
    {
        $addresses = [];

        /** @var \Illuminate\Database\Eloquent\Collection<int, Address> $saved */
        $saved = $customer->addresses()->get();

        foreach ($saved as $address) {
            $addresses[] = [
                'id' => (int)$address->getKey(),
                'label' => Fulfilment::formatAddress($address),
                'address1' => $address->address_1,
                'address2' => $address->address_2,
                'city' => $address->city,
                'state' => $address->state,
                'postcode' => $address->postcode,
                'countryId' => (int)$address->country_id,
            ];
        }

        return $addresses;
    }

    protected static function customer(): ?array
    {
        $customer = Auth::customer();
        if (!$customer) {
            return null;
        }

        return [
            'id' => (int)$customer->getKey(),
            'firstName' => $customer->first_name,
            'lastName' => $customer->last_name,
            'email' => $customer->email,
            'telephone' => $customer->telephone,
            'addresses' => self::addresses($customer),
        ];
    }

    protected static function cart(): array
    {
        try {
            $manager = resolve(CartManager::class);
            $cart = $manager->getCart();
            $totals = resolve(OrderManager::class)->getCartTotals();
            $coupon = $cart->getCondition('coupon');
            $giftup = $cart->getCondition('giftup');

            $content = collect($cart->content());
            $images = self::lineImages($content);

            return [
                'count' => (int)$cart->count(),
                'lines' => $content->map(fn(CartItem $item): array => self::line($item, $images[(int)$item->id] ?? null))->values()->all(),
                'totals' => collect($totals)->sortBy('priority')->map(fn(array $total): array => [
                    'code' => $total['code'],
                    'title' => $total['title'],
                    'value' => (float)$total['value'],
                    'label' => Money::label($total['value']),
                ])->values()->all(),
                'coupon' => $coupon instanceof CartCondition ? (string)($coupon->getMetaData('code') ?? '') : '',
                'couponApplied' => $coupon instanceof CartCondition && $coupon->isApplied(),
                'giftup' => $giftup instanceof CartCondition ? (string)($giftup->getMetaData('code') ?? '') : '',
                'giftupApplied' => $giftup instanceof CartCondition && $giftup->isApplied(),
                'tip' => self::tip($cart->getCondition('tip')),
                'checkout' => CheckoutStatus::current(),
                'amount' => (float)$cart->total(),
                'total' => Money::label($cart->total()),
                'subtotal' => Money::label($cart->subtotal()),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'count' => 0,
                'lines' => [],
                'totals' => [],
                'coupon' => '',
                'couponApplied' => false,
                'giftup' => '',
                'giftupApplied' => false,
                'tip' => null,
                'checkout' => ['blocked' => true, 'reason' => Storefront::lang('alert.something_wrong'), 'minimum' => null],
                'amount' => 0.0,
                'total' => Money::label(0),
                'subtotal' => Money::label(0),
            ];
        }
    }

    /**
     * Tip presets from Cart settings, plus the tip currently applied.
     */
    protected static function tip(?CartCondition $condition): ?array
    {
        if (!CartSettings::tippingEnabled()) {
            return null;
        }

        $percentage = (CartSettings::get('tip_value_type') ?? 'F') === 'P';

        return [
            'percentage' => $percentage,
            'presets' => collect(CartSettings::tippingAmounts())
                ->map(fn(object $amount): array => [
                    'value' => (float)$amount->value,
                    'label' => $percentage ? rtrim(rtrim(number_format((float)$amount->value, 2), '0'), '.').'%' : Money::label($amount->value),
                ])
                ->values()
                ->all(),
            'amount' => (float)($condition?->getMetaData('amount') ?? 0),
            'isCustom' => (bool)$condition?->getMetaData('isCustom'),
        ];
    }

    /**
     * Dish thumbnails for the basket, loaded in one query.
     *
     * @return array<int, ?string>
     */
    protected static function lineImages(Collection $content): array
    {
        $ids = $content->map(fn(CartItem $item): int => (int)$item->id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Menu> $menus */
        $menus = Menu::query()->with('media')->whereKey($ids)->get();

        return $menus
            ->mapWithKeys(fn(Menu $menu): array => [(int)$menu->getKey() => $menu->getThumb(['width' => 160, 'height' => 160])])
            ->all();
    }

    protected static function line(CartItem $item, ?string $image = null): array
    {
        $raw = $item->toArray();

        return [
            'rowId' => $item->rowId,
            'menuId' => (int)$item->id,
            'name' => $item->name,
            'image' => $image,
            'qty' => (int)$item->qty,
            'comment' => (string)$item->comment,
            'price' => Money::label($item->price),
            'subtotal' => Money::label($item->subtotal()),
            'selected' => collect($raw['options'] ?? [])->mapWithKeys(function(mixed $option): array {
                $option = $option instanceof Arrayable ? $option->toArray() : (array)$option;

                return [(int)($option['id'] ?? 0) => collect($option['values'] ?? [])->map(function(mixed $value): array {
                    $value = $value instanceof Arrayable ? $value->toArray() : (array)$value;

                    return ['id' => (int)($value['id'] ?? 0), 'qty' => (int)($value['qty'] ?? 1)];
                })->values()->all()];
            })->all(),
            'options' => collect($raw['options'] ?? [])->map(function(mixed $option): array {
                $option = $option instanceof Arrayable ? $option->toArray() : (array)$option;
                $values = collect($option['values'] ?? [])->map(function(mixed $value): string {
                    if ($value instanceof Arrayable) {
                        $value = $value->toArray();
                    }

                    if (!is_array($value)) {
                        return (string)$value;
                    }

                    // Quantity options can hold several of one value, e.g. "2× Coke".
                    $qty = (int)($value['qty'] ?? 1);

                    return ($qty > 1 ? $qty.'× ' : '').($value['name'] ?? '');
                })->filter()->values()->all();

                return [
                    'name' => (string)($option['name'] ?? ''),
                    'values' => $values,
                ];
            })->values()->all(),
        ];
    }
}
