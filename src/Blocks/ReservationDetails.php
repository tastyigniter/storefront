<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The booked table: guests, date and time, location and note.
 */
class ReservationDetails extends PageBlock
{
    protected string $code = 'reservation-details';

    protected string $icon = 'fa fa-chair';

    protected array $pages = ['booking.success', 'account.reservation'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_location' => $this->switch('label_show_location', 'left'),
            'show_note' => $this->switch('label_show_note', 'right'),
        ];
    }
}
