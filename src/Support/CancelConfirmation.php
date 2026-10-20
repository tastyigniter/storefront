<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Storefront\Storefront;
use Igniter\User\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * A signed-in customer can cancel their own order or booking.
 * A guest has to enter the email address on the order or booking first.
 */
final class CancelConfirmation
{
    public static function required(?int $customerId): bool
    {
        $customer = Auth::customer();

        return !$customerId || !$customer || $customerId !== (int)$customer->getKey();
    }

    public static function authorize(?int $customerId, mixed $email, mixed $expected, string $missing): void
    {
        $customer = Auth::customer();
        if ($customerId && $customer && $customerId === (int)$customer->getKey()) {
            return;
        }

        // Someone else's account record is not cancelled by guessing the email.
        if ($customerId) {
            throw new ApplicationException($missing);
        }

        self::match($email, $expected);
    }

    public static function match(mixed $email, mixed $expected): void
    {
        $given = strtolower(trim(is_string($email) ? $email : ''));
        $actual = strtolower(trim(is_string($expected) ? $expected : ''));
        if ($given === '' || $actual === '' || strlen($given) !== strlen($actual) || !hash_equals($actual, $given)) {
            throw ValidationException::withMessages([
                'email' => Storefront::lang('alert.cancel_email_mismatch'),
            ]);
        }
    }
}
