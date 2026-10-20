<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\ForgotPasswordRequest;
use Igniter\Storefront\Storefront;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        return Inertia::render('Account/ForgotPassword');
    }

    public function store(ForgotPasswordRequest $request, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($request, $action) {
            $action->forgot($request->validated());

            return back()->with('success', Storefront::lang('alert.reset_link_sent'));
        });
    }
}
