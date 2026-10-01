# IT Support ServiceDesk — Project Guidelines

Instructions for any developer or coding agent working in this repository.

## Approved technology stack (strict)

| Layer | Technology |
|---|---|
| Backend | PHP, **Laravel 11.x** |
| Database | MySQL 8 |
| Frontend | Laravel Blade + Tailwind CSS + **vanilla JavaScript** |
| API | Laravel REST API (Laravel Sanctum) |
| Tests | PHPUnit |
| Tooling | Git/GitHub, Postman; Node/npm **only** to build assets (Vite + Tailwind) |
| Deployment | Linux, Nginx, MySQL (Docker optional) |

**Do NOT introduce** React, Vue, Angular, Next.js, Nuxt, Inertia, Livewire, Bootstrap, Material UI,
a Node.js backend, Express, Python/Django/Flask, or any other framework.
**Do NOT install** AI/agent scaffolding packages (e.g. Laravel Boost) — they are not part of the approved stack.

## Conventions

- Roles, ticket statuses and priorities are defined ONLY in `app/Enums` (`UserRole`, `TicketStatusSlug`,
  `TicketPriorityLevel`). Never hardcode those strings/numbers elsewhere.
- Every Eloquent model has an explicit `$fillable`. Never use `$guarded = []`.
  Privileged/system columns (`users.role`, `users.is_active`, `users.email_verified_at`,
  `tickets.ticket_number`, `tickets.resolved_at`, `tickets.closed_at`) are NOT mass-assignable.
- Schema changes go through migrations (reversible, with proper foreign keys and indexes).
  Never edit a migration that has already run in a shared environment.
- Business rules live in `app/Services` (and later policies); controllers stay thin.
- Never run destructive commands (`migrate:fresh`, `db:wipe`, tests) against a non-disposable database.
  Tests use `it_support_servicedesk_testing`; `tests/TestCase.php` refuses to run against any database
  whose name does not end in `_testing`.
- Demo data is **development only** and must never be seeded in production.
- Never commit `.env`, credentials, or secrets.
- Authorization (Phase 03): every route that touches a record is behind `['auth', 'active']`, binds the
  record, then calls `$this->authorize(...)` — route-model binding is NOT authorization and `role:*`
  middleware is only a coarse gate. Nested resources use `->scopeBindings()`. Lists use `Ticket::visibleTo($user)`.
  Policies only ever DENY in `before()` (inactive users); abilities are granted explicitly. Never add a
  public route without adding it to the allowlist in `tests/Feature/Security/RouteProtectionTest.php`.
  Blade `@can` hides UI only. See `docs/SECURITY.md`.

## Common commands

```bash
php artisan migrate:fresh --seed   # DEV database only
php artisan test
npm run build
vendor/bin/pint                    # code style
```
