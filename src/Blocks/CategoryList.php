<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * The menu's category tabs, with the dish search and allergen filter.
 */
class CategoryList extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'category-list',
            'name' => $this->lang('block_category_list'),
            'description' => $this->lang('block_category_list_desc'),
            'icon' => 'fa fa-list',
            'unique' => true,
            // Not limited to wide columns: some themes list the categories in a sidebar.
            'pages' => ['order'],
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'show_search' => $this->switch('label_show_search', 'left'),
            'show_allergen_filter' => $this->switch('label_show_allergen_filter', 'right'),
            'sticky' => array_merge($this->switch('label_sticky', 'left'), [
                'comment' => $this->lang('help_sticky'),
            ]),
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return '';
    }
}
