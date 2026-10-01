# IT Support ServiceDesk

Enterprise-style IT support ticket management system.

| | |
|---|---|
| **Framework** | Laravel **11.x** (currently 11.57) |
| **PHP** | 8.2 – 8.4 (developed/tested on 8.4) |
| **Database** | MySQL **8.0+** (developed/tested on 8.4) |
| **Frontend** | Blade + Tailwind CSS 4 + vanilla JavaScript (Vite is used only to build assets) |
| **API** | Laravel REST API with Sanctum *(authentication foundation only — no endpoints yet)* |
| **Tests** | PHPUnit 11 |

> **Status: Phase 03 — authentication, role-based access control, policies and IDOR protection.**
> No dashboards, ticket workflow UI or REST API endpoints yet (Phase 04+).
> Authentication/authorization design, rules and test map: [`docs/SECURITY.md`](docs/SECURITY.md).
> Schema, indexes, delete rules and soft-delete decisions: [`docs/DATABASE.md`](docs/DATABASE.md).
> Project/stack rules for contributors and agents: [`AGENTS.md`](AGENTS.md).

## Requirements

* PHP 8.2+ with `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`
* Composer 2
* MySQL 8.0+ (**real MySQL** — the schema uses a generated column and `ENUM`; MariaDB is not the supported target)
* Node 20+ and npm — **only** to build Tailwind/Vite assets

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Two databases: development, and a separate one used ONLY by the test suite.
mysql -u root -e "CREATE DATABASE it_support_servicedesk         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                  CREATE DATABASE it_support_servicedesk_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Adjust DB_USERNAME / DB_PASSWORD in .env if needed (never commit .env)

php artisan migrate --seed      # schema + master data + DEMO data (non-production only)

npm install
npm run build                   # or `npm run dev` while developing
```

### Database commands

| Command | Effect |
|---|---|
| `php artisan migrate` | Apply migrations |
| `php artisan migrate:fresh --seed` | **Drops every table** and rebuilds — development database only |
| `php artisan db:seed` | Safe to re-run: seeders are idempotent (no duplicates, existing rows and changed passwords are left alone) |

## Seeded data

**Master data (all environments):** 7 departments, 9 ticket categories, 4 priorities (with SLA targets), 6 statuses.

**Demo data (non-production only):** 12 users, 26 tickets, 26 assignment rows (5 tickets with reassignment history, 5 unassigned), 70 comments (15 internal IT notes). Tickets cover every status, priority and category and 6 departments. No attachment records or files are created.
In `APP_ENV=production` the demo seeders are skipped, and `DemoUserSeeder` refuses to run.

### Demo credentials — ⚠️ DEVELOPMENT ONLY

All demo accounts share one **known, public** password. Never use these accounts outside a local/dev database.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `ServiceDesk@2026` |
| IT Support | `support1@example.com` … `support3@example.com` | `ServiceDesk@2026` |
| Employee | `employee1@example.com` … `employee8@example.com` | `ServiceDesk@2026` |

Passwords are stored only as hashes (Laravel's hasher). The addresses use the reserved `example.com` domain.
Sign in at `/login`.

## Tests

```bash
php artisan test
```

Tests run on the separate `it_support_servicedesk_testing` MySQL database. `tests/TestCase.php` **refuses to boot** against any database whose name does not end in `_testing`, because `RefreshDatabase` drops every table.

## Code style

```bash
vendor/bin/pint
```

## Architecture notes

* Roles, statuses and priorities are defined once, in `app/Enums` (`UserRole`, `TicketStatusSlug`, `TicketPriorityLevel`).
* Every model has an explicit `$fillable`; privileged/system columns (`role`, `is_active`, `ticket_number`, `resolved_at`, `closed_at`, …) are not mass-assignable.
* `app/Services/TicketAssignmentService` is the only supported way to assign/reassign: it closes the previous assignment and opens a new one in a locked transaction, so history is never overwritten.
* Non-production environments enable `Model::preventLazyLoading()`, so N+1 queries fail loudly. Use `Ticket::withListRelations()` for list queries.
* Authentication uses Laravel's session guard and password broker; authorization is done by Policies (`app/Policies`) plus the `role` / `active` route middleware. Details: [`docs/SECURITY.md`](docs/SECURITY.md).
* Ticket-number generation, the ticket workflow, dashboards/UI, uploads and the REST API are **not** implemented yet.

## Security notice — Laravel 11 end of life

The project is pinned to Laravel 11 as specified. Laravel 11 no longer receives security fixes, and `composer audit` reports advisories that are fixed only in Laravel ≥ 12.60 / ≥ 13.10. See "Known security advisories" in [`docs/DATABASE.md`](docs/DATABASE.md#known-security-advisories-laravel-11) and plan an upgrade before any production deployment.
