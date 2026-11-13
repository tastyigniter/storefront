<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Frontend\Models\Banner;
use Igniter\Storefront\Support\ThemeContent;
use Override;

class Banners extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'banners',
            'name' => $this->lang('block_banners'),
            'description' => $this->lang('block_banners_desc'),
            'icon' => 'fa fa-rectangle-ad',
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'banner_codes' => [
                'label' => $this->lang('label_banners'),
                'type' => 'selectlist',
                'mode' => 'checkbox',
                'options' => fn(): array => class_exists(Banner::class) ? Banner::query()->whereIsEnabled()->pluck('name', 'code')->all() : [],
                'comment' => $this->lang('help_banners'),
                'validationRule' => ['nullable', 'array'],
            ],
        ];
    }

    #[Override]
    public function resolve(array $settings): array
    {
        return [
            'banners' => ThemeContent::banners(array_values(array_filter((array)($settings['banner_codes'] ?? [])))),
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return implode(', ', array_filter((array)($settings['banner_codes'] ?? [])));
    }
}
