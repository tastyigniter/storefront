<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Cart\Models\Category;
use Override;

/**
 * The location's menu, grouped by category.
 */
class MenuItemList extends ContentBlock
{
    public const array IMAGE_MODES = ['theme', 'show', 'hide'];

    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'menu-item-list',
            'name' => $this->lang('block_menu_item_list'),
            'description' => $this->lang('block_menu_item_list_desc'),
            'icon' => 'fa fa-utensils',
            'unique' => true,
            'wide' => true,
            'pages' => ['order'],
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'categories' => [
                'label' => $this->lang('label_categories'),
                'type' => 'selectlist',
                'mode' => 'checkbox',
                'options' => fn(): array => Category::query()->whereIsEnabled()->orderBy('priority')->pluck('name', 'category_id')->all(),
                'comment' => $this->lang('help_categories'),
                'validationRule' => ['nullable', 'array'],
            ],
            'card_style' => [
                'label' => $this->lang('label_card_style'),
                'type' => 'radiotoggle',
                'span' => 'left',
                'default' => 'row',
                'options' => [
                    'row' => $this->lang('text_card_row'),
                    'tile' => $this->lang('text_card_tile'),
                ],
                'validationRule' => ['in:row,tile'],
            ],
            'columns' => [
                'label' => $this->lang('label_columns'),
                'type' => 'radiotoggle',
                'span' => 'right',
                'default' => '3',
                'options' => ['2' => '2', '3' => '3', '4' => '4'],
                'validationRule' => ['in:2,3,4'],
            ],
            'item_images' => $this->imageMode('label_item_images', 'left'),
            'category_covers' => $this->imageMode('label_category_covers', 'right'),
            'hide_unavailable' => array_merge($this->switch('label_hide_unavailable', 'left'), ['default' => false]),
            'show_group_order_bar' => $this->switch('label_show_group_order_bar', 'right'),
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        $count = count(array_filter((array)($settings['categories'] ?? [])));

        return $count ? sprintf(lang($this->lang('text_category_count')), $count) : lang($this->lang('text_all_categories'));
    }

    protected function imageMode(string $label, string $span): array
    {
        return [
            'label' => $this->lang($label),
            'type' => 'radiotoggle',
            'span' => $span,
            'default' => 'theme',
            'options' => [
                'theme' => $this->lang('text_theme_setting'),
                'show' => $this->lang('text_show'),
                'hide' => $this->lang('text_hide'),
            ],
            'validationRule' => ['in:theme,show,hide'],
        ];
    }
}
