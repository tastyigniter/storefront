<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Local\Facades\Location;
use Igniter\Storefront\Storefront;
use Illuminate\Support\Str;

/**
 * Page title and meta description. Shared for every storefront route and printed
 * in the root view, so the first page load has them before Vue starts.
 */
class PageMeta
{
    /**
     * Storefront routes with a title and description in the language file (meta.{route}_title).
     */
    protected const array ROUTES = [
        'order', 'checkout.checkout', 'checkout.success', 'booking', 'booking.success', 'contact',
        'account.login', 'account.register', 'account.forgot', 'account.reset', 'account.account',
        'account.orders', 'account.order', 'account.reservations', 'account.reservation', 'account.address',
        'account.confirm', 'account.points', 'locations', 'gift-cards', 'vendor.register',
    ];

    /**
     * Title and description for a storefront route, by route name.
     *
     * @return array{title: string, description: string}
     */
    public static function forRoute(?string $routeName): array
    {
        $route = Str::after((string)$routeName, 'igniter.theme.');
        if (!in_array($route, self::ROUTES, true)) {
            return self::make(null);
        }

        $key = str_replace('.', '_', $route);
        $location = (string)(Location::current() ?? Location::currentOrDefault())?->location_name;

        return self::make(
            self::line('meta.'.$key.'_title'),
            self::line('meta.'.$key.'_description', ['location' => $location ?: self::siteName()]),
        );
    }

    /**
     * A line from the active theme's language file.
     */
    public static function line(string $key, array $replace = []): string
    {
        return Storefront::lang($key, $replace);
    }

    /**
     * "Page title | Site name", with the site description when none is given.
     *
     * @return array{title: string, description: string}
     */
    public static function make(?string $title, ?string $description = null): array
    {
        $siteName = self::siteName();
        $title = trim((string)$title);
        $description = self::clean($description);

        return [
            'title' => $title !== '' && $title !== $siteName ? $title.' - '.$siteName : $siteName,
            'description' => $description !== '' ? $description : self::siteDescription(),
        ];
    }

    /**
     * The description from the theme settings, used for the homepage and as a fallback.
     */
    public static function siteDescription(): string
    {
        return self::clean((string)ThemeContent::value('meta_description', '')) ?: self::line('meta.default_description');
    }

    protected static function siteName(): string
    {
        return (string)setting('site_name', self::line('site.name'));
    }

    /**
     * Plain text, kept to the length search engines show.
     */
    protected static function clean(?string $text): string
    {
        $text = trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$text))));

        return Str::limit($text, 160, '…');
    }
}
