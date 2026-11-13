<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The table being booked and the location's hours, policy and phone.
 */
class BookingSummary extends PageBlock
{
    protected string $code = 'booking-summary';

    protected string $icon = 'fa fa-clipboard-list';

    protected array $pages = ['booking'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_table' => $this->switch('label_show_booking_table', 'left'),
            'show_info' => $this->switch('label_show_booking_info', 'right'),
            'sticky' => array_merge($this->switch('label_sticky_summary', 'left'), ['comment' => $this->lang('help_sticky_booking')]),
        ];
    }
}
