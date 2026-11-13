<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The location header on the menu page: cover photo, name, hours, address,
 * rating and order types. The section it is in shows the cover photo behind
 * it, so the order fulfilment block can sit beside it on the photo.
 */
class LocalHeader extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'local-header',
            'name' => $this->lang('block_local_header'),
            'description' => $this->lang('block_local_header_desc'),
            'icon' => 'fa fa-store',
            'unique' => true,
            'wide' => true,
            'pages' => ['order'],
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_cover' => $this->switch('label_show_cover', 'left'),
            'show_rating' => $this->switch('label_show_rating', 'right'),
            'show_order_types' => $this->switch('label_show_order_types', 'left'),
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return '';
    }
}
