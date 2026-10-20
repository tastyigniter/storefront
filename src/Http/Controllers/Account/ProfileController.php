<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\UpdateProfileRequest;
use Igniter\Storefront\Storefront;
use Illuminate\Http\Request;

class ProfileController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function update(UpdateProfileRequest $request, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($request, $action) {
            $action->updateProfile($request->validated());

            return back()->with('success', Storefront::lang('alert.profile_saved'));
        });
    }

    public function destroy(Request $request, ManageAccount $action): mixed
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        return $this->attempt(function() use ($action, $data) {
            $action->deleteAccount($data['password']);

            return redirect()->route('igniter.theme.home')->with('success', Storefront::lang('alert.account_deleted'));
        });
    }
}
