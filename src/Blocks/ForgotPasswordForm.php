<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The form to request a password reset link.
 */
class ForgotPasswordForm extends PageBlock
{
    protected string $code = 'forgot-password-form';

    protected string $icon = 'fa fa-key';

    protected array $pages = ['account.forgot'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title', 'left'),
            'hint' => $this->wording('label_hint', 'right'),
        ];
    }
}
