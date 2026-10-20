<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

class Money
{
    public static function label(float|int|string|null $amount): string
    {
        return html_entity_decode(strip_tags(currency_format($amount ?? 0)));
    }
}
