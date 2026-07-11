# Phorum

![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777bb4)
![License](https://img.shields.io/badge/license-BSD--3--Clause-blue)

A modern, ground-up rewrite of the [Phorum](https://www.phorum.org/) web message board — full class-based PHP 8.3 architecture, Twig templates, and Composer dependency management, while staying schema-compatible with Phorum 6 so existing installations can upgrade in place with no data migration.

## Table of Contents

- [Tech Stack & Requirements](#tech-stack--requirements)
- [Installation & Setup](#installation--setup)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [Available Commands](#available-commands)
- [Architecture Overview](#architecture-overview)
- [Themes & Plugins](#themes--plugins)
- [Testing](#testing)
- [License](#license)

## Tech Stack & Requirements

- **PHP 8.3+** with `pdo`, `pdo_mysql`, `mbstring`, `json`, and `fileinfo` extensions
- **MySQL 8.0+ or MariaDB 10.6+**
- **Composer 2**
- A web server: **Apache 2.4+** (`mod_rewrite`) or **Nginx** with PHP-FPM
- Key libraries: [`twig/twig`](https://twig.symfony.com/) 3, [`pagemill/router`](https://packagist.org/packages/pagemill/router) 3, [`league/commonmark`](https://commonmark.thephpleague.com/) 2.8, [`phpmailer/phpmailer`](https://github.com/PHPMailer/PHPMailer) 6.9, [`phpunit/phpunit`](https://phpunit.de/) 11 (dev)

See `composer.json` for the full, version-pinned dependency list.

## Installation & Setup

For a full production installation guide (web server config, database setup, subfolder installs, post-install checklist), see **[docs/installation.md](docs/installation.md)**.

Quick start for local development, using Docker:

```bash
git clone <repo-url> phorum
cd phorum
composer install
cp etc/phorum.example.php etc/phorum.php
cp etc/config.ini.example etc/config.ini
docker compose up
```

Then visit `http://localhost:8080/install` to run the web installer, which checks PHP requirements, creates the database tables, and sets up the initial administrator account.

Without Docker, point your web server's document root at `public/`, install dependencies with `composer install --no-dev --optimize-autoloader`, and follow the same config steps above — see [docs/installation.md](docs/installation.md) for full web server configuration (Apache/Nginx).

## Configuration

Two config files, both copied from `.example` templates and excluded from version control:

| File | Purpose |
|------|---------|
| `etc/phorum.php` | Site name, theme, database name/prefix, `base_url`/`base_path`, session security, mail settings, admin secret. See `etc/phorum.example.php` for every available key with inline documentation. |
| `etc/config.ini` | Database connection credentials (`dealnews/db` format) — host, user, password, database name. |

The `db_name` key in `etc/phorum.php` must match the `[db.X]` section name in `etc/config.ini`.

## Running the Application

**Development** (Docker): `docker compose up` — starts MySQL, PHP-FPM, and Nginx (forum available at `http://localhost:8080`).

**Production**: standard PHP-FPM + Apache/Nginx setup with the document root at `public/`. See [docs/installation.md](docs/installation.md) for full web server configuration, HTTPS/`session_secure`, and a post-install hardening checklist.

## Available Commands

| Command | Description |
|---------|--------------|
| `composer install` | Install PHP dependencies |
| `vendor/bin/phpunit` | Run the full test suite |
| `vendor/bin/phpunit path/to/Test.php` | Run a single test file |
| `docker compose up` | Start the local dev stack (MySQL, PHP-FPM, Nginx) |
| `./release.sh <version>` | Tag a release, strip dev-only files, and build `dist/phorum-<version>.tar.gz` and `.zip` |

*(Lint/fix tooling is not yet configured in this project.)*

## Architecture Overview

Every request enters through **`public/index.php`**, which boots `Config` and hands off to `Phorum\Core\App`:

1. `App` initializes Twig (registering the Markdown format hook), loads enabled plugin modules (`mods/`, e.g. BBCode), and builds the router from `etc/routes.php`.
2. Requests are redirected to `/install` until the app reports itself installed.
3. Once installed, `Auth`/`AdminAuth`/`Impersonation`/`Lang` are initialized and the route table (`pagemill/router`) matches the request URI to a `Controller@method` action.
4. The matched controller (constructed with `Config` and the Twig `Environment`) handles the request and returns a `Response`, which `App` sends to the client.

Key directories:

| Path | Purpose |
|------|---------|
| `src/Core/` | `Config`, `App`, `Auth`, `AdminAuth`, `Lang`, `CsrfGuard` — framework-level plumbing |
| `src/Http/` | Base `Controller` + one controller per route area (`Controllers/`, with an `Admin/` subdirectory for the admin panel) |
| `src/Mapper/` | Database access via `dealnews/data-mapper` — one mapper per table |
| `src/Model/` | Plain model classes, one per table |
| `src/Service/` | Business logic (moderation, PM, search, subscriptions, flood control, permissions, etc.) |
| `src/Hook/` | `HookDispatcher` + the procedural `phorum_api_hook()` wrapper that keeps old-style plugin hooks working |
| `src/Twig/` | `PhorumExtension` — custom Twig functions/filters (`trans`, `csrf_field`, `pagination_url`, etc.) |
| `templates/` | Twig templates (`admin/` subdirectory for the admin panel) |
| `themes/` | Per-theme stylesheets and optional template overrides |
| `etc/routes.php` | The full route table |
| `lang/` | `en.php` is the canonical string list; all other locale files overlay on top of it via a 3-layer fallback chain |

There is no `$PHORUM` superglobal — dependencies are passed explicitly via constructor injection.

## Themes & Plugins

Six themes ship by default (Emerald, Topaz, Sapphire, Ruby, Diamond, Amethyst) — see **[docs/theme-development.md](docs/theme-development.md)** for building your own.

Old-style Phorum plugin hooks are still supported through `phorum_api_hook()` (`src/Hook/functions.php`); the BBCode formatter (`mods/bbcode/`) is included as a reference implementation. Markdown is also supported natively, per-message.

## Testing

```bash
vendor/bin/phpunit
```

All tests must pass with no PHPUnit deprecation warnings before merging. The suite covers controllers, services, mappers, models, and the Twig extension — see `phpunit.xml.dist` for configuration.

## License

BSD 3-Clause — see [LICENSE.txt](LICENSE.txt).
