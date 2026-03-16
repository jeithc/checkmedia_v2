# AGENTS.md

## Cursor Cloud specific instructions

### Project overview

Laravel 12 advertising audit app. Uses SQLite (file-based), Blade templates, Tailwind CSS 4, Vite 7.

### Critical: Injected environment variables

The Cursor Cloud VM injects secrets that **override** the `.env` file and break the default SQLite setup (the DB connection gets set to mysql instead of sqlite). Before running any `php artisan` command, you must unset the DB-related, cache, session, queue, broadcast, and app-key injected variables. This is already configured in `~/.bashrc` for new shell sessions. If commands fail with "could not find driver" for mysql, this is the cause.

### Common commands

See `composer.json` scripts section for the authoritative list. Key ones:

- **Lint**: `./vendor/bin/pint --test` (fix: `./vendor/bin/pint`)
- **Tests**: `php artisan test`
- **Dev server**: `composer dev` (runs artisan serve + queue + pail + vite via concurrently)
- **Dev server (manual)**: `php artisan serve --host=0.0.0.0 --port=8000` and `npm run dev`
- **Build frontend**: `npm run build`
- **Migrations**: `php artisan migrate --force`

### Database

Default: SQLite at `database/database.sqlite`. No external DB server needed. Create if missing: `touch database/database.sqlite`.

### Setup from scratch

See `composer.json` scripts.setup for the full bootstrap sequence. Requires PHP 8.4+ (the `composer.lock` has Symfony 8.x packages that need PHP >= 8.4).
