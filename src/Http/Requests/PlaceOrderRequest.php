<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Igniter\Cart\Classes\CheckoutForm;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Storefront\Support\CheckoutFields;
use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    protected ?CheckoutForm $checkoutForm = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules come from the checkout form, so fields and rules added by extensions apply.
     */
    public function rules(): array
    {
        return array_merge(CheckoutFields::rules($this->checkoutForm()), [
            'payment' => ['nullable', 'string', 'max:128'],

            // Tokens and flags the card gateways read in processPaymentForm().
            'pay_from_profile' => ['nullable', 'boolean'],
            'create_payment_profile' => ['nullable', 'boolean'],
            'stripe_payment_method' => ['nullable', 'string', 'max:255'],
            'stripe_idempotency_key' => ['nullable', 'string', 'max:255'],
            'square_card_nonce' => ['nullable', 'string', 'max:1024'],
            'square_card_token' => ['nullable', 'string', 'max:2048'],
            'authorizenetaim_DataDescriptor' => ['nullable', 'string', 'max:255'],
            'authorizenetaim_DataValue' => ['nullable', 'string', 'max:4096'],
        ]);
    }

    public function messages(): array
    {
        return CheckoutFields::messages($this->checkoutForm());
    }

    public function attributes(): array
    {
        return CheckoutFields::attributes($this->checkoutForm());
    }

    protected function checkoutForm(): CheckoutForm
    {
        return $this->checkoutForm ??= CheckoutFields::make(resolve(OrderManager::class)->loadOrder());
    }
}
