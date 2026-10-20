<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Concerns;

trait ResolvesStorefrontPage
{
    public function getPage(): object
    {
        return new class
        {
            public ?array $settings = null;

            public function getKey(): string
            {
                return 'storefront';
            }
        };
    }
}
