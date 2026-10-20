<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\VendorRegisterRequest;
use Igniter\Storefront\Support\VendorSignup;
use Inertia\Inertia;
use Inertia\Response;

class VendorController
{
    use PresentsStorefront;
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        abort_unless(VendorSignup::available(), 404);

        return Inertia::render('Vendor/Register', VendorSignup::form($this->countries()));
    }

    public function store(VendorRegisterRequest $request): mixed
    {
        return $this->attempt(function() use ($request) {
            $message = VendorSignup::submit($request->validated());

            return redirect()->route('igniter.theme.home')->with('success', $message);
        });
    }
}
