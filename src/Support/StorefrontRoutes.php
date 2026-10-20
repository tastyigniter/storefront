<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Illuminate\Support\Facades\Route;

/**
 * URI templates for the storefront routes, shared with the Vue app so links
 * and form posts use route names instead of hardcoded paths.
 */
class StorefrontRoutes
{
    use ReadsKey;

    /**
     * Paths used when the storefront routes are not registered yet.
     * Theme activation syncs these menus after routing has already run.
     *
     * @var array<string, string>
     */
    protected const array PATHS = [
        'igniter.theme.order.current' => '/order',
        'igniter.theme.locations' => '/locations',
        'igniter.theme.booking' => '/book-table',
        'igniter.theme.contact' => '/contact',
    ];

    /**
     * Relative URL for a storefront route. Menu definitions are loaded while a
     * theme is activated, before these routes exist, so the path is used then.
     */
    public static function url(string $name): string
    {
        if (Route::has($name)) {
            return route($name, absolute: false);
        }

        $path = self::PATHS[$name] ?? '/';
        $prefix = trim((string)config('igniter-routes.uri'), '/');

        return ($prefix === '' ? '' : '/'.$prefix).$path;
    }

    public static function order(?LocationModel $location = null): string
    {
        $location ??= Location::current();
        $slug = $location instanceof LocationModel ? (string)$location->permalink_slug : '';

        return $slug !== ''
            ? route('igniter.theme.order', ['location' => $slug])
            : route('igniter.theme.locations');
    }

    /**
     * @return ($key is null ? array<string, string> : mixed)
     */
    public static function templates(?string $key = null, mixed $default = null): mixed
    {
        $templates = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if (!is_string($name) || !str_starts_with($name, 'igniter.theme.')) {
                continue;
            }

            if (!str_starts_with($route->getActionName(), 'Igniter\\Storefront\\Http\\Controllers\\')) {
                continue;
            }

            $templates[$name] = '/'.ltrim($route->uri(), '/');
        }

        ksort($templates);

        return self::read($templates, $key, $default);
    }
}
