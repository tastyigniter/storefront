<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_id' => ['required', 'integer'],
            'row_id' => ['nullable', 'string', 'max:64'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:500'],
            'menu_options' => ['nullable', 'array'],
        ];
    }
}
