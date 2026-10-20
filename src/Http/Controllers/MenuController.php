<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\MenuCatalog;
use Igniter\Storefront\Support\Schedules;
use Igniter\Storefront\Support\StorefrontRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController
{
    use PresentsStorefront;
    use ResolvesStorefrontPage;

    /**
     * /order follows the current location, the same way Orange sends /local/menus to /{location}/menus.
     */
    public function current(): RedirectResponse
    {
        return redirect()->to(StorefrontRoutes::order());
    }

    public function index(Request $request): Response
    {
        // CheckLocation has already selected the kitchen named in {location}.
        $location = $this->location();

        return Inertia::render('Order/Menu', [
            'groups' => $location instanceof LocationModel
                ? MenuCatalog::grouped($location, (string)Location::orderType(), (string)$request->query('search', ''))
                : [],
            'search' => (string)$request->query('search', ''),
            'fulfilmentOptions' => Schedules::fulfilmentOptions(),
        ]);
    }
}
