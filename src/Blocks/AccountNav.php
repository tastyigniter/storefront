<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * Links to the account pages and the sign out button.
 */
class AccountNav extends PageBlock
{
    protected string $code = 'account-nav';

    protected string $icon = 'fa fa-list';

    protected array $pages = self::ACCOUNT_PAGES;

    #[Override]
    public function defineFields(): array
    {
        return [
            'sticky' => $this->switch('label_sticky', 'left'),
            'show_sign_out' => $this->switch('label_show_sign_out', 'right'),
        ];
    }
}
