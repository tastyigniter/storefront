<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The customer's table bookings.
 */
class AccountReservations extends PageBlock
{
    protected string $code = 'account-reservations';

    protected string $icon = 'fa fa-calendar';

    protected array $pages = ['account.reservations'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_status' => $this->switch('label_show_status', 'left'),
            'show_guests' => $this->switch('label_show_guests', 'right'),
        ];
    }
}
