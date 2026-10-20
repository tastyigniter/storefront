<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Concerns;

use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Models\Reservation;
use Igniter\Storefront\Support\CancelConfirmation;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\Hours;
use Igniter\Storefront\Support\Money;
use Igniter\Storefront\Support\OrderProgress;
use Igniter\Storefront\Support\StorefrontState;
use Igniter\System\Models\Country;
use Illuminate\Database\Eloquent\Collection;

trait PresentsStorefront
{
    protected function location(): ?LocationModel
    {
        $location = Location::currentOrDefault();

        return $location instanceof LocationModel ? $location : null;
    }

    protected function countries(): array
    {
        $countries = [];

        /** @var Collection<int, Country> $rows */
        $rows = Country::query()->whereIsEnabled()->orderBy('country_name')->get(['country_id', 'country_name']);

        foreach ($rows as $country) {
            $countries[] = [
                'id' => (int)$country->getKey(),
                'name' => $country->country_name,
            ];
        }

        return $countries;
    }

    protected function addressPayload(object $address): array
    {
        return [
            'id' => (int)$address->getKey(),
            'address1' => $address->address_1,
            'address2' => $address->address_2,
            'city' => $address->city,
            'state' => $address->state,
            'postcode' => $address->postcode,
            'countryId' => (int)$address->country_id,
        ];
    }

    protected function orderPayload(Order $order): array
    {
        $order->loadMissing(['menus.menu_options.menu_option', 'totals', 'location', 'status', 'status_history', 'payment_method', 'address']);

        return [
            'hash' => $order->hash,
            'id' => (int)$order->getKey(),
            'total' => Money::label($order->order_total),
            'type' => $order->order_type,
            'date' => optional($order->order_date)->toDateString() ?? (string)$order->order_date,
            'time' => Hours::clock($order->order_time),
            'placed' => [
                'date' => $order->created_at?->toDateString() ?? '',
                'time' => Hours::clock($order->created_at),
            ],
            'status' => (string)$order->status_name,
            'statusComment' => OrderProgress::comment($order),
            'progress' => OrderProgress::stage($order),
            'payment' => $order->payment_method?->name,
            'deliveryAddress' => $order->isDeliveryType() ? $order->formatted_address : null,
            'comment' => $order->comment,
            'deliveryComment' => $order->delivery_comment,
            'location' => $order->location?->location_name,
            'menus' => $order->menus->map(fn($menu): array => [
                'name' => $menu->name,
                'qty' => (int)$menu->quantity,
                'price' => Money::label($menu->subtotal ?? $menu->price),
                'comment' => $menu->comment,
                // Selected options grouped by option, e.g. Drinks: 2× Coke, Diet Coke.
                'options' => $menu->menu_options
                    ->groupBy(fn($value): string => (string)($value->menu_option->option_name ?? ''))
                    ->map(fn($values, string $name): array => [
                        'name' => $name,
                        'values' => $values->map(fn($value): string => ((int)$value->quantity > 1 ? (int)$value->quantity.'× ' : '').$value->order_option_name)->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ])->all(),
            'totals' => $order->totals->sortBy('priority')->values()->map(fn($total): array => [
                'title' => $total->title,
                'label' => Money::label($total->value),
            ])->all(),
            // Same rules as the Orange order preview.
            'canCancel' => !$order->isCanceled() && $order->isCancelable(),
            'confirmEmail' => CancelConfirmation::required($order->customer_id ? (int)$order->customer_id : null),
            'canReorder' => $order->menus->isNotEmpty(),
            'isCanceled' => $order->isCanceled(),
            'table' => DineInTables::details($order),
        ];
    }

    /**
     * Who placed the order and where from, for the customer's own account page.
     *
     * @return array<string, mixed>
     */
    protected function orderContactPayload(Order $order): array
    {
        $location = $order->location;

        return [
            'contact' => [
                'name' => trim($order->customer_name),
                'email' => (string)$order->email,
                'telephone' => (string)$order->telephone,
            ],
            'restaurant' => $location instanceof LocationModel ? [
                'name' => $location->location_name,
                'address' => StorefrontState::address($location),
                'telephone' => (string)$location->location_telephone,
                'orderUrl' => route('igniter.theme.order', ['location' => $location->permalink_slug]),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reservationPayload(Reservation $reservation): array
    {
        return [
            'hash' => $reservation->hash,
            'guests' => (int)$reservation->guest_num,
            'date' => optional($reservation->reserve_date)->toDateString() ?? (string)$reservation->reserve_date,
            'time' => Hours::clock($reservation->reserve_time),
            'name' => trim($reservation->first_name.' '.$reservation->last_name),
            'location' => $reservation->location?->location_name,
            'locationAddress' => $reservation->location instanceof LocationModel ? StorefrontState::address($reservation->location) : null,
            'comment' => $reservation->comment,
            'telephone' => $reservation->telephone,
            'status' => (string)($reservation->status_name ?? ''),
            'canCancel' => !$reservation->isCanceled() && (bool)$reservation->isCancelable(),
            'confirmEmail' => CancelConfirmation::required($reservation->customer_id ? (int)$reservation->customer_id : null),
            'isCanceled' => $reservation->isCanceled(),
        ];
    }
}
