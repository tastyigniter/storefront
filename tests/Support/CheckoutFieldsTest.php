<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Classes\CheckoutForm;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Support\CheckoutFields;
use Illuminate\Support\Facades\Event;

function checkoutForm(): CheckoutForm
{
    return CheckoutFields::make(resolve(OrderManager::class)->loadOrder());
}

afterEach(function(): void {
    Event::forget('checkout.form.extendFieldsBefore');
});

beforeEach(function(): void {
    currentLocation();
});

it('builds the default checkout fields', function(): void {
    $fields = collect(CheckoutFields::present(checkoutForm()))->keyBy('name');

    expect($fields->keys()->all())->toBe(['first_name', 'last_name', 'email', 'telephone', 'comment', 'delivery_comment', 'termsAgreed'])
        ->and($fields['termsAgreed'])->toMatchArray(['required' => true, 'termsUrl' => '/terms-and-conditions', 'tab' => 'terms'])
        ->and($fields['comment']['placeholder'])->toBe('checkout.comment_placeholder')
        ->and($fields['email']['attributes'])->toBe(['autocomplete' => 'email']);
});

it('applies the theme checkout settings', function(): void {
    setThemeSettings([
        'checkout_terms_page' => '',
        'checkout_telephone_required' => false,
        'checkout_show_comment' => false,
        'checkout_show_delivery_comment' => false,
    ]);

    $form = checkoutForm();
    $names = array_column(CheckoutFields::present($form), 'name');

    expect($names)->toBe(['first_name', 'last_name', 'email', 'telephone'])
        ->and(CheckoutFields::rules($form)['telephone'])->toContain('nullable')
        ->and(CheckoutFields::termsUrl())->toBeNull();
});

it('hides the delivery note for collection orders', function(): void {
    Location::updateOrderType(LocationModel::COLLECTION);

    expect(array_column(CheckoutFields::present(checkoutForm()), 'name'))->not->toContain('delivery_comment');
});

it('includes fields extensions add', function(): void {
    Event::listen('checkout.form.extendFieldsBefore', function($form): void {
        $form->fields['diet'] = [
            'label' => 'Diet',
            'type' => 'select',
            'tab' => 'details',
            'comment' => 'Tell the kitchen',
            'options' => ['vegan' => 'Vegan', 'halal' => 'Halal'],
        ];
        $form->config['rules']['diet'] = 'required|in:vegan,halal';
    });

    $form = checkoutForm();
    $diet = collect(CheckoutFields::present($form))->firstWhere('name', 'diet');

    expect($diet)->toMatchArray([
        'type' => 'select',
        'required' => true,
        'comment' => 'Tell the kitchen',
        'options' => [['value' => 'vegan', 'label' => 'Vegan'], ['value' => 'halal', 'label' => 'Halal']],
    ])
        ->and(CheckoutFields::rules($form)['diet'])->toBe('required|in:vegan,halal')
        ->and(CheckoutFields::attributes($form)['diet'])->toBe('Diet')
        ->and(CheckoutFields::messages($form))->toHaveKey('termsAgreed.accepted');
});

it('lets fields without rules through', function(): void {
    Event::listen('checkout.form.extendFieldsBefore', function($form): void {
        $form->fields['nickname'] = ['label' => 'Nickname', 'type' => 'text'];
    });

    expect(CheckoutFields::rules(checkoutForm())['nickname'])->toBe(['nullable']);
});
