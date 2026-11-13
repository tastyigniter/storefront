<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The location's address, phone, email and opening hours.
 */
class ContactDetails extends PageBlock
{
    protected string $code = 'contact-details';

    protected string $icon = 'fa fa-store';

    protected array $pages = ['contact'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_address' => $this->switch('label_show_address', 'left'),
            'show_telephone' => $this->switch('label_show_telephone', 'right'),
            'show_email' => $this->switch('label_show_email', 'left'),
            'show_hours' => $this->switch('label_show_hours_today', 'right'),
            'show_week' => $this->switch('label_show_week', 'left'),
        ];
    }
}
