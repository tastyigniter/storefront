<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Flame\Geolite\Model\Location;
use Igniter\Storefront\Actions\Fulfilment;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\SearchFulfillmentRequest;
use Igniter\Storefront\Http\Requests\UpdateFulfilmentRequest;
use Igniter\Storefront\Http\Requests\UpdateSessionRequest;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\AddressSuggestions;
use Igniter\Storefront\Support\DeliveryCoverage;
use Igniter\Storefront\Support\GeoPin;
use Igniter\Storefront\Support\StorefrontRoutes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FulfillmentController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function store(SearchFulfillmentRequest $request, Fulfilment $action): mixed
    {
        $data = $request->validated();

        return $this->attempt(function() use ($action, $data) {
            $action->search(
                $data['address'] ?? null,
                isset($data['address_id']) ? (int)$data['address_id'] : null,
                latitude: isset($data['latitude']) ? (float)$data['latitude'] : null,
                longitude: isset($data['longitude']) ? (float)$data['longitude'] : null,
            );

            $redirect = redirect()->to(StorefrontRoutes::order());
            if ($message = DeliveryCoverage::message()) {
                [$level, $text] = $message;
                $redirect->with($level, $text);
            }

            return $redirect;
        });
    }

    public function update(UpdateFulfilmentRequest $request, Fulfilment $action): mixed
    {
        $data = $request->validated();

        return $this->attempt(function() use ($action, $data): RedirectResponse {
            $action->update(
                $data['order_type'],
                (bool)$data['asap'],
                $data['date'] ?? null,
                $data['time'] ?? null,
                $data['address'] ?? null,
                isset($data['address_id']) ? (int)$data['address_id'] : null,
                isset($data['latitude']) ? (float)$data['latitude'] : null,
                isset($data['longitude']) ? (float)$data['longitude'] : null,
                isset($data['area_id']) ? (int)$data['area_id'] : null,
                isset($data['table_id']) ? (int)$data['table_id'] : null,
                isset($data['guest_count']) ? (int)$data['guest_count'] : null,
            );

            return back();
        });
    }

    /**
     * Turn a map pin into a street address. The pin coordinates are what get saved.
     */
    public function locate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $location = GeoPin::locate((float)$data['latitude'], (float)$data['longitude']);
        $address = $location instanceof Location ? GeoPin::label($location) : '';

        if (!$location?->hasCoordinates() || $address === '') {
            return response()->json([
                'message' => Storefront::lang('alert.pin_no_address'),
            ], 422);
        }

        return response()->json([
            'address' => $address,
            'latitude' => (float)$data['latitude'],
            'longitude' => (float)$data['longitude'],
        ]);
    }

    /**
     * Addresses matching what the customer has typed so far.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['nullable', 'string', 'max:255']]);

        return response()->json([
            'suggestions' => AddressSuggestions::search((string)($data['query'] ?? '')),
        ]);
    }

    /**
     * Coordinates for a picked suggestion that came without them.
     */
    public function place(Request $request): JsonResponse
    {
        $data = $request->validate(['placeId' => ['required', 'string', 'max:500']]);

        $coordinates = AddressSuggestions::coordinates($data['placeId']);
        if ($coordinates === null) {
            return response()->json(['message' => Storefront::lang('alert.address_not_found_check')], 422);
        }

        return response()->json($coordinates);
    }

    public function session(UpdateSessionRequest $request, Fulfilment $action): mixed
    {
        $data = $request->validated();

        return $this->attempt(function() use ($action, $data): RedirectResponse {
            $action->switchLocation((int)$data['location_id'], $data['order_type'] ?? null);

            if (($data['return_to'] ?? null) === 'booking') {
                return redirect()->route('igniter.theme.booking');
            }

            return redirect()->to($this->currentPage());
        });
    }

    /**
     * Stay on the page the customer was viewing. A location menu keeps its query
     * string and takes the slug of the kitchen just chosen, so the address does
     * not switch the kitchen back.
     */
    protected function currentPage(): string
    {
        $previous = url()->previous();
        $path = rtrim((string)(parse_url($previous, PHP_URL_PATH) ?: '/'), '/') ?: '/';
        $query = parse_url($previous, PHP_URL_QUERY);

        if ($path === '/order' || preg_match('#/[^/]+/order$#', $path) === 1) {
            $target = StorefrontRoutes::order();

            return is_string($query) && $query !== '' ? $target.'?'.$query : $target;
        }

        return $previous;
    }
}
