# Introduction

Shared Vue storefront modules for TastyIgniter themes. Themes import these files and compile them with their own Vite build.

```ts
import { uiStore } from '@tastyigniter/storefront/ui';
```

`vue`, `@inertiajs/vue3`, and `@tanstack/vue-store` are peer dependencies, so a theme keeps a single copy of each.
