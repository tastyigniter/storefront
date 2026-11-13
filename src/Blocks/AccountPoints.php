<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The customer's loyalty points balance.
 */
class AccountPoints extends PageBlock
{
    protected string $code = 'account-points';

    protected string $icon = 'fa fa-coins';

    protected array $pages = ['account.points'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_earned' => $this->switch('label_show_points_earned', 'left'),
            'show_redeemed' => $this->switch('label_show_points_redeemed', 'right'),
            'show_pending' => $this->switch('label_show_points_pending', 'left'),
        ];
    }
}
