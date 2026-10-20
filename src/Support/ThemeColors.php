<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Storefront\Storefront;

/**
 * Background and focus ring from the active theme's custom data, falling back
 * to the colours on the storefront config.
 */
final class ThemeColors
{
    public static function background(): string
    {
        return self::color('color_background', Storefront::config()->defaultBackground);
    }

    public static function ring(): string
    {
        return self::color('color_ring', Storefront::config()->defaultRing);
    }

    protected static function color(string $key, string $fallback): string
    {
        $value = ThemeContent::value($key);
        if (!is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            return strtolower($fallback);
        }

        return strtolower($value);
    }
}
