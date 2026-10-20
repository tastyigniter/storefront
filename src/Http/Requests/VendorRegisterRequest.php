<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Igniter\Storefront\Http\Requests\Concerns\ChecksCaptcha;
use Igniter\Storefront\Support\VendorSignup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class VendorRegisterRequest extends FormRequest
{
    use ChecksCaptcha;

    public function authorize(): bool
    {
        return VendorSignup::available();
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'between:2,32', 'unique:locations,location_name'],
            'description' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:filter', 'max:96'],
            'telephone' => ['nullable', 'string', 'max:32'],
            'street' => ['required', 'between:2,128'],
            'city' => ['nullable', 'string', 'max:128'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'countryId' => ['required', 'integer'],
            'admin' => ['required', 'array'],
            'admin.name' => ['required', 'string', 'max:128'],
            'admin.email' => ['required', 'email:filter', 'max:96', 'unique:admin_users,email'],
            'admin.username' => ['required', 'alpha_dash', 'unique:admin_users,username'],
            'admin.password' => ['required', Password::min(8)->numbers()->symbols()->letters()->mixedCase(), 'same:admin.password_confirm'],
            'admin.password_confirm' => ['required', 'string'],
            'cuisines' => ['nullable', 'array'],
            'cuisines.*' => ['integer'],
        ], $this->captchaRules());
    }
}
