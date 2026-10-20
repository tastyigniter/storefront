<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\SaveAddressRequest;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\Pagination;
use Igniter\User\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController
{
    use PresentsStorefront;
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(Request $request): Response
    {
        $customer = Auth::customer();
        $editing = null;
        if ($request->filled('address')) {
            $editing = $customer->addresses()->whereKey((int)$request->query('address'))->first();
        }

        $page = Pagination::query($customer->addresses(), fn(object $address): array => $this->addressPayload($address));

        return Inertia::render('Account/Address', [
            'countries' => $this->countries(),
            'addresses' => $page['items'],
            'pagination' => $page['pagination'],
            'editing' => $editing ? $this->addressPayload($editing) : null,
        ]);
    }

    public function store(SaveAddressRequest $request, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($request, $action) {
            $action->saveAddress(
                $request->safe()->except(['address_id']),
                $request->integer('address_id') ?: null,
            );

            return redirect()->route('igniter.theme.account.address')->with('success', Storefront::lang('alert.address_saved'));
        });
    }

    public function destroy(int $addressId, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($addressId, $action) {
            $action->deleteAddress($addressId);

            return back()->with('success', Storefront::lang('alert.address_removed'));
        });
    }
}
