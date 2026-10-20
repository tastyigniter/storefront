<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\ResetPasswordRequest;
use Igniter\Storefront\Storefront;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(string $code): Response
    {
        return Inertia::render('Account/ResetPassword', [
            'code' => $code,
        ]);
    }

    public function store(ResetPasswordRequest $request, string $code, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($request, $code, $action) {
            $action->reset($code, $request->validated());

            return redirect()->route('igniter.theme.account.login')->with('success', Storefront::lang('alert.password_updated'));
        });
    }
}
