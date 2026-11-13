<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The locations, as cards with their photo, address, hours and order button.
 */
class LocationList extends PageBlock
{
    protected string $code = 'location-list';

    protected string $icon = 'fa fa-location-dot';

    protected array $pages = ['locations'];

    protected bool $wide = true;

    #[Override]
    public function defineFields(): array
    {
        return [
            'columns' => $this->choice('label_columns', ['2' => 'text_two', '3' => 'text_three', '4' => 'text_four'], '3', 'left'),
            'show_count' => $this->switch('label_show_count', 'right'),
            'show_images' => $this->switch('label_show_images', 'left'),
            'show_hours' => $this->switch('label_show_hours', 'right'),
            'show_order_types' => $this->switch('label_show_offers', 'left'),
        ];
    }
}
