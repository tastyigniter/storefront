<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

class CallToAction extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'call-to-action',
            'name' => $this->lang('block_call_to_action'),
            'description' => $this->lang('block_call_to_action_desc'),
            'icon' => 'fa fa-bullhorn',
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return array_merge([
            'title' => [
                'label' => $this->lang('label_title'),
                'type' => 'text',
                'validationRule' => ['required', 'string', 'max:255'],
            ],
            'text' => [
                'label' => $this->lang('label_text'),
                'type' => 'textarea',
                'validationRule' => ['nullable', 'string', 'max:1000'],
            ],
        ], $this->buttonFields(), [
            'alignment' => $this->alignmentField(),
        ]);
    }
}
