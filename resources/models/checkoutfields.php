<?php

declare(strict_types=1);

use Igniter\Storefront\Storefront;
use Igniter\User\Facades\Auth;
use Illuminate\Validation\Rule;

// Checkout fields, built with Igniter\Cart\Classes\CheckoutForm so extensions can
// add or change fields through checkout.form.extendFieldsBefore / extendFields.
// Tabs: details (Your details), comments (Notes), terms (above Place order).
return [
    'fields' => [
        'first_name' => [
            'label' => 'lang:igniter.cart::default.checkout.label_first_name',
            'tab' => 'details',
            'type' => 'text',
            'attributes' => ['autocomplete' => 'given-name'],
        ],
        'last_name' => [
            'label' => 'lang:igniter.cart::default.checkout.label_last_name',
            'tab' => 'details',
            'type' => 'text',
            'attributes' => ['autocomplete' => 'family-name'],
        ],
        'email' => [
            'label' => 'lang:igniter.cart::default.checkout.label_email',
            'tab' => 'details',
            'type' => 'email',
            'attributes' => ['autocomplete' => 'email'],
        ],
        'telephone' => [
            'label' => 'lang:igniter.cart::default.checkout.label_telephone',
            'tab' => 'details',
            'type' => 'telephone',
            'attributes' => ['autocomplete' => 'tel'],
        ],
        'comment' => [
            'label' => 'lang:igniter.cart::default.checkout.label_comment',
            'tab' => 'comments',
            'type' => 'textarea',
            'placeholder' => Storefront::lang('checkout.comment_placeholder'),
        ],
        'delivery_comment' => [
            'label' => 'lang:igniter.cart::default.checkout.label_delivery_comment',
            'tab' => 'comments',
            'type' => 'textarea',
            'placeholder' => Storefront::lang('checkout.delivery_comment_placeholder'),
        ],
        'termsAgreed' => [
            'label' => 'lang:igniter.cart::default.checkout.text_checkout_terms',
            'tab' => 'terms',
            'type' => 'checkbox',
            'options' => [],
        ],
    ],
    'rules' => [
        'first_name' => ['required', 'between:1,48'],
        'last_name' => ['required', 'between:1,48'],
        'email' => ['required', 'email:filter', 'max:96',
            Rule::unique('customers', 'email')->ignore(Auth::customer()?->getKey(), 'customer_id'),
        ],
        'telephone' => ['required', 'regex:/^([0-9\s\-\+\(\)]*)$/i', 'max:20'],
        'comment' => ['nullable', 'max:500'],
        'delivery_comment' => ['nullable', 'max:500'],
        'termsAgreed' => ['accepted'],
    ],
    'messages' => [
        'telephone.regex' => Storefront::lang('alert.phone_invalid'),
        'termsAgreed.accepted' => Storefront::lang('alert.terms_required'),
        'email.unique' => Storefront::lang('alert.email_taken'),
    ],
];
