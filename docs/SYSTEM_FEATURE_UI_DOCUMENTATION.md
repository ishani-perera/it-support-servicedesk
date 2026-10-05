# IT Support ServiceDesk
## System Feature and User Interface Guide

**Document version:** 1.0  
**Prepared:** 6 October 2026  
**Source reviewed:** Laravel project source, web/API route definitions, Blade views, controllers, request validation, policies/services, and the project README and architecture notes.

> **Source-file note:** No uploaded ZIP archive or drafted PDF was present in the available workspace when this guide was prepared. This document is based on the checked-out `it-support-servicedesk` project. The missing draft was therefore not available for comparison.

---

## 1. Document Purpose and Scope

This guide describes the ServiceDesk features and user interface that are implemented in the reviewed project. It is organized by the three implemented account roles and covers each role’s dashboard, reachable application pages, key interactions, and displayed data. It also records the shared authentication, notification, ticket, and API surfaces needed to understand the complete application.

### 1.1 Role naming

The application defines three roles in its `UserRole` enum:

| Role in this guide | Role label shown by the application | Role value |
|---|---|---|
| Administrator | Administrator | `admin` |
| Employee | Employee | `employee` |
| Service Provider / Service Person | IT Support | `support` |

“Service Provider” and “Service Person” are understood here to mean the application’s **IT Support** role. There is no separate service-provider role in the code.

### 1.2 Technology and application structure

- **Backend:** PHP 8.2+ and Laravel 11
- **UI:** Laravel Blade, Tailwind CSS 4, and vanilla JavaScript
- **Database:** MySQL 8+ / InnoDB
- **API authentication:** Laravel Sanctum bearer tokens
- **Automated tests:** PHPUnit 11
- **Ticket workflow:** Open → Assigned → In Progress → Waiting for User → Resolved → Closed

The browser UI uses server-rendered Blade pages. The REST API is a separate `/api` route surface and returns JSON.

### 1.3 Interpretation of buttons and actions

Buttons and links below describe the action wired by the current application. Record changes are subject to server-side validation, authentication, role middleware, and the applicable authorization policy. A control may not appear for a user who lacks the required policy ability.

---

## 2. Shared Application UI and Common Pages

### 2.1 Authenticated application shell

Every signed-in role uses the shared responsive application shell.

**Primary purpose:** Provide role-specific navigation, account identity, notifications, and a consistent page frame.

**Features and data displayed:**

- Role-aware sidebar with active-page state.
- Current user’s initials, name, role, and email.
- Header title for the current page.
- Notification bell with unread count and recent notification menu.
- Responsive mobile sidebar with backdrop and open/close controls.
- Shared success and validation-error feedback areas.
- Skip-to-content accessibility link.

**Buttons and interactions:**

- **Sidebar navigation links:** Open the dashboard and pages available to the signed-in role.
- **Mobile navigation button:** Opens the sidebar on small screens.
- **Sidebar backdrop / close control:** Closes mobile navigation.
- **Notification bell:** Opens a recent-notification menu.
- **Sign out icon/button:** Ends the current web session and returns to the public root page.
- **Dismiss status button:** Hides the current success message for the page view.

**Important scope note:** A self-service profile-edit screen is not implemented. A profile update endpoint exists, but the reviewed UI contains no profile form. A password-change endpoint also exists without a corresponding authenticated password-change page.

### 2.2 Notifications page

**Accessible to:** Administrator, Employee, and IT Support.

**Primary purpose:** Show the signed-in user’s ticket notifications and allow them to open related tickets.

**Features and functionalities:**

- Paginated notification list, 20 items per page.
- Unread notifications are visually distinguished from read notifications.
- Notification data includes a title, message, date/time, and related ticket reference when available.
- The notification query is limited to notifications the user owns and tickets they may view.

**Buttons and interactions:**

- **Mark all read:** Marks the current user’s visible unread notifications as read.
- **Mark read:** Marks one notification as read.
- **Ticket-number / Open ticket link:** Marks that notification read and opens the related ticket, after ticket authorization succeeds.
- **Pagination controls:** Move between notification pages.
- **Notification bell menu – View all notifications:** Opens this page.

**Data displayed:** Notification title, message, read/unread indicator, created date/time, and related ticket number or “Open ticket” link.

### 2.3 Authentication pages

These pages are available to guests. There is no public self-registration flow; administrators provision accounts.

#### Sign-in page

**Primary purpose:** Authenticate an existing active user.

**Features:** Work-email and password sign-in, optional remember-me, password-recovery navigation, validation feedback, and rate-limited login attempts.

**Buttons and interactions:**

- **Sign in:** Submits email and password. On success, creates a fresh authenticated session and directs the user to their intended page or role dashboard.
- **Remember me:** Requests a persistent recaller cookie when selected.
- **Forgot your password?:** Opens the reset-link request page.

**Data displayed:** Email and password fields, optional remember-me checkbox, validation errors, and sign-in guidance. The form does not expose account-registration options.

#### Forgot-password page

**Primary purpose:** Request a password-reset link for an active account.

**Buttons and interactions:**

- **Email reset link:** Submits the work email to the password broker. The confirmation is intentionally generic so the page does not disclose whether an account exists.
- **Back to sign in:** Returns to the sign-in page.

**Data displayed:** Work-email field, validation feedback, and generic request confirmation.

#### Reset-password page

**Primary purpose:** Set a new password using a valid reset token.

**Buttons and interactions:**

- **Reset password:** Submits the token, email, new password, and confirmation. A successful reset returns the user to sign-in; it does not automatically sign them in.

**Data displayed:** Email, new password, confirmation, password guidance, and validation or invalid/expired-link feedback.

---

# 3. Administrator Dashboard and Accessible Pages

Administrators have navigation to Dashboard, Tickets, Users & technicians, Departments, Categories, Priorities & SLA, Workflow statuses, and Reports & analytics. They also use shared Notifications and can sign out.

## 3.1 Administrator dashboard

**Primary purpose:** Provide a current, database-backed overview of account and ticket activity and shortcuts to administration tasks.

**Features and functionalities:**

- Summary statistics for total users, active employees, active IT Support users, ticket counts by status, and high-priority tickets.
- Workflow status distribution visualization.
- Recent tickets and recent account activity.
- Read-only workflow-status overview.
- Priority and SLA-level overview.

**Buttons and interactions:**

- **Reports & analytics:** Opens the reports page.
- **Status statistic/card:** Opens the ticket list filtered to that status where the link is available.
- **All tickets / recent ticket link:** Opens the ticket list or a specific ticket, subject to ticket policy authorization.
- **Manage users:** Opens the user directory.
- **View workflow setup:** Opens the read-only statuses page.
- **Manage priority targets:** Opens priority settings.
- **Sidebar links:** Open each available administrator area.

**Data displayed:** Account totals, active-role totals, status counts, high-priority count, recent ticket number/title/requester/department/status/date, recent user name/role/department/update time, configured statuses, and configured priorities.

## 3.2 Tickets page (administrator list)

**Primary purpose:** Search and review organization-wide tickets using the shared ticket index.

**Features and functionalities:**

- Search by ticket number, title, or requester.
- Filter by status, priority, and category.
- Sort by newest, oldest, recently updated, or highest priority.
- Paginated results that retain query-string filters.
- Empty-state message and clear-filter path.

**Buttons and interactions:**

- **Search field:** Enter a ticket identifier, title, or requester term.
- **Status / Priority / Category dropdowns:** Select one optional filter.
- **Sort by dropdown:** Select result order.
- **Apply filters:** Submits selected filters using GET.
- **Clear:** Returns to the unfiltered ticket list.
- **Open ticket:** Opens the ticket detail page if the current administrator is authorized to view it.
- **Pagination controls:** Move between result pages while retaining active filters.

**Data displayed:** Ticket number and title, requester, category, priority badge, status badge, and last-updated date.

## 3.3 Ticket detail page

**Primary purpose:** Show the full ticket and its conversation. The page uses the shared staff ticket-detail view; controls depend on the current user’s policy abilities.

**Features and functionalities:**

- Ticket description, resolution when available, status, priority, category, requester, department, and current assignee.
- SLA response and resolution target information.
- Conversation timeline with public replies and internal notes shown according to authorization.
- Attachment metadata and protected download links.
- Where authorized, status transitions, resolution entry, assignment, reassignment, and unassignment.

**Buttons and interactions:**

- **Support queue / Back to tickets:** Returns to the ticket index.
- **Send message:** Submits a comment/reply.
- **Add as an internal note:** Marks a staff comment internal where the user is authorized; employees do not receive the internal-note control.
- **Attachment download:** Requests a private file after ticket and attachment authorization.
- **Upload file:** Uploads an allowed ticket attachment where authorized.
- **Save status:** Applies one of the valid next status transitions. Selecting Resolved requires a solution/resolution description.
- **Assign to me:** Assigns an unassigned ticket to the current support user when permitted.
- **Reassign ticket:** Changes the current assignee using the selected active staff account.
- **Return to unassigned queue:** Ends the current assignment where authorized.

**Data displayed:** Ticket number, title, description, requester, submitted/updated timestamps, status, priority, category, department, current assignee, resolution, SLA state/deadlines, assignment history, comments, internal/public visibility labels, and attachments.

## 3.4 Users & technicians page (directory)

**Primary purpose:** Find and manage user accounts, including Employee, IT Support, and Administrator accounts.

**Features and functionalities:**

- Search by name, email, or employee ID.
- Filter by role, department, and active/inactive state.
- Sort by name, email, role, or date added.
- Paginated user directory.
- User-detail navigation and edit actions.

**Buttons and interactions:**

- **Create user:** Opens the account-creation form.
- **Search / Role / Department / Account / Sort by:** Provide optional directory criteria.
- **Apply filters:** Submits the selected criteria.
- **Clear:** Returns to the default directory.
- **User name:** Opens that account’s detail page.
- **Edit:** Opens the account-edit form.
- **Pagination controls:** Move between result pages.

**Data displayed:** Name, email, employee ID when present, role, department, account status, and creation date.

## 3.5 Create user / Edit user page

**Primary purpose:** Provision an account or update an existing account’s identity, role, and department information.

**Features and functionalities:**

- Name, email, employee ID, phone, role, and department fields.
- New-account form includes temporary password and password confirmation.
- Employee and IT Support roles require an active department.
- Edit form does not display or reveal the current password.
- An administrator cannot change their own role through the edit form.

**Buttons and interactions:**

- **Create account:** Validates and creates the new account, then opens its user detail page.
- **Save changes:** Validates and saves the changed account fields.
- **Cancel / Back to users:** Returns to the directory when creating; when editing, returns to the user detail page.

**Data displayed/entered:** Full name, email, employee ID, phone, role, department, and (creation only) temporary password and confirmation. Field-level errors are shown after invalid submissions.

## 3.6 User detail page

**Primary purpose:** Review one account and manage its active status.

**Buttons and interactions:**

- **Back to users:** Returns to the directory.
- **Edit user:** Opens the edit form.
- **Deactivate account / Activate account:** Changes active status where permitted. Administrators cannot change their own active status.

**Data displayed:** Initials, name, email, role, department, employee ID, phone, creation time, last profile-update time, and active/inactive status.

## 3.7 Departments and Categories pages

Both reference-data areas use the shared master-data list and form templates.

**Primary purpose:** Maintain departments and ticket categories used in ticket creation and classification.

**Features and functionalities:**

- Search by name.
- View descriptions, active state, and related record counts.
- Create records and edit existing records.
- Existing inactive/soft-deleted records remain available to historical records; records are not destructively removed through these pages.

**Buttons and interactions:**

- **Add department / Add category:** Opens the corresponding create form.
- **Search:** Applies the entered name search.
- **Clear:** Removes the search query.
- **Edit:** Opens that record’s form.
- **Pagination controls:** Move through the result list.

**Data displayed:** Name, description, related users/tickets for departments or ticket count for categories, active/inactive state, and an edit action.

## 3.8 Create/Edit Department or Category form

**Primary purpose:** Add or update a department or ticket category.

**Buttons and interactions:**

- **Save:** Validates and creates or updates the record.
- **Cancel / Back to departments or categories:** Returns to the relevant list.
- **Active checkbox (edit only):** Controls availability in new ticket forms without removing historical references.

**Data displayed/entered:** Name, description, and (edit only) active availability. Validation errors are displayed by field.

## 3.9 Priority settings page

**Primary purpose:** Review priority levels and their SLA targets.

**Features and functionalities:**

- Displays the configured priority ordering, visual color, response target, resolution target, ticket count, and active state.
- Priority names and numeric levels are fixed by application design; the page offers management links rather than deletion or level renumbering.

**Buttons and interactions:**

- **Manage:** Opens the selected priority’s edit page.
- **Pagination controls:** Move through priority records.

**Data displayed:** Level, priority name/color, response and resolution minutes, associated ticket count, and active/inactive state.

## 3.10 Edit priority and SLA page

**Primary purpose:** Update the descriptive and operational target settings for a priority level.

**Buttons and interactions:**

- **Save priority settings:** Saves the description, badge color, response target, resolution target, and active state.
- **Cancel / Back to priorities:** Returns to the priority list.

**Data displayed/entered:** Priority name/level context, description, color input, response target in minutes, resolution target in minutes, and active checkbox. Names and numeric levels remain fixed.

## 3.11 Workflow statuses page

**Primary purpose:** Show the configured workflow sequence without allowing changes that could bypass transition rules.

**Features:** Read-only status configuration. Status transitions are enforced by the workflow service.

**Buttons and interactions:** Pagination controls only when the list spans multiple pages. There are no status-edit or status-create controls on this page.

**Data displayed:** Order, status name, canonical slug, ticket count, and active/inactive availability.

## 3.12 Reports & analytics page

**Primary purpose:** Review aggregated ticket operations and SLA performance for an optional creation-date range.

**Features and functionalities:**

- Filter report data using inclusive Created from / Created to dates.
- SLA totals, compliance percentage, overdue counts, overdue-by-priority and overdue-by-current-agent summaries.
- Ticket charts/bars by status, priority, category, department, current agent, and creation date over time.
- Open high-priority ticket summary.
- Recently resolved or closed ticket summary.
- CSV export excludes requester email and ticket description; CSV formula-leading values are escaped.

**Buttons and interactions:**

- **Apply dates:** Refreshes report data for the selected date range.
- **Clear:** Removes the date range.
- **Download CSV:** Exports matching ticket rows with ticket number, status, priority, category, department, current support agent, created time, resolved time, and closed time.

**Data displayed:** Aggregated counts/charts and SLA metrics, ticket numbers/titles/statuses/priorities/departments in the summary lists, and completion timestamps. Report pages do not show requester contact details or ticket descriptions.

---

# 4. Employee Dashboard and Accessible Pages

Employees can access Dashboard, My tickets, and Create a ticket. They also have the shared notification menu/page and sign-out control.

## 4.1 Employee dashboard

**Primary purpose:** Let an employee create a request and understand the current state of their own tickets.

**Features and functionalities:**

- Welcome area and create-ticket shortcut.
- Total ticket count and status counts based only on the signed-in employee’s visible tickets.
- Status visualization and recent-ticket list (up to six records).
- Recent tickets are ordered by last update.
- Responsive table/card presentation.

**Buttons and interactions:**

- **Create new ticket:** Opens the ticket creation form.
- **Status statistic/card:** Opens My tickets with that status selected where applicable.
- **View all tickets:** Opens My tickets.
- **Open ticket:** Opens the selected ticket detail.

**Data displayed:** Counts by workflow status and recent ticket title, ticket number, category, priority, status, and last-update date.

## 4.2 My tickets page

**Primary purpose:** Search and track the employee’s own support requests.

**Features and functionalities:** Search, status/priority/category filters, sorting, pagination, and a contextual empty state. Ticket visibility is requester-scoped by authorization and query rules.

**Buttons and interactions:**

- **Create a ticket:** Opens the creation form.
- **Search tickets:** Filters by ticket number or title (the backend search also supports related requester matching where applicable).
- **Status / Priority / Category:** Select optional filters.
- **Sort by:** Select newest, oldest, or recently updated.
- **Apply filters:** Submits the GET form.
- **Clear / Clear filters:** Returns to the unfiltered employee ticket list.
- **Ticket link / Open ticket:** Opens ticket details.
- **Pagination controls:** Move through results while retaining filters.

**Data displayed:** Ticket number/title, category, priority, current status, creation time, and last update. On mobile, list entries are presented as compact cards.

## 4.3 Create a ticket page

**Primary purpose:** Submit a new IT support request.

**Features and functionalities:**

- Required title and detailed description.
- Required active category and priority selections.
- Optional single screenshot/document attachment; supported extensions include PDF, TXT, common image formats, and Office documents, up to 10 MB.
- Drag-and-drop or click-to-select file area, selected-file name/size display, and remove-file action.
- Validation feedback and submission/loading state.
- The requester’s department is used for the ticket when available; users without a department use the permitted department selection path.

**Buttons and interactions:**

- **Back to my tickets:** Returns to the ticket list.
- **File upload area:** Opens a file picker or accepts a dropped file.
- **Remove:** Removes the selected file before submission.
- **Cancel:** Leaves the form and returns to My tickets without creating a ticket.
- **Submit ticket:** Validates and creates the ticket; if an attachment was selected, stores it securely. On success, redirects to the new ticket’s detail page.

**Data displayed/entered:** Title, description, category, priority, and optional attachment. Field-specific server validation errors are displayed beside the relevant controls.

## 4.4 Employee ticket detail page

**Primary purpose:** Follow one submitted request, read IT updates, send replies, and access permitted ticket files.

**Features and functionalities:**

- Ticket number, title, description, status, priority, creation/update dates, and resolution when supplied.
- SLA response/resolution summary.
- Category, department, requester, assigned technician, and ticket metadata.
- Conversation includes public replies; internal notes are not shown unless policy explicitly permits viewing them.

**Buttons and interactions:**

- **Back to my tickets:** Returns to the employee ticket list.
- **Send message:** Adds a public reply to the ticket conversation.
- **Add an attachment / Upload file:** Adds a permitted file to the ticket.
- **Download:** Opens an authorized attachment through the protected attachment endpoint.

**Data displayed:** Ticket metadata and lifecycle timestamps, SLA state, resolution, visible messages with authors and dates, and file names/sizes/uploader/time.

---

# 5. IT Support (Service Provider) Dashboard and Accessible Pages

The implemented Service Provider role is named **IT Support**. Its navigation includes Support dashboard and Ticket board. It also has the shared notification menu/page, authorized ticket routes, and sign-out control.

## 5.1 IT Support dashboard

**Primary purpose:** Give support staff a workload overview and direct access to assigned, active, and urgent work.

**Features and functionalities:**

- Ticket workload statistics including assigned-to-me, open, in-progress, waiting-for-user, resolved, and total active tickets.
- High-priority ticket list (up to five records).
- Recently assigned-to-current-user list (up to eight records).
- Counts and ticket queries are limited to tickets visible to the current support user.

**Buttons and interactions:**

- **Workload/status cards:** Open the ticket queue with the corresponding status or assignment filter when linked.
- **Open ticket:** Opens the ticket detail page.
- **Ticket board navigation:** Opens the support queue.

**Data displayed:** Workload counts, high-priority ticket number/title/status/priority/requester as rendered, and recently assigned ticket details with current status, category, priority, and update/assignment recency.

## 5.2 Ticket board and table page

**Primary purpose:** Triage, filter, and manage tickets visible to IT Support.

**Features and functionalities:**

- Kanban board organized by workflow status, with up to 20 records shown per status column.
- Table view for cross-status scanning.
- Search, status, priority, category, assignment, assigned-support-user, and sort filters.
- Links to ticket detail and policy-guarded controls for supported actions.
- The board and table use the same ticket query authorization scope.

**Buttons and interactions:**

- **Kanban / Table:** Switches display mode while retaining applicable query filters.
- **Search:** Filters ticket title/number and requester information.
- **Status / Priority / Category / Assignment / Assigned support user / Sort:** Set optional queue filters.
- **Apply filters:** Submits the queue filters.
- **Clear:** Clears filters and preserves the selected board/table mode.
- **Open ticket:** Opens the full detail page.
- **Status update controls on board:** Submit an allowed status transition; resolution requires a solution entry.
- **Assignment controls on board:** Assign/reassign when the current user has the required ability.
- **Pagination (table):** Moves through paginated list results.

**Data displayed:** Kanban status columns or table rows containing ticket number/title, requester, category, priority, status, assignee, and update time. Board columns are intentionally capped at 20 tickets each.

## 5.3 IT Support ticket detail page

**Primary purpose:** Investigate a ticket, communicate with the requester, record internal work, manage status, and manage assignment.

**Features and functionalities:**

- Ticket description, status, priority, requester contact identity, department, category, assignee, and SLA state.
- Conversation timeline differentiating **Public reply** and **Internal note** visually.
- Assignment history includes assignee, assigning user, start/end timestamps, and notes where available.
- Resolution text is recorded when transitioning to Resolved and is visible to the requester.
- File downloads use policy-authorized private storage.

**Buttons and interactions:**

- **Support queue:** Returns to the ticket board/list.
- **Send message:** Sends a public comment.
- **Add as an internal note:** Sends a staff-only comment when authorized.
- **Upload file / Remove:** Adds or removes a selected pending file; upload is permission-checked.
- **Save status:** Applies an allowed workflow transition. The Solution field is required when choosing Resolved.
- **Assign to me:** Takes ownership of an unassigned ticket.
- **Reassign ticket:** Assigns the ticket to a selected active IT staff member when allowed.
- **Return to unassigned queue:** Ends the current assignment and returns the ticket to the queue.
- **Download:** Retrieves an authorized attachment.

**Data displayed:** Ticket content, requester, department, category, priority, current status, timestamps, resolution, SLA deadlines/state, assignment history, public and internal comments (based on permission), and attachment names/size/uploader/date.

---

## 6. Ticket Workflow and Shared Ticket Concepts

### 6.1 Status lifecycle

The application’s canonical statuses are:

1. **Open** — Submitted and awaiting triage.
2. **Assigned** — Assigned to IT Support staff.
3. **In Progress** — IT Support is working on the request.
4. **Waiting for User** — IT Support needs a response or action from the requester.
5. **Resolved** — A solution has been supplied and awaits confirmation.
6. **Closed** — Work is complete.

The workflow service controls legal transitions. Users cannot select arbitrary states outside the allowed next-transition options. A solution is required to resolve a ticket.

### 6.2 Ticket comments and internal notes

- A reply is public by default and appears to authorized ticket participants.
- IT Support may add an internal note when authorized; it is visibly labeled and access-controlled.
- Each message shows its author and timestamp.
- The conversation can show attachments associated with the message.

### 6.3 Attachments

- Files are held on private storage rather than being exposed by a public storage URL.
- Download requests check ticket and attachment authorization.
- The interface displays original file name, human-readable size, uploader, and upload time.
- Accepted types and size limits are validated server-side; the create form allows a single optional initial attachment.

### 6.4 SLA information

Priority records define response and resolution target minutes. The ticket detail view and reports evaluate due times from the ticket creation timestamp and the configured priority targets. SLA display distinguishes on-track/met and overdue/breached states. Changing priority targets affects evaluation using current stored targets.

---

## 7. REST API Surface (Technical Reference)

The UI guide focuses on Blade pages, but the project also provides a JSON API under `/api`. API callers must send `Accept: application/json`; protected routes use `Authorization: Bearer <token>`. Login issues a Sanctum token that expires after the configured duration (480 minutes by default in the project documentation). API access uses the same authorization policies and services; role names alone do not grant access to every record.

### 7.1 API route groups

| Capability | Methods and endpoints |
|---|---|
| Authentication | `POST /api/auth/login`, `GET /api/auth/user`, `POST /api/auth/logout` |
| Lookups | `GET /api/lookups` |
| Tickets | `GET/POST /api/tickets`, `GET/PATCH /api/tickets/{ticket}`, `PATCH /api/tickets/{ticket}/status` |
| Assignments | `POST /api/tickets/{ticket}/assignments`, `DELETE /api/tickets/{ticket}/assignments/current` |
| Comments | `GET/POST /api/tickets/{ticket}/comments`, `GET /api/tickets/{ticket}/comments/{comment}` |
| Attachments | `GET/POST /api/tickets/{ticket}/attachments`, `GET /api/tickets/{ticket}/attachments/{attachment}` |
| Notifications | `GET /api/notifications`, `GET /api/notifications/unread-count`, `PATCH /api/notifications/{notification}/read`, `PATCH /api/notifications/read-all` |

Ticket list filters include search, status, priority, category, department, assignee, requester, date range, ticket number, assignment, sort, and page size. Validation errors use HTTP 422 with field-level errors. API mutations are throttled, and logout revokes the current token.

---

## 8. Implementation Boundaries and Not-Implemented Items

The following distinctions are important when using this guide as a capability statement:

- **Configurable permissions:** The application implements fixed roles and server-side policies. It does not provide a configurable permission matrix or permission editor.
- **Service Provider role:** There is no separate provider account type; the implemented equivalent is IT Support.
- **Self-service profile page:** No profile-edit page is present. A profile write endpoint exists, but the current navigation/UI does not expose it.
- **Authenticated password-change page:** A password-change endpoint exists, but no corresponding form page appears in the reviewed web UI.
- **Email ticket notifications:** Notifications in the app are database notifications. The example environment uses a log mailer; do not represent database notices as delivered email.
- **Audit log:** Assignment history is recorded. A general immutable audit history for all changes is not presented as a user-facing feature.
- **Self-registration:** Not implemented; administrators create user accounts.
- **Production deployment:** The repository’s own production review flags the current Laravel 11 dependency line as unsupported as of the document date and reports outstanding dependency advisories. The target deployment, mail delivery, secrets, HTTPS, backup/restore, monitoring, and private-storage configuration must be verified separately before production use.

---

## 9. Project Source Areas Reviewed

This guide was prepared from the checked-out project’s route files, role enum, relevant controllers and requests, ticket/query/workflow/SLA services, Blade views and shared components, plus `README.md` and project architecture/security/API/deployment documentation. The page descriptions reflect the implementation observed in those sources; server authorization and validation remain authoritative if a UI control or route is restricted.

---

**End of document**
