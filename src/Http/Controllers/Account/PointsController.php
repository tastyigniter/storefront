<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\LoyaltyRedeem;
use Inertia\Inertia;
use Inertia\Response;

class PointsController
{
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        $history = LoyaltyRedeem::history();
        abort_if($history === null, 404);

        return Inertia::render('Account/Points', $history);
    }
}
