# ADR-0003: Ziggy instead of Wayfinder for frontend routes

- **Status:** Accepted
- **Date:** 2026-10-16
- **Supersedes:** the Wayfinder part of [ADR-0002](0002-frontend-stack-and-starter-kit.md)

## Context

The starter kit used Laravel Wayfinder: a Vite plugin ran `php artisan wayfinder:generate` and wrote typed TypeScript helpers per controller and route (`PropertyController.edit(5)`) into gitignored folders. In practice:

- The generated files (about 900 lines per controller) were confusing to read and easy to mistake for code that must be written by hand.
- Two styles were mixed: controller-based (`@/actions/...`) and route-name-based (`@/routes/...`).
- `npm run build` needed PHP, which complicates a multi-stage Docker build where assets are built in a Node-only image.
- Wayfinder is at version 0.1. Ziggy is the long-established tool used by most existing Laravel + Inertia projects.

## Decision

**Use Ziggy** (`tightenco/ziggy` + `ziggy-js`) and remove Wayfinder completely.

- `@routes` in `resources/views/app.blade.php` prints the named routes as `window.Ziggy`.
- Vue files call `route('admin.properties.edit', id)`, imported explicitly with `import { route } from 'ziggy-js'`, the same helper name and arguments as Laravel's `route()` in PHP.
- `config/ziggy.php` leaves out routes the frontend never calls (`storage.*`, `well-known.*`, `boost.*`, `route.info`).
- Routes with a non-GET method used as links (`logout`, `verification.send`) pass `method="post"` explicitly, because a URL string carries no method.
- Inertia `<Form>` uses `:action="route(...)"` with `method="put|patch|delete"`; Inertia sends the real HTTP method over XHR.

## Alternatives considered

- **Keep Wayfinder.** Rejected for the reasons above, although it gives stronger type checking.
- **Hard-coded URL strings.** Rejected: a renamed route would have to be found by searching, and paths like `/admin/properties` would be repeated everywhere.
- **Global `ZiggyVue` plugin** (`route()` available in templates without importing). Rejected in favour of explicit imports, so each file shows where `route` comes from and TypeScript checks the call.

## Consequences

- Route **names** are plain strings: `route('admin.propertys.edit')` fails in the browser, not at build time. Feature tests and clicking through pages are the safety net. `php artisan ziggy:generate --types` can add autocompletion for route names later if needed.
- The full list of named routes (including admin ones) is readable in the page source. This is acceptable because every admin route is protected by `auth` and `can:access-admin`; knowing a URL grants no access.
- Building frontend assets no longer requires PHP.
- No generated files to explain or regenerate.
