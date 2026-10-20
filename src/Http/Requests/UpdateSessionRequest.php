<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer'],
            'order_type' => ['nullable', 'in:delivery,collection,dinein'],
            // Pages that switch location without leaving, e.g. booking a table.
            'return_to' => ['nullable', 'in:booking'],
        ];
    }
}
