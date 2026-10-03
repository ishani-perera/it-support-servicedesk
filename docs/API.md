# ServiceDesk REST API (Phase 10)

All API routes are under `/api`. Send `Accept: application/json`; for protected routes send `Authorization: Bearer <access_token>`. Login returns a Sanctum personal access token once. The stored token is hashed, expires after 480 minutes by default (`SANCTUM_TOKEN_EXPIRATION`), and `POST /api/auth/logout` revokes only the current bearer token. Authenticated API requests are limited to 60/minute per user; mutations are additionally limited to 20/minute per user. Login has an independent 10/minute IP throttle and existing 5-attempt email/IP lockout.

## Authentication

| Method | Path | Body / result |
|---|---|---|
| POST | `/api/auth/login` | `email`, `password`; returns `data.access_token`, `data.token_type`, `data.expires_at`, and safe `data.user` |
| GET | `/api/auth/user` | Current safe user resource |
| POST | `/api/auth/logout` | Revoke current bearer token |

Login also uses the existing per-email-and-IP lockout, with an additional route throttle. Inactive accounts receive the same credential failure as invalid credentials. Password change/reset, role change, deactivation, and logout revoke existing personal access tokens. Never store the returned token in a public client or commit it.

## Tickets

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/tickets` | Paginated visible tickets |
| POST | `/api/tickets` | Create ticket (may include `file`) |
| GET | `/api/tickets/{ticket}` | View authorized ticket and SLA |
| PATCH | `/api/tickets/{ticket}` | Update allowed fields through existing policy/service |
| PATCH | `/api/tickets/{ticket}/status` | Body: `status` canonical slug |
| POST | `/api/tickets/{ticket}/assignments` | Body: `assigned_to`, optional `note` |
| DELETE | `/api/tickets/{ticket}/assignments/current` | Unassign when authorized |
| GET / POST | `/api/tickets/{ticket}/comments` | List visible comments / create a comment (`body`, optional `is_internal`) |
| GET / POST | `/api/tickets/{ticket}/attachments` | List authorized attachments / upload `file`, optional `comment_id` |
| GET | `/api/tickets/{ticket}/attachments/{attachment}` | Secure authorized file download |
| GET | `/api/lookups` | Active departments, categories, priorities, statuses |

Ticket listing supports `search`, `status`, `priority_id`, `category_id`, `department_id`, `assigned_to`, `requester_id`, `from`, `to`, `ticket_number`, `assignment`, `sort`, and `per_page` (maximum 100). Responses use Laravel JSON resource pagination (`data`, `links`, `meta`). Employees see only their own tickets and public comments; IT Support visibility and modification rules continue to come from existing policies. Admin does not bypass undefined policy abilities.

## Notifications

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/notifications` | Own notifications for tickets still visible to the user |
| GET | `/api/notifications/unread-count` | Authorized unread count |
| PATCH | `/api/notifications/{notification}/read` | Mark one visible owned notification read |
| PATCH | `/api/notifications/read-all` | Mark visible owned notifications read |

The standard validation response is HTTP 422 with `message` and field-level `errors`; authentication, authorization, missing records, and throttling use their corresponding HTTP status codes. Private attachment resources contain a secure API download endpoint and never include filesystem paths.
