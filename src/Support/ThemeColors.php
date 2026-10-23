<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Storefront\Storefront;

/**
 * Shell background and focus ring from the active theme's custom data, falling back
 * to the colours on the storefront config.
 */
final class ThemeColors
{
    public static function background(): string
    {
        $config = Storefront::config();
        $keys = array_values(array_unique([
            $config->appBackgroundKey,
            'color_neutral_light',
            'color_neutral_dark',
            'color_background',
        ]));

        return self::firstColor($keys, $config->defaultBackground);
    }

    public static function ring(): string
    {
        $keys = ['color_accent', 'color_ring'];

        return self::firstColor($keys, Storefront::config()->defaultRing);
    }

    /**
     * @param list<string> $keys
     */
    protected static function firstColor(array $keys, string $fallback): string
    {
        foreach ($keys as $key) {
            $value = ThemeContent::value($key);
            if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
                return strtolower($value);
            }
        }

        return strtolower($fallback);
    }
}
