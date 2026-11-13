<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The customer's name and phone, and the change password fields.
 */
class AccountDashboard extends PageBlock
{
    protected string $code = 'account-dashboard';

    protected string $icon = 'fa fa-user-pen';

    protected array $pages = ['account.account'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_password' => $this->switch('label_show_change_password'),
        ];
    }
}
