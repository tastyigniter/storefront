<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * Base class for the blocks that make up a storefront page (contact, booking,
 * account pages...). The block reads its data from the page props the page's
 * controller already sends; its settings only change what is shown.
 *
 * The name and description come from the language keys block_{code} and
 * block_{code}_desc, with dashes in the code turned into underscores.
 */
abstract class PageBlock extends ContentBlock
{
    public const array AUTH_PAGES = ['account.login', 'account.register', 'account.forgot', 'account.reset'];

    public const array ACCOUNT_PAGES = [
        'account.account',
        'account.address',
        'account.orders',
        'account.order',
        'account.reservations',
        'account.reservation',
        'account.points',
    ];

    protected string $code;

    protected string $icon = 'fa fa-cube';

    /** @var list<string> Page codes the block can be added to, empty for every page */
    protected array $pages = [];

    protected bool $unique = true;

    protected bool $wide = false;

    #[Override]
    public function blockDetails(): array
    {
        $key = 'block_'.str_replace('-', '_', $this->code);

        return [
            'code' => $this->code,
            'name' => $this->lang($key),
            'description' => $this->lang($key.'_desc'),
            'icon' => $this->icon,
            'unique' => $this->unique,
            'wide' => $this->wide,
            'pages' => $this->pages,
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return '';
    }

    /**
     * A switch field, off by default.
     */
    protected function switchOff(string $label, string $span = 'full'): array
    {
        return array_merge($this->switch($label, $span), ['default' => false]);
    }

    /**
     * Text that replaces the page's own wording. Left blank, the page keeps its
     * wording, translated into the customer's language.
     */
    protected function wording(string $label, string $span = 'full', string $type = 'text'): array
    {
        return [
            'label' => $this->lang($label),
            'type' => $type,
            'span' => $span,
            'comment' => $this->lang('help_wording'),
            'validationRule' => ['nullable', 'string', 'max:'.($type === 'textarea' ? 500 : 120)],
        ];
    }

    /**
     * @param array<int|string, string> $options Values and their language keys
     */
    protected function choice(string $label, array $options, string $default, string $span = 'full'): array
    {
        return [
            'label' => $this->lang($label),
            'type' => 'radiotoggle',
            'span' => $span,
            'default' => $default,
            'options' => array_map($this->lang(...), $options),
            'validationRule' => ['in:'.implode(',', array_keys($options))],
        ];
    }
}
