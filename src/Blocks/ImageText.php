<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

class ImageText extends ContentBlock
{
    protected array $htmlFields = ['content'];

    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'image-text',
            'name' => $this->lang('block_image_text'),
            'description' => $this->lang('block_image_text_desc'),
            'icon' => 'fa fa-panorama',
            'wide' => true,
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return array_merge([
            'image' => [
                'label' => $this->lang('label_image'),
                'type' => 'mediafinder',
                'span' => 'left',
                'validationRule' => ['required', 'string'],
            ],
            'image_position' => [
                'label' => $this->lang('label_image_position'),
                'type' => 'radiotoggle',
                'default' => 'start',
                'span' => 'right',
                'options' => [
                    'start' => $this->lang('text_left'),
                    'end' => $this->lang('text_right'),
                ],
                'validationRule' => ['in:start,end'],
            ],
            'title' => [
                'label' => $this->lang('label_title'),
                'type' => 'text',
                'validationRule' => ['nullable', 'string', 'max:255'],
            ],
            'content' => [
                'label' => $this->lang('label_content'),
                'type' => 'richeditor',
            ],
        ], $this->buttonFields());
    }

    #[Override]
    public function resolve(array $settings): array
    {
        return array_merge($settings, ['url' => $this->imageUrl($settings['image'] ?? null)]);
    }
}
