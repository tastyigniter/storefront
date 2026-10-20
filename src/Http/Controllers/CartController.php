<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Actions\UpdateCart;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\AddToCartRequest;
use Igniter\Storefront\Http\Requests\ApplyCouponRequest;
use Igniter\Storefront\Http\Requests\UpdateCartRequest;
use Igniter\Storefront\Support\MenuCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function store(AddToCartRequest $request, UpdateCart $action): mixed
    {
        $data = $request->validated();

        return $this->attempt(function() use ($action, $data): RedirectResponse {
            $action->add(
                (int)$data['menu_id'],
                (int)($data['quantity'] ?? 1),
                (array)($data['menu_options'] ?? []),
                $data['comment'] ?? null,
                $data['row_id'] ?? null,
            );

            return back();
        });
    }

    public function item(int $menuId): JsonResponse
    {
        $location = Location::current() ?? Location::currentOrDefault();
        $item = $location instanceof LocationModel ? MenuCatalog::find($location, $menuId) : null;

        abort_unless($item !== null, 404);

        return response()->json($item);
    }

    public function update(UpdateCartRequest $request, string $rowId, UpdateCart $action): mixed
    {
        $data = $request->validated();

        return $this->attempt(function() use ($action, $rowId, $data): RedirectResponse {
            $action->update(
                $rowId,
                array_key_exists('quantity', $data) ? (int)$data['quantity'] : null,
                $data['comment'] ?? null,
                $data['action'] ?? null,
            );

            return back();
        });
    }

    public function destroy(string $rowId, UpdateCart $action): mixed
    {
        return $this->attempt(function() use ($action, $rowId): RedirectResponse {
            $action->remove($rowId);

            return back();
        });
    }

    public function tip(Request $request, UpdateCart $action): mixed
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:10000'],
            'custom' => ['boolean'],
        ]);

        return $this->attempt(function() use ($action, $data): RedirectResponse {
            $action->tip((float)$data['amount'], (bool)($data['custom'] ?? false));

            return back();
        });
    }

    public function removeCoupon(UpdateCart $action): mixed
    {
        return $this->attempt(function() use ($action): RedirectResponse {
            $action->clearCoupon();

            return back();
        });
    }

    public function removeGiftCard(UpdateCart $action): mixed
    {
        return $this->attempt(function() use ($action): RedirectResponse {
            $action->clearGiftCard();

            return back();
        });
    }

    public function coupon(ApplyCouponRequest $request, UpdateCart $action): mixed
    {
        $data = $request->validated();

        return $this->attempt(function() use ($action, $data): RedirectResponse {
            $action->coupon($data['code'] ?? '');

            return back();
        });
    }
}
