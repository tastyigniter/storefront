<?php

declare(strict_types=1);

use Igniter\Storefront\Support\StorefrontRoutes;

// Synced into Design > Static Menus, where the columns and links can be edited.
// URLs are resolved by route name. Activation syncs this file before the routes exist.
return [
    'name' => 'Footer menu',
    'items' => [
        [
            'title' => 'Order',
            'code' => 'order',
            'type' => 'header',
            'items' => [
                ['title' => 'Order online', 'code' => 'order-online', 'type' => 'url', 'url' => StorefrontRoutes::url('igniter.theme.order.current')],
                ['title' => 'Book a table', 'code' => 'book-table', 'type' => 'url', 'url' => StorefrontRoutes::url('igniter.theme.booking')],
                ['title' => 'Contact us', 'code' => 'contact', 'type' => 'url', 'url' => StorefrontRoutes::url('igniter.theme.contact')],
            ],
        ],
        [
            'title' => 'Information',
            'code' => 'information',
            'type' => 'header',
            'items' => [
                ['title' => 'About Us', 'code' => 'about-us', 'type' => 'static-page', 'reference' => 'about-us'],
                ['title' => 'Privacy Policy', 'code' => 'policy', 'type' => 'static-page', 'reference' => 'policy'],
                ['title' => 'Terms and Conditions', 'code' => 'terms', 'type' => 'static-page', 'reference' => 'terms-and-conditions'],
            ],
        ],
    ],
];
