<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests;

use Igniter\Storefront\Http\Requests\Concerns\ChecksCaptcha;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    use ChecksCaptcha;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:96'],
            'telephone' => ['required', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:128'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ], $this->captchaRules());
    }
}
