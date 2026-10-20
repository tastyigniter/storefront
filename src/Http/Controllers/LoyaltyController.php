<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\LoyaltyRedeem;
use Illuminate\Http\Request;

class LoyaltyController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function store(Request $request): mixed
    {
        $state = LoyaltyRedeem::state();
        abort_if($state === null, 404);

        $amount = (int)$request->validate(['amount' => ['required', 'integer', 'min:0']])['amount'];
        if ($amount === 0) {
            LoyaltyRedeem::clear();

            return back();
        }

        return $this->attempt(function() use ($amount, $state) {
            LoyaltyRedeem::redeem($amount, $state);

            return back();
        });
    }

    public function destroy(): mixed
    {
        LoyaltyRedeem::clear();

        return back();
    }
}
