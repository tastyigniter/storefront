<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchFulfillmentRequest extends FormRequest
{
    /** Pages a location search can open once the location is found, by page code. */
    public const array REDIRECT_PAGES = ['order', 'locations', 'booking', 'checkout.checkout', 'home'];

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
            'redirect_to' => ['nullable', 'in:'.implode(',', self::REDIRECT_PAGES)],
        ];
    }
}
