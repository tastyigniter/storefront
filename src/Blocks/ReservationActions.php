<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * Buttons to order for the table or online, and to cancel the booking.
 */
class ReservationActions extends PageBlock
{
    protected string $code = 'reservation-actions';

    protected string $icon = 'fa fa-hand-pointer';

    protected array $pages = ['booking.success', 'account.reservation'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_order_buttons' => $this->switch('label_show_order_buttons', 'left'),
            'show_cancel' => array_merge($this->switch('label_show_cancel', 'right'), ['comment' => $this->lang('help_show_cancel')]),
        ];
    }
}
