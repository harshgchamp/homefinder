# HomeFinder

<!-- TODO(harsh): confirm this one-line purpose. -->

HomeFinder is a web application for searching and shortlisting homes. It is at the foundation stage: authentication, account settings and the application shell are in place, and the property features are still to be built.

## Stack

| Layer             | Choice                                                            |
| ----------------- | ----------------------------------------------------------------- |
| Backend           | Laravel 13 on PHP 8.5, Fortify for auth (two-factor and passkeys) |
| Frontend          | Vue 3, Inertia 3, TypeScript, Tailwind 4, shadcn-vue              |
| Data              | MySQL 8.4, Redis                                                  |
| Local environment | Laravel Sail (Docker) on WSL2, Mailpit for mail                   |
| Quality           | Pest 4, Larastan (PHPStan level 7), Pint                          |

The reasons behind these choices are recorded in [docs/adr](docs/adr):

- [ADR-0001: Local development environment](docs/adr/0001-local-development-environment.md)
- [ADR-0002: Frontend stack and starter kit](docs/adr/0002-frontend-stack-and-starter-kit.md)

## Running locally

You need Docker and, on Windows, WSL2. Clone the project into the WSL2 filesystem (for example `~/code/homefinder`), not under `/mnt/c`.

**First-time setup**

```bash
# 1. Install PHP dependencies once with the host's Composer, to get Sail itself
composer install --ignore-platform-reqs

# 2. Create the environment file
cp .env.example .env
```

Then edit `.env` so it points at the Sail containers:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

```bash
# 3. Start the containers
./vendor/bin/sail up -d

# 4. Finish the setup inside the container
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
```

**Day to day**

```bash
./vendor/bin/sail up -d        # start
./vendor/bin/sail composer dev # app processes and the Vite dev server
./vendor/bin/sail down         # stop
```

| What                 | Where                                             |
| -------------------- | ------------------------------------------------- |
| Application          | http://localhost                                  |
| Mailpit inbox        | http://localhost:8025                             |
| MySQL (DBeaver etc.) | `127.0.0.1:3306`, database `laravel`, user `sail` |

Sail binds ports 80, 3306, 6379, 5173, 1025 and 8025. If another Sail project is running, stop it first or change the ports in `.env` (`APP_PORT`, `FORWARD_DB_PORT` and so on); see ADR-0001.

Always run PHP, Composer and Artisan through `sail`. The container has PHP 8.5; the host may not.

## Checks

```bash
./vendor/bin/sail composer lint      # fix code style with Pint
./vendor/bin/sail composer analyse   # static analysis with Larastan
./vendor/bin/sail composer test      # style check, static analysis, then the Pest suite
./vendor/bin/sail composer ci:check  # everything above plus the frontend checks
```
