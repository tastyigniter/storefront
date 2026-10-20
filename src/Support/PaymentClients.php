<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Models\Order;
use Igniter\PayRegister\Models\Payment;
use Igniter\PayRegister\Payments\AuthorizeNetAim;
use Igniter\PayRegister\Payments\Square;
use Igniter\PayRegister\Payments\Stripe;
use LogicException;

/**
 * Browser config for gateways that collect card details with their own JS SDK,
 * mirroring the payregister payment_form partials the Orange theme renders.
 */
class PaymentClients
{
    public const string STRIPE = 'stripe';

    public const string SQUARE = 'square';

    public const string AUTHORIZE_NET = 'authorizenet';

    public static function type(Payment $payment): ?string
    {
        $gateway = $payment->getGatewayObject();

        return match (true) {
            $gateway instanceof Stripe => $gateway->isOffsiteMode() ? null : self::STRIPE,
            $gateway instanceof Square => self::SQUARE,
            $gateway instanceof AuthorizeNetAim => self::AUTHORIZE_NET,
            default => null,
        };
    }

    public static function config(Payment $payment, Order $order): ?array
    {
        $type = self::type($payment);
        if ($type === null) {
            return null;
        }

        $gateway = $payment->getGatewayObject();
        $customer = $order->customer;
        $profile = $customer && $payment->paymentProfileExists($customer)
            ? $payment->findPaymentProfile($customer)
            : null;

        $config = [
            'type' => $type,
            'hidden' => $gateway->getHiddenFields(),
            'profile' => $profile ? [
                'brand' => (string)$profile->card_brand,
                'last4' => (string)$profile->card_last4,
            ] : null,
            'canSaveCard' => $customer !== null && $gateway->supportsPaymentProfiles(),
        ];

        return $config + match ($type) {
            self::STRIPE => [
                'publishableKey' => $gateway->getPublishableKey(),
                'clientSecret' => $profile ? null : $gateway->createOrFetchIntent($order),
                'options' => $gateway->getStripeJsOptions($order),
            ],
            self::SQUARE => [
                'sdkUrl' => 'https://'.($gateway->isTestMode() ? 'sandbox.' : '').'web.squarecdn.com/v1/square.js',
                'applicationId' => $gateway->getAppId(),
                'locationId' => $gateway->getLocationId(),
                'amount' => number_format((float)$order->order_total, 2, '.', ''),
                'currency' => currency()->getUserCurrency(),
            ],
            self::AUTHORIZE_NET => [
                'endpoint' => $gateway->getEndPoint(),
                'apiLoginId' => $gateway->getApiLoginID(),
                'clientKey' => $gateway->getClientKey(),
            ],
            default => throw new LogicException('Unexpected payment client ['.$type.'].'),
        };
    }
}
