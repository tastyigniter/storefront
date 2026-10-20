<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'between:1,48'],
            'last_name' => ['required', 'between:1,48'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'min:6', 'max:32', 'confirmed'],
        ];
    }
}
