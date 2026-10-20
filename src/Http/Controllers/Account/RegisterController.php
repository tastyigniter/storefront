<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\RegisterRequest;
use Igniter\Storefront\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(Request $request, ManageAccount $action): Response|RedirectResponse
    {
        if ($request->filled('code')) {
            $action->activate((string)$request->query('code'));

            return redirect()->route('igniter.theme.account.account')->with('success', Storefront::lang('alert.account_ready'));
        }

        return Inertia::render('Account/Register', [
            'registrationAllowed' => (bool)setting('allow_registration', true),
        ]);
    }

    public function store(RegisterRequest $request, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($request, $action) {
            $customer = $action->register($request->validated());

            return $customer->is_activated
                ? redirect()->route('igniter.theme.account.account')->with('success', Storefront::lang('alert.welcome'))
                : redirect()->route('igniter.theme.account.login')->with('success', Storefront::lang('alert.check_email'));
        });
    }
}
