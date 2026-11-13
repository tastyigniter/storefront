<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The tick and thank you message shown once an order is placed.
 */
class OrderConfirmation extends PageBlock
{
    protected string $code = 'order-confirmation';

    protected string $icon = 'fa fa-circle-check';

    protected array $pages = ['checkout.success'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title'),
            'text' => $this->wording('label_text', 'full', 'textarea'),
            'show_order_number' => $this->switch('label_show_order_number'),
        ];
    }
}
