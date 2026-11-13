<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

/**
 * Lets the customer rate their order or visit, when reviews are turned on.
 */
class ReviewForm extends PageBlock
{
    protected string $code = 'review-form';

    protected string $icon = 'fa fa-star';

    protected array $pages = ['booking.success', 'account.order'];
}
