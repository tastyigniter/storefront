<?php

declare(strict_types=1);

namespace Igniter\Storefront\Actions;

use Igniter\Cart\Classes\CartManager;

class UpdateCart
{
    public function add(int $menuId, int $quantity, array $menuOptions, ?string $comment, ?string $rowId = null): void
    {
        resolve(CartManager::class)->addOrUpdateCartItem([
            'rowId' => $rowId,
            'menuId' => $menuId,
            'quantity' => max(1, $quantity),
            'menu_options' => $menuOptions,
            'comment' => $comment,
        ]);
    }

    public function update(string $rowId, ?int $quantity, ?string $comment, ?string $action): void
    {
        $manager = resolve(CartManager::class);

        if ($action === 'plus' || $action === 'minus') {
            $manager->updateCartItemQty($rowId, $action);

            return;
        }

        if ($quantity !== null && $quantity < 1) {
            $manager->removeCartItem($rowId);

            return;
        }

        $attributes = [];
        if ($quantity !== null) {
            $attributes['qty'] = $quantity;
        }

        if ($comment !== null) {
            $attributes['comment'] = $comment;
        }

        if ($attributes === []) {
            return;
        }

        $manager->getCartItem($rowId);
        $manager->getCart()->update($rowId, $attributes);
    }

    public function remove(string $rowId): void
    {
        resolve(CartManager::class)->removeCartItem($rowId);
    }

    public function coupon(?string $code): void
    {
        resolve(CartManager::class)->applyCouponCondition((string)$code);
    }

    public function tip(float $amount, bool $isCustom): void
    {
        // A zero amount makes the tip condition skip itself, which clears the tip.
        resolve(CartManager::class)->applyCondition('tip', [
            'amount' => max(0, $amount),
            'isCustom' => $amount > 0 && $isCustom,
        ]);
    }

    public function clearCoupon(): void
    {
        resolve(CartManager::class)->applyCouponCondition('');
    }

    public function clearGiftCard(): void
    {
        resolve(CartManager::class)->removeCondition('giftup');
    }
}
