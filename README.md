# IT Support ServiceDesk

An internal IT service desk for employee requests and support operations. The application includes employee ticket creation and tracking, an IT Support queue and Kanban board, admin user and reference-data management, reports, notifications, SLA targets, and a token-authenticated REST API.

| | |
|---|---|
| **Framework** | Laravel 11.x (locked dependency: 11.57.0) |
| **PHP** | 8.2+ |
| **Database** | MySQL 8.0+ (InnoDB) |
| **Frontend** | Blade, Tailwind CSS 4, vanilla JavaScript, Vite |
| **API authentication** | Laravel Sanctum personal access tokens |
| **Tests** | PHPUnit 11 |

> **Production status: not ready.** Laravel 11 security support ended on March 12, 2026. The locked framework also currently fails `composer audit` with four advisories. Upgrade to a supported Laravel release and rerun the audit and test suite before production. See [production deployment requirements](docs/PRODUCTION.md) and [security notes](docs/SECURITY.md).

## Requirements

- PHP 8.2+ and the Laravel-required extensions, including `pdo_mysql`, `mbstring`, `openssl`, `xml`, `curl`, `fileinfo`, and `bcmath`
- Composer 2
- MySQL 8.0+
- Node.js 20+ and npm to build frontend assets

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Keep the test database separate; tests refuse database names without _testing.
mysql -u root -e "CREATE DATABASE it_support_servicedesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                  CREATE DATABASE it_support_servicedesk_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Set DB_USERNAME and DB_PASSWORD in the local .env for your MySQL account.
php artisan migrate --seed

npm install
npm run build
```

The seeders install reference data in all environments and demo users/tickets only outside production. Demo credentials are public development fixtures; never use them in a shared environment.

## Verification

```bash
php artisan test
npm run build
php artisan route:list
vendor/bin/pint --test
git diff --check
```

Tests use `it_support_servicedesk_testing`. `tests/TestCase.php` refuses to run against a database whose name does not end in `_testing`, because `RefreshDatabase` drops and rebuilds tables.

## Architecture and security

- Authorization lives in explicit policies, active-account checks, and role middleware. Employee ticket visibility is requester-scoped; support read access and modification rights are separate.
- FormRequests validate write inputs; models define explicit fillable fields. Ticket assignment and workflow changes go through services.
- Attachments are stored on the private local disk and streamed only after ticket-level authorization.
- The API uses expiring Sanctum bearer tokens and route throttles; API operations use the same policies and services as the web application.
- Outside production, lazy loading is prevented to surface N+1 queries during development and tests.

Further details: [API](docs/API.md), [security](docs/SECURITY.md), [database](docs/DATABASE.md), [production deployment](docs/PRODUCTION.md), [contributor rules](AGENTS.md).
