<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        return Inertia::render('Account/Dashboard');
    }
}
