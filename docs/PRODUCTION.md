# Production deployment requirements

## Release gate

**Do not deploy the currently locked dependency set.** This application uses Laravel 11.57.0, and Laravel's official support schedule lists security fixes through March 12, 2026. That date has passed. `composer audit --locked` currently reports four advisories for `laravel/framework` (including two records for the same CRLF issue). The application has mitigations for some affected surfaces, but those do not replace supported framework dependencies. Upgrade to a supported Laravel release, resolve dependency advisories, and rerun the complete test suite before authorizing production deployment.

## Environment and secrets

- Set `APP_ENV=production`, `APP_DEBUG=false`, a canonical HTTPS `APP_URL`, and a generated, private `APP_KEY`. Keep production `.env` and credentials outside version control; never copy local or demo credentials to production.
- Set `APP_TRUSTED_HOSTS` to the exact public hostnames serving this installation. The application rejects other hosts.
- Use a least-privilege database account, private network access, and encrypted connections where supported. Do not use MySQL `root` in production.
- Configure a real mail transport and sender before enabling password resets. The example uses the `log` mailer, which does not deliver messages and writes reset messages to application logs.
- Configure the database/session/cache/queue stores for the hosting topology. The example uses MySQL-backed session and cache stores; their framework tables are included in the migrations. If jobs are queued, run and supervise a queue worker. Current ticket notifications are synchronous database notifications, not queued mail.
- Keep `storage/` and `bootstrap/cache/` writable by the application process and inaccessible to direct web requests. Ticket attachments use the private local disk. If deploying multiple app instances, configure a shared private disk or ensure authenticated attachment downloads reach the instance holding the file; do not publish the private disk or run `storage:link` for it.
- Serve only over HTTPS. Production session cookies default to encrypted, `HttpOnly`, `SameSite=Lax`, and `Secure`; do not override these settings insecurely. Behind a reverse proxy or load balancer, set `APP_TRUSTED_PROXIES` to only its actual IP/CIDR ranges so client IP throttles and HTTPS detection use trusted forwarded headers. Never use `*` or trust forwarded headers from arbitrary clients.
- Set operational log retention and access controls; logs may contain account identifiers and diagnostic data. Monitor the `/up` health route, database capacity, storage, failed jobs (if used), and backups.

## Release procedure

1. Build and test the exact release artifact in CI, including `composer audit`, `npm audit`, the full PHPUnit suite, and `npm run build`.
2. Provision MySQL 8+, a dedicated restricted DB user, HTTPS, canonical hostnames, mail delivery, private file storage, and backup/restore procedures.
3. Install production PHP dependencies with `composer install --no-dev --optimize-autoloader`; install Node build dependencies in the build environment and publish only the generated `public/build` assets.
4. Set deployment environment variables/secrets, then run `php artisan migrate --force` during a controlled release window followed by `php artisan db:seed --force` to install the required reference data. `DatabaseSeeder` skips demo accounts/tickets when `APP_ENV=production`; verify that value before seeding.
5. Run `php artisan optimize` after environment values and the new release are in place. Rebuild/clear caches as part of each release; do not bake a developer's `.env` or cached config into the artifact.
6. Start the PHP/web processes and any configured queue workers under a process supervisor. Confirm `/up`, sign-in, password-reset mail delivery, authorized attachment download, and backup restoration in the target environment.

The local audit cannot verify a production host, proxy ranges, TLS termination, database grants, mail delivery, monitoring, storage durability, or backup restoration. These are deployment-owner checks.
