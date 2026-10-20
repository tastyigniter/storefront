<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\LocationDirectory;
use Igniter\Storefront\Support\Pagination;
use Igniter\Storefront\Support\Reviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController
{
    use ResolvesStorefrontPage;

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:'.implode(',', array_keys(LocationDirectory::ORDER_TYPES))],
            'sort' => ['nullable', 'in:name,distance'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $cuisine = $request->integer('cuisine') ?: null;
        $sort = $data['sort'] ?? 'name';
        $from = $sort === 'distance'
            ? LocationDirectory::origin(isset($data['lat']) ? (float)$data['lat'] : null, isset($data['lng']) ? (float)$data['lng'] : null)
            : null;

        $page = Pagination::items(LocationDirectory::locations($cuisine, (string)($data['search'] ?? ''), $data['type'] ?? null, $from));

        return Inertia::render('Locations/Index', [
            'locations' => $page['items'],
            'pagination' => $page['pagination'],
            'cuisines' => LocationDirectory::cuisines(),
            'cuisine' => $cuisine,
            'filters' => [
                'search' => (string)($data['search'] ?? ''),
                'type' => $data['type'] ?? null,
                'sort' => $sort,
                // Nearest first was asked for, but there is no position to measure from.
                'needsPosition' => $sort === 'distance' && $from === null,
            ],
        ]);
    }

    /**
     * A location's approved reviews, a page at a time, for the reviews drawer on the menu.
     */
    public function reviews(Request $request, string $slug): JsonResponse
    {
        abort_unless(Reviews::enabled(), 404);

        $model = LocationModel::query()->whereIsEnabled()->where('permalink_slug', $slug)->first();
        abort_unless($model instanceof LocationModel, 404);

        return response()->json(Reviews::forLocation($model, $request->integer('page', 1)));
    }
}
