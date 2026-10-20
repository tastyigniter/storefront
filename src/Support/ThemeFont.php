<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Storefront\Storefront;

/**
 * The storefront typeface. A Google Fonts CSS URL replaces the theme's default
 * family; the first family in that URL is used for the whole site.
 */
class ThemeFont
{
    public static function stylesheet(): string
    {
        return self::url() ?? Storefront::config()->defaultFontUrl;
    }

    public static function css(): ?string
    {
        $family = self::family();
        if ($family === null || strcasecmp($family, Storefront::config()->defaultFontFamily) === 0) {
            return null;
        }

        return ":root{--font-sans:'".$family."',ui-sans-serif,system-ui,sans-serif;}";
    }

    protected static function url(): ?string
    {
        $url = ThemeContent::value('font_url');
        if (!is_string($url) || self::familyFrom($url) === null) {
            return null;
        }

        return $url;
    }

    protected static function family(): ?string
    {
        return self::familyFrom(self::url() ?? '');
    }

    protected static function familyFrom(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== 'fonts.googleapis.com'
            || isset($parts['user'])
            || !in_array($parts['path'] ?? '', ['/css', '/css2'], true)
            || !is_string($parts['query'] ?? null)
        ) {
            return null;
        }

        if (preg_match('/(?:^|&)family=([^&]+)/', $parts['query'], $match) !== 1) {
            return null;
        }

        $family = trim(explode(':', urldecode($match[1]), 2)[0]);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{0,80}$/', $family) !== 1) {
            return null;
        }

        return $family;
    }
}
