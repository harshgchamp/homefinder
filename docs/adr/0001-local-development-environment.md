# ADR-0001: Local development environment

- **Status:** Accepted
- **Date:** 2026-10-04

## Context

HomeFinder is developed on a Windows machine. The app needs PHP 8.5, MySQL, Redis and a mail catcher, and those should be the same versions for everyone and should not depend on what is installed on the host (the host PHP is 8.3, the project runs on 8.5).

## Decision

**Laravel Sail inside WSL2.** The code lives in the WSL2 filesystem (`~/code/homefinder`), not under `/mnt/c`, and every PHP, Composer and Artisan command runs through `sail` so it uses the container's PHP 8.5.

Sail starts four containers, defined in `compose.yaml`:

| Service | Image | Host port | Override in `.env` |
|---|---|---|---|
| App (PHP 8.5) | `sail-8.5/app` | 80 | `APP_PORT` |
| Vite dev server | (same container) | 5173 | `VITE_PORT` |
| MySQL | `mysql:8.4` | 3306 | `FORWARD_DB_PORT` |
| Redis | `redis:alpine` | 6379 | `FORWARD_REDIS_PORT` |
| Mailpit SMTP / dashboard | `axllent/mailpit` | 1025 / 8025 | `FORWARD_MAILPIT_PORT` / `FORWARD_MAILPIT_DASHBOARD_PORT` |

Inside the containers the app reaches the others by service name (`DB_HOST=mysql`, `REDIS_HOST=redis`). Those names only resolve inside the Docker network.

**DBeaver** runs on Windows and connects through the forwarded port: host `127.0.0.1`, port `3306`, database `laravel`, user `sail`, password from `DB_PASSWORD` in `.env`. If it reports "Public Key Retrieval is not allowed", set the driver property `allowPublicKeyRetrieval=true`. A second database named `testing` is created by Sail and is used by the test suite.

## The cmslaravel conflict lesson

<!-- TODO(harsh): replace this paragraph with what actually happened. -->
Every Sail project binds the same default host ports (80, 3306, 6379, 5173, 1025, 8025). Two projects cannot be up at once on the defaults: the second one fails to start, or a tool such as DBeaver ends up talking to the other project's database. The rule from now on: run `sail down` in the other project before `sail up` here, or give this project its own ports with the `.env` overrides in the table above.

## Consequences

- No PHP, MySQL or Redis install is needed on the host; a new machine needs only WSL2 and Docker.
- Commands must be prefixed with `sail`. Running plain `php` or `composer` uses the host's PHP 8.3 and can produce a different result.
- File watching and Composer are fast because the code is on the Linux filesystem; moving it to `/mnt/c` would make both slow.
- `APP_URL` in `.env` is `http://localhost:8000` but `APP_PORT` is not set, so the app is actually served on port 80. One of the two needs to change so generated URLs match.
- `.env.example` still has the starter kit defaults (SQLite, log mailer). A fresh clone needs the Sail values (`DB_CONNECTION=mysql`, `DB_HOST=mysql`, `REDIS_HOST=redis`, `MAIL_PORT=1025`) copied in by hand until it is updated.
