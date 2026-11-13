<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Fixture;

use Igniter\Main\Classes\BaseBlock;
use Override;

class HeroBlock extends BaseBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return ['code' => 'hero', 'name' => 'Hero'];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'type' => 'text', 'default' => 'Welcome'],
        ];
    }

    #[Override]
    public function resolve(array $settings): array
    {
        return ['heading' => strtoupper((string)$settings['title'])];
    }
}
