<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Igniter\Storefront\Http\Requests\Concerns\ChecksCaptcha;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use ChecksCaptcha;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'first_name' => ['required', 'between:1,48'],
            'last_name' => ['required', 'between:1,48'],
            'email' => ['required', 'email', 'max:96', 'unique:customers,email'],
            'password' => ['required', 'min:6', 'max:32', 'confirmed'],
            'telephone' => ['required', 'string', 'max:20'],
            'newsletter' => ['boolean'],
        ], $this->captchaRules());
    }
}
