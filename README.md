## Introduction

Storefront is the shared ordering engine for TastyIgniter themes. It provides the PHP routes, controllers, and checkout flow for the menu, basket, booking, and account pages, plus the Vue modules those pages import.

A theme supplies its own pages, layout, language lines, and styles. Storefront stays out of the visual design. Glass and Plum are two themes built on it.

The PHP package is `tastyigniter/storefront`. The Vue modules are published separately as [`@tastyigniter/storefront`](https://www.npmjs.com/package/@tastyigniter/storefront).

## Features

- Inertia storefront for online ordering and table booking
- Menu, basket, checkout, contact, locations, and static pages
- Customer account for profile, addresses, orders, and reservations
- Delivery and collection scheduling, including saved addresses
- Payment clients for the payregister gateways a location has enabled
- Optional group ordering, dine-in tables, loyalty points, and gift cards when those extensions are installed
- Shared Vue helpers for routes, copy, the basket drawer, and maps

## How to Use

Require the PHP package from the theme:

```bash
composer require tastyigniter/storefront
```

Register the theme when its service provider boots. Routes and pages run only while this theme is the active one. `langNamespace` is the theme's own language file, edited under Localisation > Languages.

```php
use Igniter\Storefront\Config;
use Igniter\Storefront\Storefront;

Storefront::register(new Config(
    theme: 'igniterlabs-plum',
    rootView: 'plum::app',
    defaultBackground: '#fbf5f8',
    defaultRing: '#5f0f40',
    defaultFontUrl: 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap',
    defaultFontFamily: 'DM Sans',
    pwaDirectory: __DIR__.'/../resources/pwa',
    langNamespace: 'igniterlabs.plum',
));
```

Load the theme's Blade root view and translations from that same provider. The root view is the Inertia document. Page components live in the theme at `resources/js/pages` and are not part of this package.

Install the Vue modules next to Vue, Inertia, and TanStack Store. Those three are peer dependencies, so the theme keeps a single copy of each. Vite compiles the TypeScript source. There is no separate storefront build.

```bash
npm install @tastyigniter/storefront@4.0 vue @inertiajs/vue3 @tanstack/vue-store
```

```ts
import { t } from '@tastyigniter/storefront/lang';
import { route, syncRoutes } from '@tastyigniter/storefront/routes';
import { useStorefront } from '@tastyigniter/storefront/storefront';
import { openCart } from '@tastyigniter/storefront/ui';
```

## Reporting issues

If you encounter a bug in this package, please report it using the [Issue Tracker](https://github.com/tastyigniter/TastyIgniter/issues) on GitHub.

## Contributing

Contributions are welcome! Please read [TastyIgniter's contributing guide](https://tastyigniter.com/docs/resources/contribution-guide).

## Security vulnerabilities

For reporting security vulnerabilities, please see [our security policy](https://github.com/tastyigniter/storefront/security/policy).

## License

TastyIgniter Storefront is open-source software licensed under the [MIT license](https://github.com/tastyigniter/storefront/blob/main/LICENSE.md).
