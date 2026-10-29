# ParishDesk: Master Build Plan

> **First action after approval:** save this plan as `PLAN.md` in the project root (`/Users/joseph/Projects/personal/parishdesk/PLAN.md`) and **stop** so it can be reviewed. No code, migrations, or dependency changes happen until the user approves `PLAN.md`.

---

## 1. Context

ParishDesk is a parish management system for one Catholic parish. It replaces paper registers, spreadsheets, and WhatsApp-only communication. It covers:

- parishioner and family records
- sacramental registers
- societies/groups and the history of their leaders
- banns of marriage
- events, announcements, and a blog
- finances: collections, dues, donations, campaigns/pledges, and payments
- pastoral records

It has **two dashboards** that look related but work differently:

- **Admin dashboard** (`/admin`): for parish staff and volunteers (clergy, office, finance, society leaders, editors). What each person can see and do comes from the roles and permissions the Super Admin gives them. It is dense and data-heavy, built around a sidebar.
- **Member portal** (`/portal`): for parishioners. It is lighter, built from cards, works well on phones, and lets members see and do things for themselves.

Both share one design system built with **Tailwind CSS v4**. The parish chooses a **theme**, and that theme sets the primary color everywhere.

### Decisions already made

| Topic           | Decision                                                                                                                                                                                                                                                                              |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tenancy         | **Single parish** per installation. Parish details are kept in a settings record, not a tenant table.                                                                                                                                                                                 |
| Payments        | **Offline only for now.** Admins record cash, transfer, POS and cheque payments. Members can upload proof of payment. A gateway abstraction (driver interface, webhooks, payment attempts) is built now so Paystack, Flutterwave or Stripe can be added later without schema changes. |
| Database        | **MySQL 8** in production. SQLite stays for the local test suite where it is compatible.                                                                                                                                                                                              |
| Member accounts | **Admin invite only.** Public registration is turned off. Admins invite a member record by email (SMS later), and the member sets a password through a signed link.                                                                                                                   |

### Current codebase (starting point)

- Laravel 13, PHP 8.3+, Inertia v3 + Vue 3 + TypeScript, Tailwind v4 (`resources/css/app.css` already maps `--primary`, `--sidebar-*`, `--chart-*` CSS variables through `@theme inline`).
- shadcn-vue style UI kit in `resources/js/components/ui/*` (button, card, dialog, dropdown, select, sheet, sidebar, badge, avatar, skeleton, sonner, tooltip…), built on reka-ui.
- Fortify auth with 2FA and passkeys. Pages are in `resources/js/pages/auth`, settings in `resources/js/pages/settings`, and the Appearance (light/dark) tab is `AppearanceTabs.vue`.
- Wayfinder for typed routes, Pest 5, Larastan, Pint, `vp` (vite-plus) build.
- Layouts: `resources/js/layouts/AppLayout.vue` and `layouts/app/*` (sidebar shell from `AppSidebar.vue`, `NavMain.vue`, `NavUser.vue`).
- Only the `User` model exists. The folder is not yet a git repo; run `git init` as the first foundation step.

---

## 2. Technical Architecture

### 2.1 Stack additions (need approval before installing)

| Package                                            | Purpose                                                                                     |
| -------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `spatie/laravel-permission`                        | Roles and granular permissions, assigned through roles or directly to users (**confirmed**) |
| `spatie/laravel-activitylog`                       | Audit trail for every change to a record (required for registers and finance)               |
| `spatie/laravel-medialibrary`                      | Photos, cover images, attachments, proof-of-payment uploads                                 |
| `spatie/laravel-sluggable` (optional)              | Blog and page slugs                                                                         |
| `barryvdh/laravel-dompdf`                          | Certificates, receipts, giving statements, reports as PDF                                   |
| `spatie/simple-excel`                              | CSV/XLSX import (members) and exports                                                       |
| `@tiptap/vue-3` + starter kit                      | Rich text editor for blog posts and announcements                                           |
| `@unovis/vue` (shadcn-vue charts) or `vue-chartjs` | Dashboard charts                                                                            |
| `@tanstack/vue-table`                              | Headless engine behind a reusable `DataTable` component                                     |

### 2.2 Backend structure (inside the existing `app/` conventions)

```
app/
  Enums/                 # MemberStatus, SacramentType, PostStatus, PaymentMethod, … (backed enums)
  Models/                # One model per table, grouped by naming, not sub-namespaces
  Actions/<Module>/      # Single-purpose business actions (e.g. Members/InviteMember, Finance/RecordPayment)
  Http/Controllers/Admin/<Module>/…   # Admin dashboard controllers
  Http/Controllers/Portal/…           # Member portal controllers
  Http/Controllers/Public/…           # Public blog/events/announcements
  Http/Requests/<Module>/…            # Form Requests for every write
  Policies/                           # One policy per model; permissions checked here
  Payments/              # PaymentGateway contract, OfflineGateway, GatewayManager, webhook handling
  Support/Numbering/     # Member no., receipt no., certificate no. generators
  Notifications/         # Database + mail (SMS channel added later)
  Console/Commands/      # Scheduled jobs (publish posts, dues generation, reminders)
```

`Enums`, `Payments` and `Support` are new sub-folders under `app/`. They are listed here so they get approved together with this plan.

### 2.3 Routing

- `routes/web.php`: public pages (home, blog, public events, public announcements) and auth.
- `routes/admin.php`: prefix `/admin`, name `admin.`, middleware `auth, verified, can:access-admin`.
- `routes/portal.php`: prefix `/portal`, name `portal.`, middleware `auth, verified, member.linked`.
- `routes/webhooks.php`: `POST /webhooks/payments/{gateway}` (CSRF-exempt, verified by signature).
- After login, users go to `/admin` if they hold any admin permission (through a role or directly), otherwise to `/portal`. Staff who are also parishioners get a "Switch to my portal" toggle.

### 2.4 Cross-cutting conventions

- **Money** is stored as `unsignedBigInteger` minor units (kobo/cents) with a `currency` column. The parish's default currency is set in settings. A `Money` cast and value object handle conversion.
- **Soft deletes** on all people and register records. Nothing in a sacramental or financial register is ever hard-deleted. Financial entries are fixed by **reversal entries**, not edits.
- **Audit log** on every model that has a register or finance role.
- **Numbering**: configurable formats (e.g. `MEM-2026-0001`, `RCPT-000123`, `BAP/2026/045`), generated atomically.
- **Server-side tables**: one shared filter/sort/paginate pattern (a `HasTableQuery` concern plus `DataTable.vue`), used by every list page.
- **Authorization**: policies everywhere. Inertia shares `auth.permissions` so the UI can hide actions; the server always enforces them.
- **Timezone and date formats** come from parish settings.

---

## 3. Roles & Permissions

### 3.0 Roles are data, not code

- **Super Admin is the only built-in role.** It is created by the installer and cannot be renamed, edited or deleted. It always has every permission (`Gate::before`).
- **No other roles are predefined.** A fresh installation has exactly one role, `super-admin`, and one user, the Super Admin account created by `php artisan parishdesk:install`, which asks for name, email and password. There are no seeded roles, starter templates or suggested role sets.
- **The Super Admin sets up every other role.** They name each role, give it a description, and pick exactly which permissions it holds from the permission matrix. Parishes organise differently, so a role can be anything: "Parish Secretary", "Choir Coordinator", "Building Committee Treasurer", "Youth Chaplain"… The Super Admin can also give `settings.roles.manage` to other users so they can help manage roles.
- **The code never checks role names.** Policies, middleware, menus and the UI only check **permissions** (`$user->can('members.update')`), never `hasRole('secretary')`. So renaming, deleting or creating a role never breaks the app, and a role does exactly what its permissions allow, nothing more and nothing less. The only exception is the `super-admin` bypass.
- **What ships in code is the permission catalogue only** (§3.2): every action the app can guard, grouped by module, with clear labels and descriptions. Roles are assembled from it.

- **Portal access is not a role.** A user can use the member portal when their account is linked to a member record. The admin dashboard needs at least one admin permission. One person can have both.

### 3.1 Principles

- **Package:** `spatie/laravel-permission` (confirmed). The `User` model uses the `HasRoles` trait. Permissions go to **users**, never to member records.
- **Every admin resource is guarded. No exceptions.** Each admin route, controller action, Inertia page, sidebar item, button, export and bulk action needs a named permission. Anything that does not declare one is denied by default.
- **Two ways to grant access**, managed by the admin in the UI:
    1. **Through roles:** a user is given one or more roles and inherits all of their permissions.
    2. **Directly:** a user is given specific permissions on top of their roles, e.g. a Secretary who may also `finance.payments.verify`.
       A user's **effective permissions** are their role permissions plus their direct permissions (Spatie's `getAllPermissions()`).
- **Scope is expressed as a permission too**, so custom roles can control it. Where it matters, a permission comes in `.any` and `.own` variants:
    - `societies.members.manage.any` vs `.own` (only societies the user leads)
    - `pastoral.records.view.any` vs `.own` (only entries the user wrote)
    - `events.update.any` vs `.own`
      Policies check `.any` first, then `.own` together with an ownership check.

### 3.2 Permission naming & registry

- Permissions are named `module.resource.action`. Actions are `view`, `create`, `update`, `delete`, `export`, `import`, `approve`, `restore`, plus a few special ones. Examples: `members.view`, `members.create`, `sacraments.certificates.issue`, `finance.transactions.approve`, `finance.payments.verify`, `blog.posts.publish`, `blog.comments.moderate`, `pastoral.confidential.view`, `settings.roles.manage`, `settings.theme.update`.
- **Single source of truth:** a `PermissionRegistry` in `app/Support/Permissions/`, or a backed enum `App\Enums\Permission`, lists every permission grouped by module, each with a label and description for the UI.
- An idempotent `php artisan permissions:sync` command runs on deploy and in the seeder. It creates any missing permissions, removes ones that no longer exist, and gives every permission to `super-admin`. It **never touches admin-defined roles**. New permissions added in later releases start unassigned, and the admin grants them to roles as needed. CI fails if a policy or route uses a permission that is not in the registry.

### 3.3 Enforcement layers (defence in depth)

1. **Route middleware:** the whole `/admin` group requires `auth`, `verified`, `can:access-admin`. A user can access admin if they have _any_ admin permission. Individual route groups also use Spatie's `permission:` middleware (never `role:`).
2. **Policies:** one per model, and every controller action calls `Gate::authorize()` / `$this->authorize()`. Each policy method checks the matching permission (`$user->can('members.update')`). Scope rules are permissions too, never role names:
    - `.own` permissions only cover records the user owns or leads (e.g. their own society, the pastoral entries they wrote).
    - Confidential records are only shown with `pastoral.confidential.view`.
3. **Form Requests:** `authorize()` delegates to the policy.
4. **Super Admin bypass:** a `Gate::before()` lets `super-admin` through. It is the only bypass.
5. **Frontend (display only):** Inertia shares `auth.permissions` (effective permission names) and `auth.roles`. A `usePermissions()` composable (`can('members.create')`, `canAny([...])`) and a `<Can>` wrapper component hide navigation items, buttons and columns. Sidebar items declare the permission they need and are filtered with it. **Hiding something in the UI is never the protection. The server always enforces access.**
6. **Data-level filtering:** list queries respect scopes. For example, a user with only `societies.members.manage.own` only sees members of the societies they lead.

### 3.4 Admin UI: Access Control (Settings → Users & Access)

- **Roles page:** list the roles with how many users and permissions each has. **Create a new role** (name, description, colour badge), then define what it can do by ticking permissions. Edit, rename or duplicate any role (duplicating one is a quick way to make a similar role). Super Admin is shown read-only. Each role has a **permission matrix**: modules as collapsible groups, with action columns, check-all per module, and search. A role can only be deleted once it has no users, or after its users are reassigned.
- **Users page:** list staff users with their roles and how many direct permissions they have, with filters by role. From a user you can:
    - assign or remove roles (multi-select)
    - grant or revoke **direct permissions** using the same matrix (inherited permissions show as checked and locked, labelled "via role X")
    - see an **effective permissions** preview
    - activate or deactivate the account and force a password reset
- **Staff invitations:** invite a staff user by email and pick their roles and direct permissions at invite time. This uses the same signed-link flow as member invites.
- **Safeguards:**
    - Admins cannot remove their own `settings.roles.manage` or `super-admin`.
    - The last super admin cannot be demoted.
    - Only super admins can grant `super-admin`.
    - A user can only grant permissions they hold themselves (no privilege escalation).
- **Audit:** every role, permission and assignment change is written to the activity log (who, what, before/after) and shown on the user's and role's history tab.
- **Cache:** Spatie's permission cache is cleared automatically on change. Users' Inertia-shared permissions refresh on their next request.

### 3.5 Tests for access control

- A **route coverage test** goes through every route in the `admin.` group and asserts that a user with no permissions gets 403. This makes sure no route is left unguarded.
- Per-module policy tests: allowed with the permission (via role and via direct grant), denied without it.
- Safeguard tests: no self-lockout, last super admin protected, no privilege escalation.
- `permissions:sync` is idempotent and never changes admin-defined roles.
- A newly created custom role with a single permission (e.g. only `blog.posts.create`) can reach exactly that feature and gets 403 everywhere else.
- An **architecture test** (Pest `arch()`) fails the build if `hasRole(`, `role:` middleware or role-name strings appear outside the super-admin bypass.

---

## 4. Theming & Design System

### 4.1 Theme engine

- Theme presets go in `resources/css/themes.css`, imported from `app.css`. Each preset sets the CSS variables the app already uses (`--primary`, `--primary-foreground`, `--ring`, `--sidebar-primary`, `--sidebar-accent`, `--chart-1..5`), in both light and dark versions:
    ```css
    [data-theme="marian"]        { --primary: oklch(0.52 0.15 255); … }
    .dark[data-theme="marian"]   { --primary: oklch(0.70 0.13 255); … }
    ```
- Presets: **Marian Blue**, **Ordinary Green**, **Advent/Lent Purple**, **Martyr Red**, **Papal Gold**, **Gaudete Rose**, **Slate** (neutral).
- **Custom color**: the admin picks a hex color. The server converts it to an OKLCH scale and works out a readable foreground color (WCAG contrast check). The result is saved and injected as an inline `<style>` in `app.blade.php`.
- The parish-wide theme is saved in `parish_settings.theme` and shared to Inertia. `app.blade.php` writes `data-theme` on `<html>` during server rendering so the color never flashes on load.
- Each user still controls light/dark/system through the existing `AppearanceTabs.vue`.
- Optional (later): **liturgical-season auto theme** that switches presets by date.
- Admins see a live preview in **Settings → Appearance & Branding**, along with the logo, favicon, and parish name.

### 4.2 Two related dashboards

|         | Admin (`AdminLayout.vue`)                                                                                                              | Member portal (`PortalLayout.vue`)                                                                 |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| Shell   | Collapsible sidebar with grouped modules (reuses `components/ui/sidebar`) and a top bar with search (⌘K), notifications, and user menu | Top header with parish logo plus a short horizontal nav. On mobile it becomes a **bottom tab bar** |
| Density | Compact tables, filters, bulk actions, side-sheet forms                                                                                | Spacious cards, large tap targets, few actions                                                     |
| Shared  | Same tokens, typography, radius, `components/ui/*`, iconography (lucide), empty states, toasts (sonner)                                |

Shared building blocks to create once and reuse: `PageHeader`, `StatCard`, `DataTable`, `FilterBar`, `EmptyState`, `FormSheet`, `ConfirmDialog`, `MoneyInput`, `MoneyDisplay`, `DatePicker`, `MemberPicker` (async search), `FileUpload`, `RichTextEditor`, `StatusBadge`, `Timeline`, `CommandPalette`.

### 4.3 Frontend layout

```
resources/js/
  layouts/AdminLayout.vue, layouts/PortalLayout.vue, layouts/PublicLayout.vue
  pages/admin/<module>/{Index,Show,Create,Edit}.vue
  pages/portal/<area>/*.vue
  pages/public/{Home,Blog/Index,Blog/Show,Events/*}.vue
  components/app/*        # shared composites listed above
  composables/usePermissions.ts, useMoney.ts, useTheme.ts, useFilters.ts
```

---

## 5. Domain Modules & Data Model

Tables are listed with their key columns only. Timestamps, soft deletes and audit fields are implied.

### 5.1 Parish settings & structure

- `parish_settings`: a single row holding name, patron saint, diocese, deanery, address, contacts, logo, theme, custom_color, currency, timezone, fiscal_year_start, numbering formats, and bank details shown for offline payments.
- `zones`: stations, outstations, or Small Christian Communities (name, type, leader_member_id).
- `clergy_assignments`: member_id or name, role (parish priest, assistant, deacon, resident), start/end. This is the history of priests who served the parish.

### 5.2 People & families

- `members`: member_no, title, first/middle/last name, baptismal name, gender, date_of_birth, marital_status, phone, alt_phone, email, address, occupation, state/LGA of origin (optional), photo, zone_id, family_id, family_role (head, spouse, child, dependant, other), status (active, inactive, transferred_in, transferred_out, deceased), joined_at, notes, `user_id` (nullable, set once invited).
- `families`: name, head_member_id, address, zone_id, home_phone, notes.
- `member_invitations`: member_id, email, token, expires_at, accepted_at, invited_by.
- `member_transfers`: in or out, from/to parish, date, letter document.
- `profile_change_requests`: changes members submit from the portal, reviewed and approved by a user with `members.change-requests.approve`.
- Features: search and filter (zone, society, status, age range, sacrament gaps such as "confirmed: no"), member profile page with tabs (overview, family, sacraments, societies, giving, pastoral, activity), family tree view, CSV import with field mapping and preview, bulk invite, birthday and anniversary lists, export.

### 5.3 Sacraments

- `sacrament_records`: member_id (nullable, for people baptised here who are not members), person_name (snapshot), type (enum: baptism, first_holy_communion, confirmation, matrimony, holy_orders, anointing_of_the_sick), date, place/parish, minister, register_book, page, entry_no, certificate_no, father_name, mother_name, details (JSON for fields specific to each type, e.g. confirmation name), notes.
- `sacrament_sponsors`: record_id, member_id or name, role (godparent, sponsor, witness).
- `sacrament_classes` and `sacrament_enrollments`: preparation classes (first communion, confirmation, catechumenate, marriage course) with catechist, schedule, attendance, and completion.
- `certificate_requests`: a member requests a baptismal or other certificate from the portal. Staff then approve it, generate the PDF, and mark it collected.
- Features: register views that look like the paper books, certificate PDF templates with parish branding and numbering, links to the member profile, and a report of members with missing sacraments.

### 5.4 Marriage & banns

- `marriage_applications`: groom/bride (member_id or external details plus their parish), status workflow, proposed date, officiant, witnesses, canonical checks (baptism certificates received, marriage course completed, freedom-to-marry declarations), documents.
- `banns_publications`: application_id, publication_no (1–3), publication date, published_by, and the announcement it was included in.
- `banns_objections`: application_id, raised_by, details, resolution.
- Workflow: **Applied → Documents verified → Banns 1 → Banns 2 → Banns 3 → Cleared → Married → Recorded** (this creates the `matrimony` sacrament record and updates both members' marital status and family link). Banns can be added to Sunday announcements and published on the portal automatically. External parishes can be sent a banns request or confirmation letter as a PDF.

### 5.5 Societies, groups & leadership history

- `societies`: name, category (pious society, choir, youth, men's/women's organisation, council, committee, liturgical ministry), description, patron, meeting schedule, logo, is_active, dues settings.
- `society_memberships`: society_id, member_id, joined_at, left_at, status.
- `society_positions`: society_id, title (President, Secretary, Treasurer…), sort order.
- `society_leadership_terms`: society_id, position_id, member_id, start_date, end_date, elected_on, notes. This is the **full leadership history**, shown as a timeline of who held each office and when.
- `society_meetings` and `society_attendance` (Phase 2 of this module).
- The Parish Pastoral Council and finance committee are modelled as societies in the "council" category, so they get leadership history for free.
- Users holding `societies.*.own` permissions get a view limited to the societies they lead (from `society_leadership_terms`): members, announcements to the society, dues, and attendance.

### 5.6 Events & Mass schedule

- `events`: title, description, cover, location, starts_at, ends_at, all_day, recurrence (RRULE), society_id (nullable), visibility (public, members, society), registration_enabled, capacity, registration_deadline.
- `event_registrations`: event_id, member_id, status, checked_in_at.
- `mass_schedules`: day, time, language, location. Shown on the public site and in the portal.
- `mass_intentions`: requested_by (member or name), intention text, type (thanksgiving, repose of soul…), preferred date/mass, stipend amount, status. Links to the finance module for the stipend payment.
- Features: calendar view (month, week, list), iCal feed, reminders.

### 5.7 Announcements

- `announcements`: title, body (rich), audience (all, members, society, zone, role), audience_ids, priority (normal, important, urgent), is_pinned, publish_at, expires_at, status (draft, scheduled, published, expired), channels (portal, email; SMS/WhatsApp later), attachments.
- `announcement_reads`: announcement_id, user_id, read_at. This gives "seen by" counts.
- Features: scheduling, pinning, a "Sunday bulletin" composer that pulls this week's announcements, banns, and Mass intentions into a printable PDF, and notification fan-out through the queue.

### 5.8 Blog

- `posts`: title, slug, excerpt, body (Tiptap JSON plus rendered HTML), cover image, author_id, status (draft, scheduled, published, archived), `published_at`, visibility (public, members), allow_comments, comment moderation mode (auto-approve members / moderate all), featured, SEO title and description, reading_time.
- `post_categories` (and the pivot).
- `tags` + `taggables`: polymorphic, so the same tags can be reused on events and announcements later. **Members can suggest tags** on a post. Suggested tags wait for moderation and only appear once an editor approves them.
- `comments`: post_id, user_id, parent_id (one level of threaded replies), body, status (pending, approved, rejected, spam), approved_by, edited_at.
- `comment_reactions` (optional, later).
- **Scheduling**: a `Post::scopePublished()` query (`status in (published, scheduled) AND published_at <= now()`) means a post goes live exactly at its time, even if the scheduler is late. A scheduled command, `posts:publish-due` (runs every minute), flips the status to `published` and sends notifications.
- Features: editor with preview, autosave of drafts, a schedule picker in the parish timezone, revision history (activity log), moderation queue, public blog with category and tag filters, RSS feed, Open Graph meta, members-only posts in the portal.

### 5.9 Finance

**Ledger core**

- `funds`: General, Building Fund, Charity, Seminary, and others. Each has a name, code, is_restricted, and opening_balance.
- `income_categories` and `expense_categories` (as one hierarchical `finance_categories` table with a `type` column): Sunday collection, tithe, thanksgiving, harvest, dues, donations, Mass stipends, stole fees, salaries, utilities…
- `transactions`: the single source of truth. Columns: type (income, expense, transfer, reversal), fund_id, category_id, amount, currency, date, payment_method (cash, bank_transfer, POS, cheque, mobile_money, online), reference, payer/payee (member_id or free text), society_id, description, status (pending, approved, void), recorded_by, approved_by, `source` (morph to payment, collection, pledge…), reversal_of_id.
- `collections`: per Mass or service. Records the date, mass, counters (2–3 people), denomination breakdown (JSON), and total. Posting a collection creates the transaction.

**Member obligations**

- `dues_plans`: name (Annual Parish Dues, Society Dues), amount, frequency, applies_to (all adult members, society, family), fund, category, start date.
- `member_obligations`: member_id or family_id, plan_id, period, amount_due, amount_paid, status (unpaid, partial, paid, waived), due_date. These are generated by the scheduled command `dues:generate`.

**Donations & campaigns**

- `donations`: donor (member_id or name, anonymous flag), amount, fund, campaign_id (nullable), in_kind description, date.
- `campaigns`: name, description, cover, goal amount, start/end, fund, is_public, show_donor_names.
- `pledges`: campaign_id, member_id or family_id, amount, installments/frequency, start date, status.
- Campaign progress is shown as a total and a percentage, with a portal widget and a public page.

**Payments (gateway-ready)**

- `payments`: one row per money-in event from a member. Columns: payer member_id, amount, currency, method, gateway (offline, paystack…), status (pending, awaiting_verification, succeeded, failed, refunded), gateway_reference, receipt_no, paid_at, recorded_by, proof media.
- `payment_allocations`: payment_id plus a morph to obligation, pledge, donation, or mass intention, with the amount. **One payment can settle several items.**
- `payment_attempts`: payment_id, gateway, request/response payload, status. Used to make webhooks idempotent.
- `App\Payments\Contracts\PaymentGateway`: `initiate(Payment)`, `verify(string $reference)`, `handleWebhook(Request)`, `refund(Payment, amount)`, `supports(method)`.
- `OfflineGateway` is the only driver for now. `GatewayManager` reads `config/payments.php` (`default`, `enabled`, credentials from env). The webhook route and controller exist but return 404 until a driver is enabled.
- **Offline flow from the portal**: a member chooses what to pay (dues, pledge, donation), sees the parish bank details, uploads a transfer receipt, and the payment becomes `awaiting_verification`. A user with `finance.payments.verify` verifies it, which creates the transaction, updates allocations, and issues the PDF receipt.
- **Admin flow**: record a payment at the desk, which gives an instant receipt PDF (and email).

**Reports**

- Income vs expense by period, fund balances, category breakdown, collections trend, dues compliance (by zone and society), campaign reports, cash book, member annual giving statement (PDF), audit trail. All exportable to CSV, XLSX, and PDF.
- Later: budgets vs actuals, bank reconciliation, approval thresholds.

### 5.10 Pastoral records

- `pastoral_records`: type (sick call, home visit, counselling, communion to the sick, anointing, general note), member_id/family_id, date, minister_id, summary, follow_up_at, **confidential** flag. Confidential records are visible only with `pastoral.confidential.view` and are encrypted at rest with Laravel's `encrypted` cast.
- `sick_list`: members currently receiving communion at home or in hospital.
- `burials`: deceased (member or name), date of death, funeral date, place of burial, officiant, register entry. Recording a burial sets the member's status to deceased.
- `pastoral_requests` (from the portal): visit request, blessing of a house or car, counselling appointment. These are routed to the clergy.
- Letters: recommendation, transfer, and status letters generated from templates as PDFs.

### 5.11 Notifications & communication

- Laravel notifications over the **database** and **mail** channels. A `NotificationChannel` contract leaves room for SMS/WhatsApp providers later.
- Triggers include: invitation, announcement, post published, comment approved/replied, payment verified/receipt, dues reminder, event reminder, request status changed, banns published, birthday greeting (optional).
- A bell in both dashboards and a notification preferences page in the portal.

---

## 6. Dashboard Home Screens

**Admin home**

- KPI cards: total members (+ new this month), families, active societies, income this month vs last month, outstanding dues, active campaign progress.
- Charts: monthly income by fund (12 months), membership growth, collections trend.
- Work queues: payments awaiting verification, certificate and pastoral requests, comments to moderate, banns in progress, profile change requests.
- Upcoming events and birthdays this week, plus a recent activity feed.
- Each widget declares the permission it needs, so what appears depends on the user's effective permissions (e.g. someone holding `finance.*` sees finance widgets, and someone with only `societies.*.own` sees their society).

**Member portal home**

- Greeting with the member's photo and member no., plus pinned or urgent announcements.
- Cards for: my family, my societies (with my role), upcoming events (register in one tap), my giving this year, outstanding dues (pay/upload proof), my pledges with progress, my sacraments (read-only from the register), and latest blog posts.
- Quick actions: book a Mass intention, request a certificate, request a pastoral visit, update my details, download my giving statement.

---

## 7. Build Phases & Milestones

Each phase ends with migrations, factories, seeders, policies, Pest feature tests, and UI. **Every phase produces something you can demo.**

### Phase 0: Foundation

1. `git init`, CI workflow (`.github` already present): Pint, Larastan, Pest, `vp check`, `vue-tsc`.
2. MySQL config in `.env.example`. Tests run on SQLite in memory (MySQL in CI if needed).
3. Install the approved packages. Build the full access-control foundation from §3: permission registry, `permissions:sync`, `parishdesk:install` (creates the `super-admin` role and Super Admin account, with no other roles), `access-admin` gate, super-admin `Gate::before`, the `usePermissions` composable and `<Can>` component, permission-filtered sidebar, the **Users & Access** admin screens (roles, permission matrix, direct user permissions, staff invites), and the route-coverage test. Every later module registers its permissions here and ships with policies.
4. Turn off Fortify registration and add the invite-acceptance flow (signed URL → set password → link to member).
5. `parish_settings` + Settings pages (parish profile, branding, theme with live preview, finance defaults, numbering).
6. Theme engine (`themes.css`, `data-theme` written during server rendering, custom color generator).
7. `AdminLayout`, `PortalLayout`, `PublicLayout`, the post-login redirect, and the shared component kit (§4.2), including `DataTable` and the server-side query concern.
8. Activity log viewer, `Money` cast, and the numbering service.

### Phase 1: People

Members, families, zones, clergy assignments, member profile tabs, CSV import, invitations, portal profile and profile-change requests, birthdays.

### Phase 2: Sacraments & Marriage

Sacrament registers, sponsors, preparation classes, certificate PDFs and requests, marriage applications, the banns workflow, and burials (shares the register code).

### Phase 3: Societies & Leadership

Societies, memberships, positions, leadership terms with a history timeline, Pastoral Council, the society-leader scoped dashboard, meetings and attendance.

### Phase 4: Communication

Announcements (audiences, scheduling, reads), events and calendar, Mass schedule and intentions, blog (editor, categories, tags plus member tag suggestions, comments plus moderation, scheduled publishing), notifications, and the Sunday bulletin PDF.

### Phase 5: Finance

Funds, categories, transactions and reversals, collections, dues plans and obligations generation, donations, campaigns and pledges, payments and allocations, the offline proof-of-payment flow, receipts, the gateway abstraction plus webhook skeleton, and financial reports.

### Phase 6: Pastoral & Member Self-Service

Pastoral records (encrypted confidential notes), sick list, pastoral requests, letters, and a unified "My Requests" page in the portal.

### Phase 7: Insights & Hardening

Permission-aware dashboard widgets and charts, the full export suite, global search (⌘K), performance (indexes, eager loading checks, caching settings and permissions), security review, backups (`spatie/laravel-backup` – proposed), queue workers and scheduler for deployment, and documentation for parish staff.

### Backlog (later)

Payment gateway driver (once chosen), SMS/WhatsApp notifications, liturgical calendar and readings, mobile PWA, online event ticketing, volunteer and ministry rosters (lectors, altar servers), inventory/assets, budgets, multi-language UI, diocese reporting exports.

---

## 8. Testing & Verification

- **Pest feature tests** per module:
    - CRUD
    - policy and permission enforcement (allowed with the permission, denied without it)
    - validation
    - workflows (banns stages → matrimony record; payment verification → transaction + allocation + receipt; dues generation; pledge balance)
- **Time-based tests** use `$this->travelTo()`:
    - a scheduled post is hidden before `published_at` and visible after
    - `posts:publish-due` flips the status and sends notifications
    - announcements expire
- **Finance invariants**:
    - allocations never exceed the payment amount
    - reversals net to zero
    - fund balances equal the sum of their transactions
- **Gateway contract test** against `OfflineGateway` plus a fake driver, to show a new gateway only needs a class and config entry.
- Each phase is checked with `composer test` (Pint, Larastan, Pest), `npm run check`, and `npm run types:check`.
- Manual check in the browser through `composer run dev`:
    - log in as Super Admin, create a few test roles and users, then log in as each one to confirm the navigation and access (local dev seeders may create sample roles for testing only, never in production)
    - switch themes and light/dark
    - check the portal on a ~400px mobile width
- Boost `browser-logs` and `last-error` are used to catch runtime errors.

---

## 9. Open Questions (can be settled as we go)

1. Default currency and locale (e.g. NGN, en-NG)?
2. Should the public website (home, blog, events, Mass times) launch in Phase 4, or stay portal-only at first?
3. Can guests (not logged in) comment on public posts, or only members?
4. Paper register formats: do certificate layouts need to match a diocesan template?
5. Is SMS needed early for members without email (affects invitations)?
