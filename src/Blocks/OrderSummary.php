<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The checkout order summary: basket lines, totals, terms and the place order button.
 */
class OrderSummary extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'order-summary',
            'name' => $this->lang('block_order_summary'),
            'description' => $this->lang('block_order_summary_desc'),
            'icon' => 'fa fa-receipt',
            'unique' => true,
            'pages' => ['checkout.checkout'],
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_items' => $this->switch('label_show_items', 'left'),
            'show_item_options' => $this->switch('label_show_item_options', 'right'),
            'sticky' => array_merge($this->switch('label_sticky_summary', 'left'), [
                'comment' => $this->lang('help_sticky_summary'),
            ]),
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return '';
    }
}
