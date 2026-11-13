<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

class RichText extends ContentBlock
{
    protected array $htmlFields = ['content'];

    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'rich-text',
            'name' => $this->lang('block_rich_text'),
            'description' => $this->lang('block_rich_text_desc'),
            'icon' => 'fa fa-paragraph',
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'content' => [
                'label' => $this->lang('label_content'),
                'type' => 'richeditor',
                'size' => 'large',
            ],
            'card' => [
                'label' => $this->lang('label_card'),
                'type' => 'switch',
                'default' => true,
            ],
        ];
    }
}
