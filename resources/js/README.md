# Introduction

Shared Vue storefront modules for TastyIgniter themes. Themes import these files and compile them with their own Vite build.

```ts
import { uiStore } from '@tastyigniter/storefront/ui';
```

`vue`, `@inertiajs/vue3`, and `@tanstack/vue-store` are peer dependencies, so a theme keeps a single copy of each.

## Release process

This folder is published to npm as [`@tastyigniter/storefront`](https://www.npmjs.com/package/@tastyigniter/storefront). The package ships the TypeScript source in `src` as it is, so there is no build step. Releases are made by hand from this folder; CI does not publish them.

### 1. Decide the version

The package follows [semantic versioning](https://semver.org). Themes depend on `^4.0.0`, so they pick up minor and patch releases with `npm update`.

- **Patch** (`4.0.3` → `4.0.4`): bug fixes that leave every export and type unchanged.
- **Minor** (`4.0.4` → `4.1.0`): new exports, new optional arguments, or new fields on shared types.
- **Major** (`4.1.0` → `5.0.0`): a removed or renamed export, a changed function signature, or a raised peer dependency range. Each theme then needs its `package.json` range changed by hand.

When a change in `src` depends on data the PHP side now shares with the page (a new prop, route, or type field), push the PHP change to `main` first. Themes get the PHP code straight away but keep the old Vue modules until they update.

### 2. Try the release in a theme

Pack the package exactly as npm will publish it, and install the tarball in a theme without saving it:

```bash
cd resources/js
npm pack
```

```bash
cd path/to/theme
npm install --no-save path/to/tastyigniter-storefront-x.x.x.tgz
```

```bash
npm run lint && npm run test:coverage && npm run build
```

### 3. Bump the version

Change the version in `package.json` without npm creating a git tag:

```bash
npm version patch
```

Use `minor` or `major` in place of `patch` as decided above. Commit the bump on its own, then push to `main`:

```bash
git commit -m "chore(js): release @tastyigniter/storefront 4.0.4" -- package.json
```

Publish only from a pushed commit, so the code on npm can always be found in the repository.

### 4. Publish

Login to npm if you haven't already:

```bash
npm login
```

Check which files will be uploaded first; the list should be `package.json`, `README.md`, `LICENSE.md`, and `src`:

```bash
npm publish
```

Check that npm now lists the new version as `latest`:

```bash
npm view @tastyigniter/storefront version
```

To try a version in a theme before making it `latest`, publish a pre-release under its own tag. `npm update` in the themes then ignores it until it is installed by name:

```bash
npm version prerelease --preid beta --no-git-tag-version
npm publish --access public --tag next
```

### Fixing a bad release

Do not unpublish; npm only allows it for 72 hours, and themes may already have installed the version. Publish a fixed version instead, then mark the bad one so installs warn about it:

```bash
npm deprecate @tastyigniter/storefront@4.0.4 "Broken release, use 4.0.5"
```
