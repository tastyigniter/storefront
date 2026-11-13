<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Cart\Models\Menu;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Support\MenuCatalog;
use Override;

class FeaturedItems extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'featured-items',
            'name' => $this->lang('block_featured_items'),
            'description' => $this->lang('block_featured_items_desc'),
            'icon' => 'fa fa-star',
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => [
                'label' => $this->lang('label_title'),
                'type' => 'text',
                'span' => 'left',
                'default' => 'Featured dishes',
                'validationRule' => ['required', 'string', 'max:80'],
            ],
            'limit' => [
                'label' => $this->lang('label_featured_limit'),
                'type' => 'number',
                'span' => 'right',
                'default' => 6,
                'validationRule' => ['required', 'integer', 'min:1', 'max:24'],
            ],
            'items' => [
                'label' => $this->lang('label_featured_items'),
                'type' => 'selectlist',
                'mode' => 'checkbox',
                'options' => fn(): array => Menu::query()->whereIsEnabled()->orderBy('menu_name')->pluck('menu_name', 'menu_id')->all(),
                'comment' => $this->lang('help_featured_items'),
                'validationRule' => ['nullable', 'array'],
            ],
        ];
    }

    #[Override]
    public function resolve(array $settings): array
    {
        $ids = array_map(intval(...), array_filter((array)($settings['items'] ?? [])));
        $location = Location::currentOrDefault();

        return [
            'title' => (string)($settings['title'] ?? ''),
            'items' => $location instanceof LocationModel && $ids
                ? MenuCatalog::featured($location, (string)Location::orderType(), $ids, max(1, (int)($settings['limit'] ?? 6)))
                : [],
        ];
    }
}
