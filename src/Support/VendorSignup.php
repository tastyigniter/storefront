<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Storefront\Storefront;
use Igniter\System\Models\Country;
use Illuminate\Support\Facades\DB;

/**
 * Public vendor registration. Cuisine choices come from the multivendor extension.
 */
class VendorSignup
{
    public static bool $enabled = false;

    /** @var (callable(array<string, mixed>): string)|null */
    public static $handler = null;

    public static function available(): bool
    {
        if (self::$enabled) {
            return true;
        }

        $settings = 'IgniterLabs\\MultiVendor\\Models\\Settings';

        return class_exists($settings)
            && class_exists('IgniterLabs\\MultiVendor\\Classes\\Manager')
            && $settings::isConfigured();
    }

    /**
     * @param list<array{id: int, name: string}> $countries
     * @return array{countries: list<array{id: int, name: string}>, cuisines: list<array{id: int, name: string}>, countryId: int}
     */
    public static function form(array $countries): array
    {
        return [
            'countries' => $countries,
            'cuisines' => self::cuisines(),
            'countryId' => (int)(Country::getDefaultKey() ?? 0),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function cuisines(): array
    {
        $class = 'IgniterLabs\\MultiVendor\\Models\\Cuisine';

        return class_exists($class) ? self::cuisineOptions($class) : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function submit(array $data): string
    {
        return self::$handler
            ? (self::$handler)($data)
            : self::register($data);
    }

    /**
     * @return list<array{id: int, name: string}>
     *
     * @codeCoverageIgnore
     */
    protected static function cuisineOptions(string $class): array
    {
        return $class::whereIsEnabled()->orderBy('priority')->get(['cuisine_id', 'name'])
            ->map(fn($cuisine): array => [
                'id' => (int)$cuisine->cuisine_id,
                'name' => (string)$cuisine->name,
            ])->values()->all();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @codeCoverageIgnore
     */
    protected static function register(array $data): string
    {
        $settings = 'IgniterLabs\\MultiVendor\\Models\\Settings';
        $approve = !$settings::requireVendorApproval();
        $data['country_id'] = $data['countryId'] ?? null;

        $vendor = DB::transaction(
            fn() => resolve('IgniterLabs\\MultiVendor\\Classes\\Manager')->register($data, $approve),
        );

        $location = $vendor->locations()->first();
        if ($location && !empty($data['cuisines']) && method_exists($location, 'cuisines')) {
            $location->cuisines()->sync((array)$data['cuisines']);
        }

        $mail = 'Facades\\Igniter\\System\\Helpers\\MailHelper';
        $mail::queueTemplate('igniterlabs.multivendor::mail.registration', ['vendor' => $vendor], [
            $vendor->owner_email, $vendor->owner_name,
        ]);
        $mail::queueTemplate('igniterlabs.multivendor::mail.registration_alert', [
            'vendor' => $vendor,
            'vendor_approval_link' => admin_url('igniterlabs/multivendor/vendors/edit/'.$vendor->id),
        ], [setting('site_email'), setting('site_name')]);

        return $approve
            ? Storefront::lang('alert.vendor_ready')
            : Storefront::lang('alert.vendor_pending');
    }
}
