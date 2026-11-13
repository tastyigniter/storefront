<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The form to choose a new password.
 */
class ResetPasswordForm extends PageBlock
{
    protected string $code = 'reset-password-form';

    protected string $icon = 'fa fa-key';

    protected array $pages = ['account.reset'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title', 'left'),
            'hint' => $this->wording('label_hint', 'right'),
        ];
    }
}
