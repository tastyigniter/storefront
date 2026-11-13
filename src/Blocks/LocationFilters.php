<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The location search, order type filter, sorting and cuisine filter.
 */
class LocationFilters extends PageBlock
{
    protected string $code = 'location-filters';

    protected string $icon = 'fa fa-filter';

    protected array $pages = ['locations'];

    protected bool $wide = true;

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_search' => $this->switch('label_show_location_search', 'left'),
            'show_order_types' => $this->switch('label_show_type_filter', 'right'),
            'show_sort' => $this->switch('label_show_sort', 'left'),
            'show_cuisines' => $this->switch('label_show_cuisines', 'right'),
        ];
    }
}
