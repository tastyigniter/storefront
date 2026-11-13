<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The contact form. Its phone setting is also applied by
 * Igniter\Storefront\Http\Requests\ContactRequest when the message is sent.
 */
class ContactForm extends PageBlock
{
    protected string $code = 'contact-form';

    protected string $icon = 'fa fa-envelope';

    protected array $pages = ['contact'];

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => $this->wording('label_title'),
            'telephone_required' => $this->switch('label_telephone_required', 'left'),
            'show_subject' => array_merge($this->switch('label_show_subject', 'right'), ['comment' => $this->lang('help_show_subject')]),
        ];
    }
}
