<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Database\Attach\Media;
use Igniter\Frontend\Models\Banner;
use Igniter\Frontend\Models\CaptchaSettings;
use Igniter\Frontend\Models\Slider;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Main\Classes\Theme;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Helpers\ImageHelper;
use Igniter\Pages\Classes\MenuManager;
use Igniter\Pages\Models\Menu as StaticMenu;
use Igniter\Storefront\Storefront;
use Stevebauman\Purify\Facades\Purify;
use Throwable;

/**
 * Content managed in the admin: theme Customize settings, sliders, banners,
 * static pages and menus (igniter.pages) and reCAPTCHA keys (igniter.frontend).
 */
class ThemeContent
{
    use ReadsKey;

    public static function theme(): ?Theme
    {
        return resolve(ThemeManager::class)->getActiveTheme();
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return self::theme()?->getCustomData()[$key] ?? $default;
    }

    /**
     * Shared with every page: footer, social links, cookie banner and captcha.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public static function site(?string $key = null, mixed $default = null): mixed
    {
        $moreInfo = self::value('gdpr_more_info_link');

        $site = [
            'mainMenu' => self::menu('main-menu'),
            'footerMenu' => self::footerMenu(),
            'social' => collect((array)self::value('social', []))
                ->filter(fn($link): bool => is_array($link) && !empty($link['url']))
                ->map(fn(array $link): array => [
                    'network' => (string)($link['network'] ?? 'other'),
                    'url' => (string)$link['url'],
                ])
                ->values()
                ->all(),
            'cookieBanner' => (bool)self::value('enable_gdpr', true) ? [
                'message' => (string)self::value('gdpr_cookie_message', ''),
                'acceptText' => (string)self::value('gdpr_accept_text', Storefront::lang('site.accept')),
                'moreInfoText' => (string)self::value('gdpr_more_info_text', ''),
                'moreInfoUrl' => $moreInfo ? '/'.ltrim((string)$moreInfo, '/') : null,
            ] : null,
            'captcha' => self::captcha(),
            'brand' => self::brand(),
            'themeColor' => ThemeColors::background(),
            'pwaIcon' => self::pwaIcon(),
            // Customize > General. Category images only apply to themes that draw category banners.
            'display' => [
                'menuImages' => (bool)self::value('show_menu_images', true),
                'categoryImages' => (bool)self::value('show_category_images', true),
            ],
            // Customize > General: suggest addresses as the customer types.
            'addressSuggestions' => (bool)self::value('address_suggestions', true),
        ];

        return self::read($site, $key, $default);
    }

    /**
     * Header and browser-tab artwork. A theme upload wins, then the restaurant
     * logo from System > Settings > General. An empty logo means the flame icon.
     *
     * @return ($key is null ? array{logo: ?string, favicon: ?string, showName: bool} : mixed)
     */
    public static function brand(?string $key = null, mixed $default = null): mixed
    {
        $restaurantLogo = self::imageUrl(setting('site_logo'));

        $brand = [
            'logo' => self::imageUrl(self::value('logo_image')) ?? $restaurantLogo,
            'favicon' => self::imageUrl(self::value('favicon')) ?? $restaurantLogo,
            'showName' => self::value('logo_display', 'beside') !== 'logo',
        ];

        return self::read($brand, $key, $default);
    }

    /** Uploaded home-screen icon, or null when the theme's own icons are used. */
    public static function pwaIcon(): ?string
    {
        return self::imageUrl(self::value('pwa_icon'));
    }

    protected static function imageUrl(mixed $path): ?string
    {
        if (!is_string($path) || $path === '' || $path === 'no_photo.png') {
            return null;
        }

        return media_url($path);
    }

    /**
     * Footer columns from the theme's footer menu under Design > Static Menus.
     *
     * @return list<array{title: string, url: ?string, items: list<array{title: string, url: string}>}>
     */
    public static function footerMenu(): array
    {
        return self::menu('footer-menu');
    }

    /**
     * Resolve a theme static menu (igniter.pages) into plain links, syncing the
     * theme's menu definitions the first time one is missing.
     *
     * @return list<array{title: string, url: ?string, items: list<array{title: string, url: ?string, items: array}>}>
     */
    public static function menu(string $code): array
    {
        $theme = self::theme();
        if (!$theme instanceof Theme || !class_exists(StaticMenu::class)) {
            return [];
        }

        try {
            $find = fn() => StaticMenu::query()->where('theme_code', $theme->getName())->where('code', $code)->first();
            $menu = $find();
            if (!$menu) {
                StaticMenu::syncAll();
                $menu = $find();
            }

            if (!$menu instanceof StaticMenu) {
                return [];
            }

            $references = resolve(MenuManager::class)->generateReferences($menu, (object)['activeMenuItem' => null]);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        return self::mapMenuItems($references);
    }

    /**
     * @param array<int, object> $items
     * @return list<array{title: string, url: ?string, items: array}>
     */
    protected static function mapMenuItems(array $items): array
    {
        return collect($items)->map(fn(object $item): array => [
            'title' => self::menuTitle((string)$item->title),
            'url' => self::relativeUrl($item->url ?: null),
            'items' => self::mapMenuItems((array)$item->items),
        ])->reject(fn(array $item): bool => self::isLocationsPage($item['url']))->values()->all();
    }

    /**
     * Single location mode drops the locations directory from every menu.
     */
    protected static function isLocationsPage(?string $url): bool
    {
        if (!self::singleLocation() || $url === null || $url === '') {
            return false;
        }

        $path = rtrim((string)(parse_url($url, PHP_URL_PATH) ?: $url), '/') ?: '/';
        $locations = rtrim((string)(parse_url(StorefrontRoutes::url('igniter.theme.locations'), PHP_URL_PATH) ?: '/locations'), '/') ?: '/locations';

        return $path === $locations;
    }

    /**
     * Menu titles saved as language keys (igniterlabs.plum::default.menu_menu) are translated.
     * Plain titles from Design > Static Menus are left as written.
     */
    protected static function menuTitle(string $title): string
    {
        if (!str_contains($title, '::')) {
            return $title;
        }

        return lang($title);
    }

    /**
     * Site key for the reCAPTCHA widget, or null when keys are not configured.
     */
    public static function captcha(): ?array
    {
        if (!class_exists(CaptchaSettings::class) || !self::captchaEnabled()) {
            return null;
        }

        return [
            'siteKey' => (string)CaptchaSettings::get('api_site_key'),
            'lang' => CaptchaSettings::getLang(),
        ];
    }

    public static function captchaEnabled(): bool
    {
        return class_exists(CaptchaSettings::class)
            && (string)CaptchaSettings::get('api_site_key') !== ''
            && CaptchaSettings::getApiSecretKey() !== '';
    }

    /**
     * Validation rules that add the reCAPTCHA check when it is configured.
     */
    public static function captchaRules(): array
    {
        return self::captchaEnabled() ? ['g-recaptcha-response' => ['recaptcha']] : [];
    }

    /**
     * Homepage hero text, slides, banners and featured dishes.
     */
    public static function home(?LocationModel $location, string $orderType): array
    {
        $featuredIds = array_map(intval(...), array_filter((array)self::value('featured_items', [])));

        return [
            'heroTitle' => (string)self::value('hero_title', ''),
            'heroText' => (string)self::value('hero_text', ''),
            'slides' => self::slides((string)self::value('slider_code', '')),
            'slideInterval' => max(2, (int)self::value('slider_interval', 6)) * 1000,
            'banners' => self::banners(array_values(array_filter((array)self::value('banner_codes', [])))),
            'featuredTitle' => (string)self::value('featured_title', Storefront::lang('site.featured_title')),
            'featured' => $location instanceof LocationModel && $featuredIds
                ? MenuCatalog::featured($location, $orderType, $featuredIds, max(1, (int)self::value('featured_limit', 6)))
                : [],
            'orderButton' => self::orderButton(),
        ];
    }

    /**
     * The default location's menu, when the homepage search is replaced by a button.
     */
    public static function orderButton(): ?string
    {
        if (!(bool)self::value('home_order_button', false)) {
            return null;
        }

        $default = LocationModel::getDefault();
        if ($default instanceof LocationModel && (string)$default->permalink_slug !== '') {
            return route('igniter.theme.order', ['location' => $default->permalink_slug], false);
        }

        return route('igniter.theme.order.current', absolute: false);
    }

    public static function singleLocation(): bool
    {
        return (bool)self::value('single_location', false);
    }

    protected static function slides(string $code): array
    {
        if ($code === '' || !class_exists(Slider::class)) {
            return [];
        }

        $slider = Slider::query()->where('code', $code)->first();
        if (!$slider instanceof Slider) {
            return [];
        }

        return $slider->images->map(fn(Media $media): array => [
            'url' => $media->getThumb(['width' => 1200, 'height' => 900]),
            'title' => (string)$media->getCustomProperty('title', ''),
            'description' => (string)$media->getCustomProperty('description', ''),
        ])->values()->all();
    }

    protected static function banners(array $codes): array
    {
        if ($codes === [] || !class_exists(Banner::class)) {
            return [];
        }

        $banners = Banner::query()->whereIsEnabled()->whereIn('code', $codes)->get()->keyBy('code');
        $mapped = [];

        foreach ($codes as $code) {
            $banner = $banners->get($code);
            if (!$banner instanceof Banner) {
                continue;
            }

            $mapped[] = [
                'code' => (string)$banner->code,
                'type' => $banner->type === 'custom' ? 'custom' : 'image',
                'url' => $banner->click_url
                    ? (str_starts_with((string)$banner->click_url, 'http') ? (string)$banner->click_url : '/'.ltrim((string)$banner->click_url, '/'))
                    : null,
                'alt' => (string)$banner->alt_text,
                'images' => array_values(array_map(
                    fn(string $path): string => ImageHelper::resize($path, ['width' => 1200, 'height' => 600]),
                    array_filter((array)$banner->image_code),
                )),
                'markup' => $banner->type === 'custom' ? Purify::clean((string)$banner->custom_code) : null,
            ];
        }

        return $mapped;
    }

    protected static function relativeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $root = rtrim(url('/'), '/');

        return str_starts_with($url, $root) ? (substr($url, strlen($root)) ?: '/') : $url;
    }
}
