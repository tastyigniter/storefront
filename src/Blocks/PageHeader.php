<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

use Override;

/**
 * A photo banner with the page title, for the top of a page.
 */
class PageHeader extends PageBlock
{
    protected string $code = 'page-header';

    protected string $icon = 'fa fa-panorama';

    protected array $pages = [];

    #[Override]
    public function defineFields(): array
    {
        return [
            'eyebrow' => $this->wording('label_eyebrow', 'left'),
            'title' => $this->wording('label_title', 'right'),
            'text' => $this->wording('label_text', 'full', 'textarea'),
            'alignment' => $this->choice('label_alignment', ['start' => 'text_left', 'center' => 'text_center'], 'center', 'left'),
            'show_icon' => $this->switch('label_show_icon', 'right'),
            'location_photo' => array_merge($this->switchOff('label_location_photo', 'left'), ['comment' => $this->lang('help_location_photo')]),
            'overlap' => array_merge($this->switch('label_overlap', 'right'), ['comment' => $this->lang('help_overlap')]),
        ];
    }
}
