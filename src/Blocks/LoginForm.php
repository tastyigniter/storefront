<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The sign in form, with social sign in when it is set up.
 */
class LoginForm extends PageBlock
{
    protected string $code = 'login-form';

    protected string $icon = 'fa fa-right-to-bracket';

    protected array $pages = ['account.login'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title', 'left'),
            'hint' => $this->wording('label_hint', 'right'),
            'show_social' => array_merge($this->switch('label_show_social', 'left'), ['comment' => $this->lang('help_show_social')]),
            'show_remember' => $this->switch('label_show_remember', 'right'),
            'show_register_link' => $this->switch('label_show_register_link', 'left'),
        ];
    }
}
