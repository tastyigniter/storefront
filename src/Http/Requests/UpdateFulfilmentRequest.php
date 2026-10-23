<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFulfilmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_type' => ['required', 'string', 'max:32'],
            'asap' => ['required', 'boolean'],
            'date' => ['nullable', 'required_if:asap,false', 'date_format:Y-m-d'],
            'time' => ['nullable', 'required_if:asap,false', 'date_format:H:i'],
            'address' => ['nullable', 'string', 'max:1000'],
            'address_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'area_id' => ['nullable', 'integer'],
            'table_id' => ['nullable', 'integer'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
