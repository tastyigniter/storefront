<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Main\Classes\BaseBlock;
use Override;

/**
 * Places a storefront page's own view (the checkout form, the booking form,
 * the account orders list...) among the page builder sections. The theme
 * renders it with the page component Inertia resolved for the route.
 */
class PageView extends BaseBlock
{
    final public function __construct(
        protected string $code,
        protected string $pageCode,
        protected string $name,
        protected string $icon = 'fa fa-window-maximize',
    ) {}

    public static function make(string $code, string $pageCode, string $name, string $icon = 'fa fa-window-maximize'): static
    {
        return new static($code, $pageCode, $name, $icon);
    }

    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => 'igniterlabs.storefront::default.builder.block_page_view_desc',
            'icon' => $this->icon,
            'unique' => true,
            'wide' => true,
            'pages' => [$this->pageCode],
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return '';
    }
}
