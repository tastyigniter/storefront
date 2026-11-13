<?php

declare(strict_types=1);

namespace Igniter\Storefront\Blocks;

/**
 * The form to add an address or edit the one chosen.
 */
class AddressForm extends PageBlock
{
    protected string $code = 'address-form';

    protected string $icon = 'fa fa-map-location-dot';

    protected array $pages = ['account.address'];
}
