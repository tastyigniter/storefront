<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\User\Facades\Auth;

/**
 * Gift card purchase page. Redeeming a card stays on the basket coupon field,
 * which already reaches the extension's coupon condition.
 */
class GiftCards
{
    /** @var array<string, mixed>|null */
    public static ?array $options = null;

    public static function installed(): bool
    {
        return class_exists('IgniterLabs\\GiftUp\\Models\\Settings');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function options(): ?array
    {
        if (self::$options !== null) {
            return self::$options;
        }

        return self::installed() ? self::load() : null;
    }

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    protected static function load(): array
    {
        $settings = 'IgniterLabs\\GiftUp\\Models\\Settings';
        $customer = Auth::customer();

        return [
            'companyId' => (string)$settings::getCompanyId(),
            'domain' => '',
            'product' => '',
            'group' => '',
            'language' => 'en-GB',
            'purchaserName' => trim((string)($customer->first_name ?? '').' '.(string)($customer->last_name ?? '')),
            'purchaserEmail' => (string)($customer->email ?? ''),
            'recipientName' => '',
            'recipientEmail' => '',
            'step' => 'details',
            'whoFor' => '',
            'promoCode' => '',
            'hideArtwork' => false,
            'hideGroups' => false,
            'hideUngroupedItems' => false,
            'hideCustomValue' => false,
            'customValueAmount' => '',
        ];
    }
}
