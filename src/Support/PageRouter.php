<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Pagic\Router;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Override;

/**
 * page_url() looks up theme pages by name, such as "account.login" or "group-order.join".
 * The storefront has no theme pages, so names it does not find fall back to the storefront route
 * of the same name (igniter.theme.{name}). Links that extensions build in emails and
 * redirects then point at the storefront pages.
 */
class PageRouter extends Router
{
    /**
     * Orange page names whose storefront route has a different name.
     */
    public const array ALIASES = [
        'local.menus' => 'order',
        'local.info' => 'order',
        'local.reviews' => 'order',
        'reservation.reservation' => 'booking',
        'reservation.success' => 'booking.success',
        'account.settings' => 'account.account',
        'account.addresses' => 'account.address',
        'contact.contact' => 'contact',
        'home.home' => 'home',
    ];

    #[Override]
    public function pageUrl(string $name, array $parameters = []): ?string
    {
        $url = parent::pageUrl($name, $parameters);
        if ($url !== $name || !ThemeGate::active()) {
            return $url;
        }

        $route = Route::getRoutes()->getByName('igniter.theme.'.(self::ALIASES[$name] ?? $name));
        if (!$route instanceof RouteInstance) {
            return $url;
        }

        // Only pass the parameters the route uses, e.g. not the location slug Orange pages take.
        $parameters = array_intersect_key($parameters, array_flip($route->parameterNames()));
        if (in_array('location', $route->parameterNames(), true) && ($parameters['location'] ?? '') === '') {
            $location = Location::current();
            if ($location instanceof LocationModel && (string)$location->permalink_slug !== '') {
                $parameters['location'] = (string)$location->permalink_slug;
            }
        }

        try {
            return route($route->getName(), $parameters, false);
        } catch (UrlGenerationException) {
            // A menu link to the order page has no restaurant yet. /order picks one.
            $fallback = Route::getRoutes()->getByName($route->getName().'.current');
            if (!$fallback instanceof RouteInstance) {
                return $url;
            }

            try {
                return route($fallback->getName(), [], false);
            } catch (UrlGenerationException) {
                return $url;
            }
        }
    }
}
