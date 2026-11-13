<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The tick and thank you message shown once a table is booked.
 */
class BookingConfirmation extends PageBlock
{
    protected string $code = 'booking-confirmation';

    protected string $icon = 'fa fa-calendar-day';

    protected array $pages = ['booking.success'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title'),
            'text' => $this->wording('label_text', 'full', 'textarea'),
        ];
    }
}
