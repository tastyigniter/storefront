<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Pages\Models\Page;
use Override;

/**
 * The checkout steps: order details, customer details, notes and payment.
 * Its field settings are applied by Igniter\Storefront\Support\CheckoutFields
 * when the checkout form is shown and when the order is placed.
 */
class CheckoutForm extends ContentBlock
{
    #[Override]
    public function blockDetails(): array
    {
        return [
            'code' => 'checkout-form',
            'name' => $this->lang('block_checkout_form'),
            'description' => $this->lang('block_checkout_form_desc'),
            'icon' => 'fa fa-cash-register',
            'unique' => true,
            'wide' => true,
            'pages' => ['checkout.checkout'],
        ];
    }

    #[Override]
    public function defineFields(): array
    {
        return [
            'telephone_required' => $this->switch('label_telephone_required', 'left'),
            'show_comment' => $this->switch('label_show_comment', 'right'),
            'show_delivery_comment' => $this->switch('label_show_delivery_comment', 'left'),
            'terms_page' => [
                'label' => $this->lang('label_terms_page'),
                'type' => 'select',
                'span' => 'right',
                'default' => 'terms-and-conditions',
                'placeholder' => $this->lang('text_no_terms'),
                'options' => fn(): array => class_exists(Page::class) ? Page::query()->pluck('title', 'permalink_slug')->all() : [],
                'comment' => $this->lang('help_terms_page'),
                'validationRule' => ['nullable', 'string', 'max:255'],
            ],
            'show_step_numbers' => $this->switch('label_show_step_numbers', 'left'),
        ];
    }

    #[Override]
    public function summary(array $settings): string
    {
        return '';
    }
}
