<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The order's type, time and address, and the items ordered.
 */
class OrderDetails extends PageBlock
{
    protected string $code = 'order-details';

    protected string $icon = 'fa fa-file-invoice';

    protected array $pages = ['checkout.success', 'account.order'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_facts' => $this->switch('label_show_order_facts', 'left'),
            'show_items' => $this->switch('label_show_order_items', 'right'),
        ];
    }
}
