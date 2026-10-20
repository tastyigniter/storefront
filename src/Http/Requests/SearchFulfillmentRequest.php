<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['required_without_all:address_id,latitude', 'nullable', 'string', 'max:1000'],
            'address_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }
}
