<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

class Image extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'image',
            'name' => $this->lang('block_image'),
            'description' => $this->lang('block_image_desc'),
            'icon' => 'fa fa-image',
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'image' => [
                'label' => $this->lang('label_image'),
                'type' => 'mediafinder',
                'validationRule' => ['required', 'string'],
            ],
            'alt' => [
                'label' => $this->lang('label_alt_text'),
                'type' => 'text',
                'span' => 'left',
                'validationRule' => ['nullable', 'string', 'max:255'],
            ],
            'link' => [
                'label' => $this->lang('label_link'),
                'type' => 'text',
                'span' => 'right',
                'validationRule' => ['nullable', 'string', 'max:255', 'regex:/^(\/|#|https?:\/\/)/i'],
            ],
        ];
    }

    #[Override]
    public function resolve(array $settings): array
    {
        return [
            'url' => $this->imageUrl($settings['image'] ?? null),
            'alt' => (string)($settings['alt'] ?? ''),
            'link' => $settings['link'] ?? null,
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return (string)(($settings['alt'] ?? '') ?: ($settings['image'] ?? ''));
    }
}
