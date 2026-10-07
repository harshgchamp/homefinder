# ADR-0002: Frontend stack and starter kit

- **Status:** Accepted
- **Date:** 2026-10-04

## Context

HomeFinder needs authentication, account settings and an application shell before any feature work can start. Building those by hand costs days and is easy to get wrong. The frontend also needs to stay in step with the backend as routes and data shapes change.

## Decision

**Start from the official Laravel 13 Vue starter kit** and keep its choices instead of replacing them.

- **Vue 3 with Inertia 3.** Pages are Vue components in `resources/js/pages`, rendered from Laravel controllers. There is no separate API and no client-side router to maintain.
- **TypeScript everywhere on the frontend.** `vue-tsc --noEmit` runs as `npm run types:check`. Wayfinder generates typed route and controller helpers into `resources/js/routes` and `resources/js/actions`, so a renamed route breaks the build and not the page.
- **Tailwind 4 with shadcn-vue components** (Reka UI, Lucide icons). The components are copied into `resources/js/components/ui`, so they are ours to edit.
- **Fortify for authentication**, including two-factor and passkeys, which the kit ships with working screens and tests.
- **Pest 4 for tests.** Feature tests use `RefreshDatabase` against the `testing` MySQL database. The kit comes with 40 passing tests covering auth and settings.

Quality checks are wired as Composer scripts: `lint` (Pint), `analyse` (Larastan at level 7) and `test` (lint check, analysis, then Pest). `ci:check` adds the frontend checks.

## Alternatives considered

- **React or Livewire starter kit.** Both are equally supported. Vue was chosen for familiarity; nothing in the project requires one over the others.
- **Separate SPA with a JSON API.** Rejected: it doubles the routing, auth and validation work for a single-team project with no second client.
- **Plain JavaScript.** Rejected: the typed Wayfinder helpers and typed page props are the main protection against backend and frontend drifting apart.
- **PHPUnit syntax.** Pest runs on PHPUnit, so nothing is lost, and the kit's tests are already written in Pest.

## Consequences

- Auth, settings and layout exist from the first commit, with tests.
- The kit's code now belongs to this project. Upstream starter kit improvements will not arrive automatically and have to be ported by hand.
- Frontend work needs Node as well as PHP, and the Wayfinder output must be regenerated when routes change (the Vite plugin does this in dev).
- Several dependencies are young (`laravel/wayfinder` 0.1, `laravel/passkeys` 0.2, `vite-plus` 0.3) and may have breaking changes before 1.0.
