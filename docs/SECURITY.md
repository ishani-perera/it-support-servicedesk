# Authentication & Authorization (Phase 03)

This document describes what Phase 03 builds, the rules it enforces, and — just as important — what it does **not** do yet.
Every guarantee below names the test that proves it. A claim without a test is listed under [Limits](#limits-and-decisions-to-revisit).

- [Authentication](#authentication)
- [Roles and route middleware](#roles-and-route-middleware)
- [Ticket access rules](#ticket-access-rules)
- [Policies](#policies)
- [IDOR protection](#idor-protection)
- [User management](#user-management)
- [Password reset](#password-reset)
- [Sessions and CSRF](#sessions-and-csrf)
- [API (Sanctum) foundation](#api-sanctum-foundation)
- [Route surface](#route-surface)
- [How to add a protected resource](#how-to-add-a-protected-resource)
- [Limits and decisions to revisit](#limits-and-decisions-to-revisit)

## Authentication

Built on Laravel's own primitives — the `web` session guard, the `Password` broker, `Hash`, `RateLimiter`, `AuthenticateSession` — with controllers laid out like Laravel Breeze's.

> **Why not `breeze:install`?** Breeze 2.4 does support Laravel 11, but its Blade stack installs **Alpine.js**, downgrades Tailwind 4 → 3, and adds postcss/`@tailwindcss/forms`. That violates the approved stack and breaks the working `npm run build`. Only the server-side pattern was kept; the Blade views are small, framework-free Tailwind pages. `laravel/breeze` is not a dependency.

| Feature | Implementation | Proven by |
|---|---|---|
| Login / logout | `Auth\AuthenticatedSessionController`, `Requests\Auth\LoginRequest` | `AuthenticationTest` |
| Deactivated / soft-deleted accounts cannot log in, and get the *same* generic error as a wrong password | `is_active => true` is part of the credential query | `test_a_deactivated_account_…`, `test_an_unknown_email_gets_the_same_error_…` |
| Brute force | 5 failures per email+IP → lockout, even the correct password is refused | `test_login_is_rate_limited_after_five_failures` |
| Remember me | opt-in checkbox only | `test_remember_me_issues_a_recaller_cookie_only_when_requested` |
| Deactivated while signed in → out on next request | `active` middleware | `test_an_account_deactivated_while_signed_in_…` |
| Passwords | bcrypt via the model's `hashed` cast; policy: min 12, mixed case, number (+ breached-password check in production) | `PasswordSecurityTest` |
| Self-registration | **none** — accounts are created by Admin (later phase) | `test_there_is_no_public_registration_route` |
| E-mail verification | **none** (it relies on signed URLs; see advisories) | `RouteProtectionTest::test_the_expected_security_routes_…` |

## Roles and route middleware

`App\Enums\UserRole` is the only place role strings exist (`test_role_names_come_only_from_the_enum` scans the code). `User` exposes `hasRole()`, `isEmployee()`, `isSupport()`, `isAdmin()`, `isStaff()`.

| Alias | Class | Behaviour |
|---|---|---|
| `role:employee` / `role:support,admin` … | `EnsureUserHasRole` | guest → login (HTML) or JSON 401; wrong role or inactive → **403**; unknown role name → exception (never silently allow/deny); **no implicit hierarchy** (admin does not pass `role:support`) |
| `active` | `EnsureUserIsActive` | signed-in-but-deactivated → session destroyed (web) / 403 (API) |

Middleware priority is set so `active` → `role` run **before** route-model binding. Otherwise a non-admin would get 404 for non-existent user ids and 403 for existing ones and could enumerate users (`IdorTest::test_employee_cannot_reach_other_user_records_…`).

`role:*` is a **coarse** gate. It is never the only check on anything that touches a specific record.

## Ticket access rules

Who can do what (`TicketPolicy`; the full matrix is asserted cell-by-cell in `TicketPolicyTest::test_the_full_ticket_ability_matrix`):

| Ability | Employee | IT Support | Admin |
|---|---|---|---|
| view | own tickets (requester) | all tickets, including tickets assigned to colleagues | all |
| create | yes | yes | yes |
| comment (public) | own tickets | tickets assigned to them + the unassigned queue | all |
| internal note / see internal notes | never | may view notes on all tickets; may add notes only on tickets assigned to them + the unassigned queue | all |
| upload attachment | own tickets | tickets assigned to them + the unassigned queue | all |
| update status / priority / core fields | never | only tickets **assigned to them** | all |
| assign / reassign / unassign | never | claim an unassigned ticket, or hand over one they hold; **not** take one from a colleague | all |
| manage users, roles, reference data | never | never | yes |

Notes:

- Support agents share read access to ticket details, comments (including internal notes), attachments and assignment history. Reassignment does not revoke team read access, but only the current assignee may change status or ticket fields, and assignment actions remain limited to the unassigned queue or tickets the agent currently holds (`test_reassignment_changes_who_can_modify_status_but_not_team_view_access`).
- `before()` in every policy only ever **denies** (inactive users — also covers stale Sanctum tokens). It never grants, because a blanket `admin => true` would also grant abilities that were never defined. Undefined abilities (e.g. deleting a ticket) are denied even for admin.
- `Ticket::scopeVisibleTo($user)` is the query-side twin of `view`. **Use it for every list.** `test_visible_to_scope_matches_the_view_policy_…` fails if the two ever disagree.
- This phase decides *who*, not *when*. Workflow rules (valid status transitions, closed tickets being read-only, who may re-open) are Phase 04.

## Policies

| Policy | Rule in one line |
|---|---|
| `TicketPolicy` | table above |
| `TicketCommentPolicy` | inherits the access of the comment's **real** parent ticket; internal notes are staff-only; no edit/delete yet |
| `TicketAttachmentPolicy` | inherits parent-ticket access; attachments on internal notes are staff-only; `download` separate from `view`; no edit/delete yet |
| `TicketAssignmentPolicy` | assignment history is internal (staff who can see the ticket); append-only — update/delete denied for everyone |
| `UserPolicy` | admin manages users; `updateRole` / `updateStatus` / `delete` are denied on **yourself** (no self-escalation, no admin lock-out); self-service edit of own profile only |
| `Department/TicketCategory/TicketPriority/TicketStatusPolicy` | any active user may read, only Admin may write (`MasterDataPolicy`) |

`RelatedPoliciesTest::test_every_model_has_its_policy_registered` fails if a new model is added without a policy.

## IDOR protection

The id in a URL is attacker-controlled. Defences, all server-side:

1. **Route-model binding loads, the policy decides.** Every controller action calls `$this->authorize()`. Binding never counts as authorization.
2. **Scoped bindings** on nested routes: `/tickets/{ticket}/comments/{comment}` returns 404 if the comment is not on that ticket, and the policy independently re-derives the comment's *real* ticket, so pairing "my ticket" with "their comment id" gives nothing.
3. **Authorization before existence.** The attachment endpoint authorizes *before* checking the file exists, so 403-vs-404 can't be used to probe for files.
4. **Private files.** Attachments live on the private `local` disk and are streamed only by `TicketAttachmentController` (`Content-Disposition: attachment`, `nosniff`, `no-store`). Laravel's built-in `storage/{path}` route for that disk is **disabled** (`config/filesystems.php: serve => false`).
5. **No ids in profile/password endpoints**: they always act on the signed-in user.

Proof (`tests/Feature/Security/IdorTest.php`, over real HTTP): Employee A → Ticket A allowed; → Ticket B forbidden; changing the id in the URL forbidden; walking **every** ticket id returns only own tickets; same for comments (incl. internal notes on one's own ticket) and attachments; plus sweeps over the whole seeded demo dataset (8 employees × 26 tickets, every support agent, admin, every internal note).

## User management

Admin-only, behind `role:admin` **and** `UserPolicy` per field (`UpdateUserRequest`): `role`, `is_active`, `department_id`.

- `role`, `is_active`, `email_verified_at` are not mass-assignable; the controller sets them explicitly after authorization.
- `PATCH /profile` accepts only `name` and `phone` (`$request->validated()`); `role`, `is_active`, `department_id`, `email`, `password` in the payload are ignored (`UserSecurityTest`).
- An admin cannot change their own role or deactivate themselves; because the actor must be an admin to change anyone's role, at least one admin always remains.
- A request that touches any field the actor may not change is rejected **as a whole** (nothing is partially applied).
- A demoted or deactivated user loses access on their next request.

## Password reset

Laravel's password broker (hashed, single-use, expiring tokens in `password_reset_tokens`; 60-minute expiry).

- The response to "send link" is **identical** for unknown, inactive, throttled and valid addresses (no account enumeration). Inactive accounts never get a link.
- A token only works for the e-mail it was issued to; a forged/expired/used token or another user's e-mail all fail with one generic message.
- After a reset the remember-me token is rotated and the password hash changes, which signs out all other sessions. The user is **not** auto-logged-in from the e-mailed link.
- E-mail input: `email:rfc,strict` **plus** an explicit rejection of CR/LF/NUL (header-injection defence in depth — see advisories).
- `POST forgot-password` / `POST reset-password` are throttled (6/min).

Proof: `PasswordResetTest` (19 tests).

## Sessions and CSRF

- Session id is regenerated on login (the framework's `SessionGuard::login()` migrates the session itself; the controller also calls `regenerate()` as defence in depth) and the session is invalidated + CSRF token regenerated on logout.
- `SessionSecurityTest::test_a_pre_login_session_id_cannot_be_reused_…` runs against the **database** session driver: the planted id is dead after login, and the authenticated id is dead after logout.
- `AuthenticateSession` is in the `web` group: changing a password invalidates every other session; "change my password" keeps only the current one.
- Session cookie is `HttpOnly; SameSite=Lax` (verified on a live server). **Production must set `SESSION_SECURE_COOKIE=true`** (HTTPS only).
- Session ids never appear in URLs.
- Every state-changing web route is in the `web` group (CSRF verified, empty except-list); a real-token / no-token / forged-token check is exercised with the middleware's test bypass switched off; every form renders `@csrf`.

## API (Sanctum) foundation

No API endpoints exist (`routes/api.php` is empty by design). Verified with throw-away routes in `SanctumFoundationTest`:

- `auth:sanctum` guards routes; guests get **JSON 401** (even without an `Accept` header), not a redirect.
- Invalid/revoked tokens → 401; tokens are stored hashed (sha256).
- Deactivated user's token → 403, soft-deleted user's token → 401 (`active` runs before binding).
- **A valid token never bypasses Policies** (Employee A's token → Ticket B = 403); `role:*` works with tokens.

## Route surface

16 application routes + `GET /up` (framework health check) + `GET /sanctum/csrf-cookie` (Sanctum package route).
The ticket/comment/attachment/admin routes return small JSON documents: they are **authorization boundary endpoints**, not UI and not the REST API. Phase 04 and the UI phases replace the bodies and keep the `authorize()` calls.

`RouteProtectionTest` fails if any route is neither on the public allowlist nor behind `auth` + `active`, if an admin route lacks `role:admin`, if a record-parameter route is public, if a nested route lacks scoped bindings, or if a registration / e-mail-verification / `storage/` route appears.

## How to add a protected resource

1. Route inside `['auth', 'active']` (API: `['auth:sanctum', 'active']`); nested → `->scopeBindings()`; admin-only → add `role:admin`.
2. Policy with explicit abilities; `before()` denies inactive users only.
3. Controller: bind, then `$this->authorize('ability', $model)` **before** doing anything else (including file/DB existence checks).
4. Lists: query through a `visibleTo`-style scope that mirrors the policy, and add a parity test.
5. Write the IDOR test first: owner allowed, another user's record forbidden, id walked over all records.
6. Mutations: validate with a FormRequest and use `$request->validated()`; never `$request->all()`.

## Limits and decisions to revisit

Genuine open points — none are hidden by a test:

1. **403 vs 404 for other people's tickets.** The requirement says "forbidden", so existing-but-not-yours is 403 and a missing id is 404; an authenticated user can therefore learn that a ticket id *exists*. Ticket numbers are sequential, so this is low-value information, but if it matters switch the policies to `Response::denyAsNotFound()`.
2. **Closed/resolved tickets.** Policies do not yet block comments/uploads on closed tickets — that is workflow (Phase 04).
3. **Sanctum token lifetime.** `config/sanctum.php` has `expiration => null` (tokens never expire). Decide the lifetime and abilities when the API phase issues tokens.
4. **Login lockout is per email+IP.** It stops guessing against one account from one address; it does not stop a distributed attack, and an attacker can lock a victim out for the lockout window. Acceptable for an internal tool; revisit with an IP-wide limiter if exposed publicly.
5. **No MFA, no e-mail verification, no account-creation UI.** Admin-created accounts and an invite/forced-password-change flow belong to a later phase.
6. **Mail.** `MAIL_MAILER=log` in `.env.example`: reset links are only logged until a real mailer is configured.
7. **Laravel 11 is end-of-life** and `composer audit` reports 4 unpatched framework advisories (fixed in ≥ 12.69 / ≥ 13.30). Relevance to this code: the `email` validation-rule CRLF issue is mitigated for the only place addresses reach mail headers (`EmailField`); signed URLs are not used anywhere (no e-mail verification, no signed downloads) and the framework's signed `storage/{path}` route is disabled; debug-page XSS only applies with `APP_DEBUG=true`. Upgrade before production.
8. **Test-harness note.** The in-process test client keeps one session store and auth guard for a whole test. Tests that switch users use `actAs()` (resets both) — otherwise `AuthenticateSession` correctly logs the second user out because the first user's password hash is still in the session. Real browsers are unaffected; the live-server check confirmed this.
