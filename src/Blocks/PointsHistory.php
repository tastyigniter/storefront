<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * Each time the customer earned or used loyalty points.
 */
class PointsHistory extends PageBlock
{
    protected string $code = 'points-history';

    protected string $icon = 'fa fa-clock-rotate-left';

    protected array $pages = ['account.points'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_status' => $this->switch('label_show_status'),
        ];
    }
}
