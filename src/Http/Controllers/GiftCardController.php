<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\GiftCards;
use Inertia\Inertia;
use Inertia\Response;

class GiftCardController
{
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        $options = GiftCards::options();
        abort_if($options === null, 404);

        return Inertia::render('GiftCards/Index', [
            'options' => $options,
        ]);
    }
}
