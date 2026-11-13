<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

/**
 * Lets the customer delete their account after entering their password.
 */
class AccountDelete extends PageBlock
{
    protected string $code = 'account-delete';

    protected string $icon = 'fa fa-user-xmark';

    protected array $pages = ['account.account'];
}
