<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The checkout title, and the order type and location the order is from.
 */
class CheckoutHeader extends PageBlock
{
    protected string $code = 'checkout-header';

    protected string $icon = 'fa fa-heading';

    protected array $pages = ['checkout.checkout'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title'),
            'show_location' => $this->switch('label_show_order_from'),
        ];
    }
}
