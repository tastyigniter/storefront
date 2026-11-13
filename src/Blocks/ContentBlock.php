<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Igniter\Main\Classes\BaseBlock;
use Override;
use Stevebauman\Purify\Facades\Purify;

/**
 * Base class for storefront content blocks. Settings listed in $htmlFields
 * are cleaned with Purify before they are saved or rendered.
 */
abstract class ContentBlock extends BaseBlock
{
    /** @var list<string> */
    protected array $htmlFields = [];

    #[Override]
    public function sanitize(array $settings): array
    {
        $settings = parent::sanitize($settings);

        // Switches post 0 or 1.
        foreach ($this->defineFields() as $name => $field) {
            if (($field['type'] ?? null) === 'switch' && array_key_exists($name, $settings)) {
                $settings[$name] = (bool)$settings[$name];
            }
        }

        foreach ($this->htmlFields as $field) {
            if (isset($settings[$field]) && is_string($settings[$field])) {
                $config = $this->purifierConfig();
                $settings[$field] = $config ? Purify::config($config)->clean($settings[$field]) : Purify::clean($settings[$field]);
            }
        }

        return $settings;
    }

    /**
     * HTML Purifier settings for this block, null uses the application default.
     */
    protected function purifierConfig(): ?array
    {
        return null;
    }

    protected function lang(string $key): string
    {
        return 'igniterlabs.storefront::default.builder.'.$key;
    }

    protected function alignmentField(): array
    {
        return [
            'label' => $this->lang('label_alignment'),
            'type' => 'radiotoggle',
            'default' => 'start',
            'options' => [
                'start' => $this->lang('text_left'),
                'center' => $this->lang('text_center'),
                'end' => $this->lang('text_right'),
            ],
            'validationRule' => ['in:start,center,end'],
        ];
    }

    protected function buttonFields(): array
    {
        return [
            'button_text' => [
                'label' => $this->lang('label_button_text'),
                'type' => 'text',
                'span' => 'left',
                'validationRule' => ['nullable', 'string', 'max:80'],
            ],
            'button_link' => [
                'label' => $this->lang('label_button_link'),
                'type' => 'text',
                'span' => 'right',
                'placeholder' => '/order',
                'validationRule' => ['nullable', 'string', 'max:255', 'regex:/^(\/|#|https?:\/\/|mailto:|tel:)/i'],
            ],
        ];
    }

    /**
     * A switch field, on by default.
     */
    protected function switch(string $label, string $span = 'full'): array
    {
        return [
            'label' => $this->lang($label),
            'type' => 'switch',
            'span' => $span,
            'default' => true,
        ];
    }

    protected function imageUrl(?string $path): ?string
    {
        return $path ? media_url($path) : null;
    }
}
