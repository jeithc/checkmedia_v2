# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CheckMedia V2 is an Out-of-Home (OOH) advertising audit management system. Field auditors inspect advertising spaces and record compliance data; admins manage spaces, review audits, generate reports, and track maintenance.

**Stack**: Laravel 12 + Orchid Platform 14 (admin panel) + Livewire 3 (interactive forms) + Tailwind CSS 4 + Vite

## Common Commands

```bash
# Setup (first time)
composer run-script setup

# Development (starts server, queue, logs, Vite concurrently)
composer run-script dev

# Run all tests (Pest PHP)
composer run-script test

# Run a single test file
php artisan test tests/Unit/AuditTest.php

# Run a specific test by name
php artisan test --filter=test_it_calculates_calendar_week_correctly

# Format code
composer exec pint

# Build frontend for production
npm run build
```

## Architecture

### Two interfaces, one app

- **Orchid admin panel** (`/admin/*` via `routes/platform.php`): Screens for space management, audit review, report building, maintenance tracking. Uses `Orchid\Screen\Screen` subclasses with `Layout::view()` for custom Blade templates.
- **Livewire auditor interface** (`/audit` via `routes/web.php`): Field-facing form (`AuditForm`) for submitting audits. Also powers `AuditReportBuilder` and `AdminDashboard`.

### Routing

- `/` redirects based on role: auditors → `/audit`, admins → `/admin`
- `/audit-action/{audit}/...` → `AuditActionController` handles state transitions (third-party marking, revision, maintenance requests, close with PDF)

### Database connections

- **Default**: SQLite (dev) or MySQL/MariaDB (production) — standard Laravel connection
- **`advisual`**: SQL Server connection for syncing advertising space data from external system (`AdvisualSyncService`)

### Key domain rules

- **Audit uniqueness**: Enforced on `[advertising_space_id, year, week, audit_type]`
- **Week calculation**: `Audit::getCalendarYearAndWeek()` uses `ceil(dayOfYear / 7)` capped at 52 — do NOT use Carbon's ISO week
- **Activity logging**: All audit state changes must call `SpaceActivityLog::log()` with named parameters and metadata
- **Permissions**: Registered in `PlatformProvider::permissions()`, checked via `$user->hasAccess('permission.name')`

### Livewire serialization pattern

Livewire components store only scalar values and IDs as properties. Models are loaded via `#[Computed]` properties to avoid serialization issues. Follow this pattern in all Livewire components.

### Orchid screen pattern

Screens define `query()` (data fetching), `layout()` (UI composition), and optionally `permission()`. Custom HTML uses `Layout::view('orchid.path.to.blade')` — the query array keys become Blade variables.

## Testing

- **Framework**: Pest PHP with PHPUnit under the hood
- **Test DB**: SQLite in-memory (configured in `phpunit.xml`)
- **Patterns**: `RefreshDatabase` trait, `Storage::fake('public')` for file uploads, `Livewire::test()` for component tests, Mockery for service mocks

## Code Style

- Extract CSS/JS into separate files or `@push`/`@section` blocks — avoid large inline `<style>`/`<script>` in Blade templates
- No strict types enforced; follow existing file conventions
- PSR-4 autoloading under `App\` namespace
- Use model constants for enum-like values (e.g., `Audit::TYPE_GENERAL`, `Maintenance::STATUS_CLOSED`)
