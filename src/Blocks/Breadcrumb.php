<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * Links back to the homepage, and the menu, ahead of the current page.
 */
class Breadcrumb extends PageBlock
{
    protected string $code = 'breadcrumb';

    protected string $icon = 'fa fa-ellipsis';

    #[Override]
    public function defineFields(): array
    {
        return [
            'current' => $this->wording('label_current_page'),
            'show_menu_link' => $this->switch('label_show_menu_link'),
        ];
    }
}
