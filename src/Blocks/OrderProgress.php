<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

/**
 * The order's progress from received to ready, updated as it changes.
 */
class OrderProgress extends PageBlock
{
    protected string $code = 'order-progress';

    protected string $icon = 'fa fa-bars-progress';

    protected array $pages = ['checkout.success', 'account.order'];
}
