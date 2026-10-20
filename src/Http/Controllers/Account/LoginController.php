<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\LoginRequest;
use Igniter\Storefront\Support\SocialiteLogin;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LoginController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        return Inertia::render('Account/Login', [
            'registrationAllowed' => (bool)setting('allow_registration', true),
            'providers' => SocialiteLogin::links(),
        ]);
    }

    public function store(LoginRequest $request, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($request, $action) {
            $action->login($request->validated());

            return redirect()->intended(route('igniter.theme.account.account'));
        });
    }

    public function destroy(ManageAccount $action): RedirectResponse
    {
        $action->logout();

        return redirect()->route('igniter.theme.home');
    }
}
