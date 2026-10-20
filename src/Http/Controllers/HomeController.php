<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Local\Facades\Location;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\ThemeContent;
use Inertia\Inertia;
use Inertia\Response;

class HomeController
{
    use PresentsStorefront;
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        return Inertia::render('Home/Index', [
            'home' => ThemeContent::home($this->location(), (string)Location::orderType()),
        ]);
    }
}
