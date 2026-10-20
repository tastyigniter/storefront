<?php

declare(strict_types=1);

namespace Igniter\Storefront;

use Igniter\Main\Classes\ThemeManager;
use RuntimeException;
use Throwable;

/**
 * Themes call register() with a config value. Routes and pages then run only
 * for the config whose theme code is the active theme.
 */
final class Storefront
{
    /** @var array<string, Config> */
    private static array $themes = [];

    public static function register(Config $config): void
    {
        self::$themes[$config->theme] = $config;

        app()->register(ServiceProvider::class);
    }

    public static function active(): ?Config
    {
        try {
            $theme = resolve(ThemeManager::class)->getActiveTheme();
        } catch (Throwable) {
            return null;
        }

        if ($theme === null) {
            return null;
        }

        return self::$themes[$theme->getName()] ?? null;
    }

    public static function config(): Config
    {
        return self::active() ?? throw new RuntimeException('No storefront theme is active.');
    }

    /**
     * A line from the active theme's language namespace (`<namespace>::default.<key>`),
     * as edited under Localisation > Languages.
     */
    public static function lang(string $key, array $replace = []): string
    {
        return lang(self::config()->langNamespace.'::default.'.$key, $replace);
    }

    /**
     * @return array<string, Config>
     */
    public static function themes(): array
    {
        return self::$themes;
    }
}
