<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Local\Classes\FeaturedLocations as FeaturedLocationIds;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Support\StorefrontState;
use Override;

/**
 * Featured locations, shuffled on each page load so each gets a turn.
 */
class FeaturedLocations extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'featured-locations',
            'name' => $this->lang('block_featured_locations'),
            'description' => $this->lang('block_featured_locations_desc'),
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
                'default' => 'Featured restaurants',
                'validationRule' => ['required', 'string', 'max:80'],
            ],
            'limit' => [
                'label' => $this->lang('label_featured_locations_limit'),
                'type' => 'number',
                'span' => 'right',
                'default' => 6,
                'validationRule' => ['required', 'integer', 'min:1', 'max:24'],
            ],
        ];
    }

    #[Override]
    public function resolve(array $settings): array
    {
        return [
            'title' => (string)($settings['title'] ?? ''),
            'locations' => $this->cards((int)($settings['limit'] ?? 6)),
        ];
    }

    /**
     * Up to $limit enabled featured locations as cards, shuffled so each gets a turn.
     *
     * @return list<array<string, mixed>>
     */
    protected function cards(int $limit): array
    {
        $ids = resolve(FeaturedLocationIds::class)->ids();
        if ($ids === []) {
            return [];
        }

        return LocationModel::query()
            ->whereIsEnabled()
            ->whereKey($ids)
            ->get()
            ->shuffle()
            ->take(max(1, $limit))
            ->map(fn(LocationModel $location): array => [
                ...StorefrontState::location($location, true),
                'image' => $location->getThumb(['width' => 480, 'height' => 320], 'thumb'),
                'featured' => true,
            ])
            ->values()
            ->all();
    }
}
