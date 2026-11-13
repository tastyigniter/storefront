<?php

declare(strict_types=1);

return [
    'pages' => [
        'home' => [
            'label' => 'Home',
            'default' => [
                [
                    'id' => 's_home_hero',
                    'layout' => 'sidebar-right-4',
                    'columns' => [
                        ['blocks' => [['id' => 'b_home_hero', 'code' => 'hero', 'settings' => ['title' => 'Hot food, fast']]]],
                        ['blocks' => []],
                    ],
                ],
            ],
        ],
        'contact' => [
            'label' => 'Contact',
        ],
        'checkout.checkout' => [
            'label' => 'Checkout',
        ],
        'booking' => [
            'label' => 'Booking',
        ],
    ],
];
