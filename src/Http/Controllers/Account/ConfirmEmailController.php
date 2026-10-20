<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\SocialiteLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmEmailController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(): Response|RedirectResponse
    {
        if (!SocialiteLogin::hasPending()) {
            return redirect()->route('igniter.theme.account.login');
        }

        return Inertia::render('Account/ConfirmEmail', [
            'email' => SocialiteLogin::pendingEmail(),
        ]);
    }

    public function store(Request $request): mixed
    {
        return $this->attempt(function() use ($request) {
            $redirect = SocialiteLogin::confirm((string)$request->input('email'));

            return $redirect ?? redirect()->route('igniter.theme.account.account');
        });
    }
}
