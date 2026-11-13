<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The customer's past orders.
 */
class AccountOrders extends PageBlock
{
    protected string $code = 'account-orders';

    protected string $icon = 'fa fa-bag-shopping';

    protected array $pages = ['account.orders'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_status' => $this->switch('label_show_status', 'left'),
            'show_total' => $this->switch('label_show_total', 'right'),
        ];
    }
}
