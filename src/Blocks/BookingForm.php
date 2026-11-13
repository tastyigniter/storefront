<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The table booking steps: party size and date, time, then contact details. Its
 * phone setting is also applied by Igniter\Storefront\Http\Requests\PlaceReservationRequest.
 */
class BookingForm extends PageBlock
{
    protected string $code = 'booking-form';

    protected string $icon = 'fa fa-calendar-check';

    protected array $pages = ['booking'];

    protected bool $wide = true;

    #[Override]
    public function defineFields(): array
    {
        return [
            'telephone_required' => $this->switch('label_telephone_required', 'left'),
            'show_comment' => $this->switch('label_show_booking_comment', 'right'),
            'show_location_picker' => array_merge($this->switch('label_show_location_picker', 'left'), ['comment' => $this->lang('help_location_picker')]),
            'show_step_labels' => $this->switch('label_show_step_labels', 'right'),
        ];
    }
}
