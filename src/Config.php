<?php

declare(strict_types=1);

namespace Igniter\Storefront;

/**
 * Plain values a theme passes when it registers. Themes do not extend this.
 */
final readonly class Config
{
    public function __construct(
        public string $theme,
        public string $rootView,
        public string $defaultBackground,
        public string $defaultRing,
        public string $defaultFontUrl,
        public string $defaultFontFamily,
        public string $pwaDirectory,
        // The theme's own language namespace, e.g. `igniterlabs.glass`. Each theme words its storefront differently.
        public string $langNamespace,
    ) {}
}
