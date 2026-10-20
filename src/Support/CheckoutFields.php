<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Admin\Classes\FormField;
use Igniter\Cart\Classes\CheckoutForm;
use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use Illuminate\Support\Facades\File;

/**
 * Builds the checkout fields with Igniter\Cart\Classes\CheckoutForm, like the Orange theme,
 * so checkout.form.extendFieldsBefore / extendFields always run for extensions.
 */
class CheckoutFields
{
    public static function make(Order $order): CheckoutForm
    {
        $config = File::getRequire(__DIR__.'/../../resources/models/checkoutfields.php');
        $config['model'] = $order;

        $form = resolve(CheckoutForm::class, ['config' => $config]);
        $form->bindEvent('form.extendFieldsBefore', fn() => self::applyThemeSettings($form));
        $form->initialize();

        return $form;
    }

    /**
     * Fields as plain data for the checkout page.
     */
    public static function present(CheckoutForm $form): array
    {
        return collect($form->getFields())
            ->map(fn(FormField $field, string $name): array => [
                'name' => $name,
                'label' => lang((string)$field->label),
                'type' => $field->type,
                'tab' => $field->tab ?? 'details',
                'value' => $field->value,
                'placeholder' => $field->placeholder ? lang($field->placeholder) : null,
                'comment' => $field->comment ? lang($field->comment) : null,
                'required' => self::isRequired($form, $name),
                'options' => in_array($field->type, ['select', 'radio'], true)
                    ? collect((array)$field->options())->map(fn($label, $value): array => [
                        'value' => $value,
                        'label' => lang((string)$label),
                    ])->values()->all()
                    : [],
                'attributes' => array_filter((array)($field->attributes['field'] ?? [])),
                'termsUrl' => $name === 'termsAgreed' ? self::termsUrl() : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Validation rules for the fields that exist after extensions have run.
     */
    public static function rules(CheckoutForm $form): array
    {
        $rules = (array)$form->getConfig('rules', []);

        // Fields an extension adds without rules still pass their value through.
        return collect($form->getFields())
            ->map(fn(FormField $field, string $name): mixed => $rules[$name] ?? ['nullable'])
            ->all();
    }

    public static function messages(CheckoutForm $form): array
    {
        return (array)$form->getConfig('messages', []);
    }

    public static function attributes(CheckoutForm $form): array
    {
        return collect($form->getFields())
            ->map(fn(FormField $field): string => lang((string)$field->label))
            ->all();
    }

    public static function termsUrl(): ?string
    {
        $slug = (string)ThemeContent::value('checkout_terms_page', 'terms-and-conditions');

        return $slug !== '' ? '/'.ltrim($slug, '/') : null;
    }

    /**
     * Same switches as the Orange checkout component, from the Glass theme settings.
     */
    protected static function applyThemeSettings(CheckoutForm $form): void
    {
        if (!self::termsUrl()) {
            unset($form->fields['termsAgreed']);
        }

        if (!(bool)ThemeContent::value('checkout_telephone_required', true)) {
            $form->config['rules']['telephone'] = ['nullable', 'regex:/^([0-9\s\-\+\(\)]*)$/i', 'max:20'];
        }

        if (!(bool)ThemeContent::value('checkout_show_comment', true)) {
            unset($form->fields['comment']);
        }

        if (!(bool)ThemeContent::value('checkout_show_delivery_comment', true) || !Location::orderTypeIsDelivery()) {
            unset($form->fields['delivery_comment']);
        }
    }

    protected static function isRequired(CheckoutForm $form, string $name): bool
    {
        // Rules can be an array or a pipe-separated string, e.g. 'required|in:a,b'.
        $rules = $form->getConfig('rules', [])[$name] ?? [];

        return collect(is_string($rules) ? explode('|', $rules) : (array)$rules)
            ->contains(fn($rule): bool => in_array($rule, ['required', 'accepted'], true));
    }
}
