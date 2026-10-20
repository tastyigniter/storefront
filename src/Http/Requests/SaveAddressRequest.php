<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_id' => ['nullable', 'integer'],
            'address_1' => ['required', 'string', 'max:128'],
            'address_2' => ['nullable', 'string', 'max:128'],
            'city' => ['required', 'string', 'max:128'],
            'state' => ['nullable', 'string', 'max:128'],
            'postcode' => ['required', 'string', 'max:10'],
            'country_id' => ['required', 'integer'],
        ];
    }
}
