<?php

declare(strict_types=1);

use Igniter\Storefront\Support\StorefrontRoutes;

// Header navigation. Synced into Design > Static Menus, where links can be added or reordered.
// Items with children render as a dropdown.
// URLs are resolved by route name. Activation syncs this file before the routes exist.
return [
    'name' => 'Main menu',
    'items' => [
        ['title' => 'Order online', 'code' => 'order', 'type' => 'url', 'url' => StorefrontRoutes::url('igniter.theme.order.current')],
        ['title' => 'Locations', 'code' => 'locations', 'type' => 'url', 'url' => StorefrontRoutes::url('igniter.theme.locations')],
        ['title' => 'Book a table', 'code' => 'book', 'type' => 'url', 'url' => StorefrontRoutes::url('igniter.theme.booking')],
        ['title' => 'Contact', 'code' => 'contact', 'type' => 'url', 'url' => StorefrontRoutes::url('igniter.theme.contact')],
    ],
];
