<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Storefront\Config;
use Igniter\Storefront\Storefront;

class ThemeGate
{
    public static function active(): bool
    {
        return Storefront::active() instanceof Config;
    }
}
