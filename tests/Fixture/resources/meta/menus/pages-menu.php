<?php

declare(strict_types=1);

// Sidebar on static pages. "All static pages" lists every published page
// that is not hidden from navigation.
return [
    'name' => 'Pages menu',
    'items' => [
        ['title' => 'Pages', 'code' => 'pages', 'type' => 'all-static-pages', 'reference' => ''],
    ],
];
