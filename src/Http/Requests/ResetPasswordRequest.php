<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'min:6', 'max:32', 'confirmed'],
        ];
    }
}
