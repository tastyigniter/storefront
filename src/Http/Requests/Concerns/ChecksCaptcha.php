<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Requests\Concerns;

use Igniter\Storefront\Support\ThemeContent;

/**
 * Adds igniter.frontend's `recaptcha` rule when reCAPTCHA keys are configured,
 * and keeps the token out of the validated data the actions receive.
 */
trait ChecksCaptcha
{
    protected function captchaRules(): array
    {
        return ThemeContent::captchaRules();
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        return $key === null && is_array($data) ? array_except($data, ['g-recaptcha-response']) : $data;
    }
}
