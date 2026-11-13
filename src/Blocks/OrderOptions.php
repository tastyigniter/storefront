<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The order fulfilment card on the menu page: delivery or pick-up and the time,
 * with the dine-in table buttons and the group order button. Its code stays
 * "order-options" so saved layouts keep it.
 */
class OrderOptions extends PageBlock
{
    protected string $code = 'order-options';

    protected string $icon = 'fa fa-truck-fast';

    protected array $pages = ['order'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'preview_mode' => array_merge($this->switchOff('label_preview_mode'), [
                'comment' => $this->lang('help_preview_mode'),
            ]),
            'show_table_actions' => array_merge($this->switch('label_show_table_actions', 'left'), [
                'comment' => $this->lang('help_table_actions'),
            ]),
            'show_group_order' => $this->switch('label_show_group_order', 'right'),
        ];
    }
}
