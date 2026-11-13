<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The account page title with the customer's initials and greeting.
 */
class AccountHeader extends PageBlock
{
    protected string $code = 'account-header';

    protected string $icon = 'fa fa-id-badge';

    protected array $pages = self::ACCOUNT_PAGES;

    protected bool $wide = true;

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_avatar' => $this->switch('label_show_avatar', 'left'),
            'show_greeting' => $this->switch('label_show_greeting', 'right'),
            'show_actions' => array_merge($this->switch('label_show_header_actions', 'left'), ['comment' => $this->lang('help_header_actions')]),
        ];
    }
}
