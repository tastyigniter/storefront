<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Frontend\Models\Slider;
use Igniter\Storefront\Http\Requests\SearchFulfillmentRequest;
use Igniter\Storefront\Support\ThemeContent;
use Override;

/**
 * The homepage hero: heading, text, the delivery address search (or an order
 * button for single location sites) and a slideshow.
 */
class Hero extends ContentBlock
{
    /** Pages the local search can open once the customer's location is found. */
    public const array REDIRECT_PAGES = SearchFulfillmentRequest::REDIRECT_PAGES;

    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'hero',
            'name' => $this->lang('block_hero'),
            'description' => $this->lang('block_hero_desc'),
            'icon' => 'fa fa-image-portrait',
            'unique' => true,
            'wide' => true,
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'title' => [
                'label' => $this->lang('label_title'),
                'type' => 'text',
                'default' => 'Artisanal Flavors, Delivered to Your Door',
                'validationRule' => ['required', 'string', 'max:120'],
            ],
            'text' => [
                'label' => $this->lang('label_text'),
                'type' => 'textarea',
                'validationRule' => ['nullable', 'string', 'max:400'],
            ],
            'slider_code' => [
                'label' => $this->lang('label_slider'),
                'type' => 'select',
                'span' => 'left',
                'placeholder' => $this->lang('text_no_slider'),
                'options' => fn(): array => class_exists(Slider::class) ? Slider::query()->pluck('name', 'code')->all() : [],
                'comment' => $this->lang('help_slider'),
                'validationRule' => ['nullable', 'string'],
            ],
            'slider_interval' => [
                'label' => $this->lang('label_slider_interval'),
                'type' => 'number',
                'span' => 'right',
                'default' => 6,
                'validationRule' => ['required', 'integer', 'min:2', 'max:30'],
            ],
            'hide_search' => [
                'label' => $this->lang('label_hide_search'),
                'type' => 'switch',
                'default' => false,
                'comment' => $this->lang('help_hide_search'),
            ],
            'redirect_page' => [
                'label' => $this->lang('label_redirect_page'),
                'type' => 'select',
                'default' => 'order',
                'options' => fn(): array => array_map(
                    lang(...),
                    array_combine(self::REDIRECT_PAGES, array_map(StorefrontBlocks::pageLabel(...), self::REDIRECT_PAGES)),
                ),
                'comment' => $this->lang('help_redirect_page'),
                'validationRule' => ['required', 'in:'.implode(',', self::REDIRECT_PAGES)],
            ],
            'address_autocomplete' => [
                'label' => $this->lang('label_address_autocomplete'),
                'type' => 'switch',
                'span' => 'left',
                'default' => true,
                'comment' => $this->lang('help_address_autocomplete'),
            ],
            'show_locate' => [
                'label' => $this->lang('label_show_locate'),
                'type' => 'switch',
                'span' => 'right',
                'default' => true,
            ],
        ];
    }

    #[Override]
    public function resolve(array $settings): array
    {
        return [
            'title' => (string)($settings['title'] ?? ''),
            'text' => (string)($settings['text'] ?? ''),
            'slides' => ThemeContent::slides((string)($settings['slider_code'] ?? '')),
            'slideInterval' => max(2, (int)($settings['slider_interval'] ?? 6)) * 1000,
            // The view menu button replaces the search when the block or the theme hides it.
            'orderButton' => ThemeContent::orderButton((bool)($settings['hide_search'] ?? false)),
            'redirectPage' => in_array($settings['redirect_page'] ?? null, self::REDIRECT_PAGES, true) ? $settings['redirect_page'] : 'order',
            'addressAutocomplete' => (bool)($settings['address_autocomplete'] ?? true),
            'showLocate' => (bool)($settings['show_locate'] ?? true),
        ];
    }
}
