<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * Buttons to reorder or cancel the order, and a link to the order history.
 */
class OrderActions extends PageBlock
{
    protected string $code = 'order-actions';

    protected string $icon = 'fa fa-rotate-right';

    protected array $pages = ['checkout.success', 'account.order'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_orders_link' => $this->switch('label_show_orders_link'),
        ];
    }
}
