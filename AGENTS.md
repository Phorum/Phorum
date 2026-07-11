# Phorum

## Project Overview

Ground-up rewrite of the Phorum forum application, versioned as Phorum 10 to mark the break from the legacy PHP4/5-era codebase — the number reflects that break, not a count of sequential releases. The primary constraint is **schema compatibility with Phorum 6** so existing Phorum 6 installations can upgrade in-place without a data migration. (Phorum 5.x installs must first upgrade to Phorum 6 before moving to Phorum 10.)

## Tech Stack

- **Language**: PHP 8.3+
- **Package Manager**: Composer
- **Database**: MySQL / MariaDB
- **Routing**: `pagemill/router` ^3.0
- **Templating**: Twig 3
- **Markdown**: `league/commonmark` ^2.8
- **Email**: PHPMailer ^6.9
- **Testing**: PHPUnit ^11.0

## Key Commands

- Install: `composer install`
- Dev: `docker compose up`
- Test all: `vendor/bin/phpunit`
- Test single: `vendor/bin/phpunit path/to/TestFile.php`

*(Lint/fix tooling not yet configured in this project.)*

## Project Structure

```
public/index.php        Single web entrypoint — boots Config + App, runs router
src/Core/App.php        Wires Twig, modules, router, auth, lang on every request
src/Core/               Config, Auth, AdminAuth, Lang, CsrfGuard
src/Http/Controller.php Base controller (render, respond, redirect, checkCsrf, baseData)
src/Http/Controllers/   Route controllers; Admin/ subdirectory for admin UI
src/Mapper/             DB access via dealnews/data-mapper — one mapper per DB table
src/Model/              Plain model classes — one per DB table
src/Service/            Business logic (MessageService, PmService, etc.)
src/Hook/               HookDispatcher + procedural phorum_api_hook() wrapper
src/Twig/               PhorumExtension — Twig functions/filters (trans, csrf_field, etc.)
etc/routes.php          Full route table for pagemill/router
etc/phorum.php          Runtime config (DB creds, site name, etc.) — not in version control
etc/config.ini          Database credentials for dealnews/db — not in version control
lang/en.php             Canonical i18n string list; all locale files overlay on top of it
lang/                   Per-locale PHP array files (es, fr, de, zh-CN, ar, etc.)
templates/              Twig templates; admin/ subdirectory for admin panel
themes/                 Per-theme CSS + optional template overrides
mods/                   Drop-in plugin modules (bbcode/, etc.)
.dev/phorum_old/        Original Phorum 6 source — READ-ONLY reference, do not modify
db/                     Schema files: mysql.sql, postgresql.sql
```

## Code Style

- 1TBS bracing style
- snake_case variables
- Protected visibility by default
- Single return point preference
- Class-based API (no bare functions)
- Dependency injection is handled by optional parameters passed to class constructors (unless specified, otherwise)
- Complete PHPDoc coverage

## Non-Obvious Patterns

- **DB schema is frozen**: never rename columns, change data types, or drop columns from existing Phorum 6 tables. Additive changes (new indexes, new tables for new features) are fine.
- **No `$PHORUM` global**: the old codebase used a `$PHORUM` superglobal everywhere. New code passes dependencies explicitly or via constructor injection.
- **pagemill/router regex patterns** require full PCRE delimiters — `!^/path/(\d+)$!` not `/path/(\d+)`. Missing delimiters cause a silent match failure.
- **`dealnews/db` LIMIT/OFFSET**: PDO cannot bind integers for LIMIT/OFFSET — interpolate them directly with an explicit `(int)` cast.
- **`AbstractPhorumMapper::lastInsertId()`** returns `string` — cast with `(int)` at the call site.
- **DealNews private Composer packages** use the `php-libraries/` namespace in composer.json. The `dealnews/` namespace is for open-source packages only. When adding new internal dependencies, check both namespaces.
- **CSRF on every POST**: call `if (!$this->checkCsrf()) { return; }` as the first line of every POST handler. Every `<form method="post">` template must include `{{ csrf_field() }}`.
- **i18n**: all user-visible strings go through `{{ trans('key') }}` in Twig or `Lang::get('key')` in PHP. Add new keys to `lang/en.php` first — other locale files fall back to English automatically via the 3-layer chain: `en.php` → `{base}.php` → `{locale}.php`.
- **`.dev/phorum_old/`** exists only as a reference for the original behavior and schema. Read it when uncertain about legacy behavior; never modify it.

## Workflow

- All tests must pass after code changes — run `vendor/bin/phpunit`. There should be no PHPUnit deprecation warnings.

## Key Files

| File | Purpose |
|------|---------|
| `public/index.php` | Web entrypoint. Bootstraps `Config` and `App`. |
| `src/Core/App.php` | Request lifecycle: Twig init → module loading → routing → auth → lang → dispatch. |
| `etc/routes.php` | Route table. Routes map to `Phorum\Http\Controllers\{Controller}@{method}`. |
| `src/Http/Controller.php` | Base controller with shared helpers used by every route controller. |
| `src/Core/Lang.php` | i18n loader. `Lang::load($locale)` builds the 3-layer fallback string table. |
| `lang/en.php` | Reference string list with all translation keys. |
| `src/Hook/functions.php` | Procedural `phorum_api_hook()` wrapper for backward-compatible plugin hooks. |
| `etc/phorum.example.php` | Annotated config template — copy to `etc/phorum.php` to configure a local instance. |
