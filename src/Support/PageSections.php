<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Main\Classes\PageBuilder;
use Igniter\Main\Classes\Theme;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Pages\Models\Page as StaticPage;
use Illuminate\Support\Str;

/**
 * Page builder sections for storefront pages, resolved to JSON-safe data the
 * theme Vue pages render with their block components.
 *
 * A storefront page code is its route name (without the "igniter.theme."
 * prefix), e.g. "home", "checkout.checkout" or "account.orders". Themes list
 * the pages they make buildable in their meta/builder.php.
 */
class PageSections
{
    /**
     * Sections for the page served by the given route, or an empty list when
     * the active theme does not make that page buildable.
     */
    public static function forRoute(?string $routeName): array
    {
        if (!$routeName || !(($theme = self::theme()) instanceof Theme)) {
            return [];
        }

        $pageCode = Str::after($routeName, 'igniter.theme.');
        if (!$theme->getBuildablePage($pageCode)) {
            return [];
        }

        $builder = resolve(PageBuilder::class);

        return self::withMediaUrls($builder->resolve($theme, $builder->forPage($theme, $pageCode), $pageCode));
    }

    /**
     * Settings of the first block with the given code on a page of the active
     * theme, or an empty list when the page does not have that block. Read
     * outside the page's own request too, e.g. when the checkout is submitted.
     */
    public static function blockSettings(string $pageCode, string $blockCode): array
    {
        return once(function() use ($pageCode, $blockCode): array {
            $theme = self::theme();
            if (!$theme instanceof Theme || !$theme->getBuildablePage($pageCode)) {
                return [];
            }

            foreach (resolve(PageBuilder::class)->forPage($theme, $pageCode) as $section) {
                foreach ($section['columns'] as $column) {
                    foreach ($column['blocks'] as $block) {
                        if ($block['code'] === $blockCode) {
                            return $block['settings'];
                        }
                    }
                }
            }

            return [];
        });
    }

    /**
     * Sections of a static page edited with the page builder.
     */
    public static function forStaticPage(StaticPage $page): array
    {
        if (!$page->usesPageBuilder() || !(($theme = self::theme()) instanceof Theme)) {
            return [];
        }

        $builder = resolve(PageBuilder::class);
        $pageCode = StaticPage::BUILDER_PAGE_CODE;

        return self::withMediaUrls($builder->resolve($theme, $builder->normalize($theme, $page->sections ?? [], $pageCode), $pageCode));
    }

    /**
     * Section background images are saved as media library paths.
     */
    protected static function withMediaUrls(array $sections): array
    {
        return array_map(function(array $section): array {
            $image = $section['settings']['background_image'] ?? null;
            $section['settings']['background_image'] = $image ? media_url($image) : null;

            return $section;
        }, $sections);
    }

    protected static function theme(): ?Theme
    {
        return resolve(ThemeManager::class)->getActiveTheme();
    }
}
