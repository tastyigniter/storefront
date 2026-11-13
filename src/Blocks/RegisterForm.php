<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The form to create a customer account.
 */
class RegisterForm extends PageBlock
{
    protected string $code = 'register-form';

    protected string $icon = 'fa fa-user-plus';

    protected array $pages = ['account.register'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title', 'left'),
            'hint' => $this->wording('label_hint', 'right'),
            'show_newsletter' => $this->switch('label_show_newsletter', 'left'),
            'show_login_link' => $this->switch('label_show_login_link', 'right'),
        ];
    }
}
