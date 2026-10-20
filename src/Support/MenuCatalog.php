<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Models\Category;
use Igniter\Cart\Models\Ingredient;
use Igniter\Cart\Models\Mealtime;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOption;
use Igniter\Cart\Models\MenuOptionValue;
use Igniter\Local\Facades\Location as LocationFacade;
use Igniter\Local\Models\Location;
use Igniter\Storefront\Storefront;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MenuCatalog
{
    protected const array RELATIONS = [
        'media', 'categories.media', 'ingredients', 'menu_options.option', 'menu_options.menu_option_values.option_value.ingredients', 'stocks', 'mealtimes',
    ];

    /**
     * Dishes picked in the theme settings, in the order they were picked.
     */
    public static function featured(Location $location, string $orderType, array $menuIds, int $limit): array
    {
        $menus = Menu::query()
            ->with(self::RELATIONS)
            ->listFrontEnd([
                'location' => $location->getKey(),
                'enabled' => true,
                'orderType' => $orderType,
            ])
            ->whereIn('menus.menu_id', $menuIds)
            ->get()
            ->keyBy('menu_id');

        return collect($menuIds)
            ->map(fn(int $id) => $menus->get($id))
            ->filter()
            ->take($limit)
            ->map(fn(Menu $menu): array => self::item($menu, $location, (int)($menu->categories->first()?->getKey() ?? 0)))
            ->values()
            ->all();
    }

    public static function grouped(Location $location, string $orderType, string $search = ''): array
    {
        $query = Menu::query()
            ->with(self::RELATIONS)
            ->listFrontEnd([
                'location' => $location->getKey(),
                'enabled' => true,
                'orderType' => $orderType,
                'sort' => 'menu_priority asc',
            ]);

        $search = trim($search);
        if ($search !== '') {
            $needle = '%'.addcslashes(Str::lower($search), '%_\\').'%';
            $query->where(function($builder) use ($needle): void {
                $builder->whereRaw('LOWER(menus.menu_name) like ?', [$needle])
                    ->orWhereRaw('LOWER(menus.menu_description) like ?', [$needle]);
            });
        }

        $items = $query->get();

        $groups = [];

        foreach ($items as $menu) {
            $categories = $menu->categories->isEmpty()
                ? [[
                    'id' => 0,
                    'name' => Storefront::lang('site.menu_group'),
                    'slug' => 'menu',
                    'priority' => 999,
                    'image' => null,
                ]]
                : $menu->categories->map(fn(Category $category): array => [
                    'id' => (int)$category->getKey(),
                    'name' => $category->name,
                    'slug' => $category->permalink_slug ?: Str::slug($category->name),
                    'priority' => (int)$category->priority,
                    // The category's own image (Restaurant > Categories), for themes that show a category banner.
                    'image' => $category->getThumb(['width' => 1200, 'height' => 480]),
                ])->all();

            foreach ($categories as $category) {
                $id = $category['id'];
                $groups[$id] ??= [
                    'id' => $id,
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'priority' => $category['priority'],
                    'image' => $category['image'],
                    'items' => [],
                ];
                $groups[$id]['items'][] = self::item($menu, $location, $id);
            }
        }

        return collect($groups)
            ->sortBy('priority')
            ->values()
            ->all();
    }

    public static function find(Location $location, int $menuId): ?array
    {
        $menu = Menu::query()
            ->with(self::RELATIONS)
            ->whereIsEnabled()
            ->find($menuId);

        if (!$menu instanceof Menu) {
            return null;
        }

        return self::item($menu, $location, (int)($menu->categories->first()?->getKey() ?? 0));
    }

    protected static function item(Menu $menu, Location $location, int $categoryId): array
    {
        $description = trim(html_entity_decode(strip_tags((string)$menu->menu_description)));

        return [
            'id' => (int)$menu->getKey(),
            'name' => $menu->menu_name,
            'description' => $description,
            'ingredients' => $menu->ingredients->pluck('name')->filter()->values()->all(),
            'allergens' => self::allergenNames($menu->ingredients),
            'price' => (float)$menu->menu_price,
            'priceLabel' => Money::label($menu->menu_price),
            'minimumQty' => max(1, (int)$menu->minimum_qty),
            'image' => $menu->getThumb(['width' => 720, 'height' => 540]),
            'outOfStock' => (bool)$menu->outOfStock($location),
            // Mealtimes (e.g. Breakfast 07:00–10:00) limit when a dish can be ordered for the chosen time.
            'available' => $menu->isAvailable(LocationFacade::orderDateTime()),
            'mealtimes' => $menu->mealtimes
                ->filter(fn(Mealtime $mealtime): bool => $mealtime->isEnabled())
                ->map(fn(Mealtime $mealtime): array => [
                    'name' => (string)$mealtime->mealtime_name,
                    'description' => (string)$mealtime->description,
                ])
                ->values()
                ->all(),
            'categoryId' => $categoryId,
            'options' => self::options($menu->menu_options),
        ];
    }

    /**
     * Names of enabled ingredients flagged as allergens.
     *
     * @return list<string>
     */
    protected static function allergenNames(Collection $ingredients): array
    {
        return $ingredients
            ->filter(fn(Ingredient $ingredient): bool => $ingredient->is_allergen && $ingredient->isEnabled())
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function options(Collection $menuOptions): array
    {
        return $menuOptions->map(fn(MenuItemOption $option): array => [
            'id' => (int)$option->getKey(),
            'name' => (string)$option->option_name,
            'displayType' => (string)($option->display_type ?: 'radio'),
            'required' => (bool)$option->is_required,
            'minSelected' => (int)$option->min_selected,
            'maxSelected' => (int)$option->max_selected,
            'values' => $option->menu_option_values->map(fn($value): array => [
                'id' => (int)$value->getKey(),
                'name' => (string)$value->name,
                'price' => (float)$value->price,
                'priceLabel' => (float)$value->price > 0 ? Money::label($value->price) : '',
                'allergens' => self::allergenNames(
                    $value->option_value instanceof MenuOptionValue ? $value->option_value->ingredients : collect(),
                ),
            ])->values()->all(),
        ])->values()->all();
    }
}
