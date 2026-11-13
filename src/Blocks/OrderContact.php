<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The customer's contact details and the restaurant the order came from.
 */
class OrderContact extends PageBlock
{
    protected string $code = 'order-contact';

    protected string $icon = 'fa fa-address-card';

    protected array $pages = ['account.order'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_contact' => $this->switch('label_show_order_contact', 'left'),
            'show_restaurant' => $this->switch('label_show_restaurant', 'right'),
        ];
    }
}
