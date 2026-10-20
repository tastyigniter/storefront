<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Socialite\Classes\ProviderManager;
use Igniter\Storefront\Storefront;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Social sign-in buttons and the confirm-email step. Provider redirects land on
 * /confirm-email when the provider did not share an email address.
 */
class SocialiteLogin
{
    public static ?object $manager = null;

    public static function manager(): ?object
    {
        if (self::$manager !== null) {
            return self::$manager;
        }

        $class = ProviderManager::class;

        return class_exists($class) ? resolve($class) : null;
    }

    /**
     * @return list<array{code: string, label: string, url: string}>
     */
    public static function links(): array
    {
        $manager = self::manager();

        return $manager ? self::present($manager) : [];
    }

    /**
     * @return list<array{code: string, label: string, url: string}>
     */
    public static function present(object $manager): array
    {
        $labels = [];
        foreach (self::call($manager, 'listProviders') as $info) {
            if (is_array($info) && isset($info['code'])) {
                $labels[(string)$info['code']] = (string)($info['label'] ?? $info['code']);
            }
        }

        $success = route('igniter.theme.account.account');
        $error = route('igniter.theme.account.login');
        $links = [];

        foreach (self::call($manager, 'listProviderLinks') as $code => $url) {
            $target = (string)$url;
            $join = str_contains($target, '?') ? '&' : '?';
            $links[] = [
                'code' => (string)$code,
                'label' => $labels[(string)$code] ?? (string)$code,
                'url' => $target.$join.http_build_query(['success' => $success, 'error' => $error]),
            ];
        }

        return $links;
    }

    public static function hasPending(): bool
    {
        $data = self::providerData();

        return is_object($data) && isset($data->user);
    }

    public static function pendingEmail(): string
    {
        $data = self::providerData();
        $email = is_object($data) ? ($data->user->email ?? '') : '';

        return is_string($email) ? $email : '';
    }

    public static function confirm(string $email): ?RedirectResponse
    {
        $manager = self::manager();
        $data = self::providerData();
        if (!$manager || !is_object($data) || !isset($data->user)) {
            throw new ApplicationException(Storefront::lang('alert.socialite_missing'));
        }

        $validated = validator(['email' => $email], [
            'email' => ['required', 'email:filter', 'max:96', 'unique:customers,email'],
        ])->validate();

        $data->user->email = $validated['email'];
        self::call($manager, 'setProviderData', [$data]);
        $redirect = self::call($manager, 'completeCallback');

        return $redirect instanceof RedirectResponse ? $redirect : null;
    }

    protected static function providerData(): mixed
    {
        $manager = self::manager();

        return $manager ? self::call($manager, 'getProviderData') : null;
    }

    protected static function call(object $manager, string $method, array $arguments = []): mixed
    {
        return $manager->{$method}(...$arguments);
    }
}
