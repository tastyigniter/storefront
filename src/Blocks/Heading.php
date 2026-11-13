<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

class Heading extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'heading',
            'name' => $this->lang('block_heading'),
            'description' => $this->lang('block_heading_desc'),
            'icon' => 'fa fa-heading',
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => [
                'label' => $this->lang('label_title'),
                'type' => 'text',
                'validationRule' => ['required', 'string', 'max:255'],
            ],
            'subtitle' => [
                'label' => $this->lang('label_subtitle'),
                'type' => 'textarea',
                'validationRule' => ['nullable', 'string', 'max:1000'],
            ],
            'level' => [
                'label' => $this->lang('label_heading_level'),
                'type' => 'radiotoggle',
                'default' => 'h2',
                'span' => 'left',
                'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'],
                'validationRule' => ['in:h1,h2,h3'],
            ],
            'alignment' => array_merge($this->alignmentField(), ['span' => 'right']),
        ];
    }
}
