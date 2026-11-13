<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * A card linking to the menu of the location the customer is ordering from.
 */
class OrderingFrom extends PageBlock
{
    protected string $code = 'ordering-from';

    protected string $icon = 'fa fa-store';

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_hours' => $this->switch('label_show_hours', 'left'),
            'show_order_types' => $this->switch('label_show_offers', 'right'),
            'overlap' => array_merge($this->switch('label_overlap_above'), ['comment' => $this->lang('help_overlap_above')]),
        ];
    }
}
