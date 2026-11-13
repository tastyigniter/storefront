<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The customer's saved addresses.
 */
class AccountAddress extends PageBlock
{
    protected string $code = 'account-address';

    protected string $icon = 'fa fa-address-book';

    protected array $pages = ['account.address'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'columns' => $this->choice('label_columns', ['1' => 'text_one', '2' => 'text_two'], '2'),
        ];
    }
}
