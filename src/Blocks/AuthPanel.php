<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * A photo panel beside the sign in forms, with a welcome and the account perks.
 */
class AuthPanel extends PageBlock
{
    protected string $code = 'auth-panel';

    protected string $icon = 'fa fa-image';

    protected array $pages = self::AUTH_PAGES;

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title', 'left'),
            'accent' => $this->wording('label_accent', 'right'),
            'text' => $this->wording('label_text', 'full', 'textarea'),
            'show_perks' => $this->switch('label_show_perks'),
        ];
    }
}
