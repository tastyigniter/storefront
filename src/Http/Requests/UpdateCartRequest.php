<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', 'in:plus,minus'],
        ];
    }
}
