# CTSMD Connect Product Backlog

This document is the canonical product wishlist/backlog for CTSMD Connect. It captures approved future-facing ideas from product discussions so they do not live only in chat history.

**Build principle:** Experience defines the product. Background logic exists to support the approved experience.

## Status key

- **Implemented** — source build completed; local runtime verification may still be pending
- **In progress** — current build slice
- **Next** — high-priority near-term build
- **Backlog** — approved direction, not yet scheduled
- **Platform** — foundational technical work that supports multiple features

## Recently implemented

### Implemented — Authentication + normalized RBAC

- Real session authentication with `/login` and POST `/logout`.
- Passwords use PHP `password_hash()` / `password_verify()` and require at least 12 characters on activation/reset.
- Invitation-only activation at `/activate?token=...`; invitation tokens are random and stored only as SHA-256 hashes.
- Time-limited password reset workflow at `/forgot-password` and `/reset-password?token=...`; reset tokens are stored hashed and single-use.
- Account lifecycle states: invited, active and disabled.
- Normalized roles and permissions replace runtime authorization from `display_role` strings.
- System roles: Member, Student, Volunteer, Production Staff, Moderator, Safeguarding and Administrator.
- Permission-specific navigation and `AccessPolicy` checks for people, productions, Community, moderation, volunteers, forms, resources, Playbills, safeguarding, accounts and audit access.
- Account & Access workspace at `/admin/accounts` for invitation issuance, role assignment and account disable/enable operations.
- Administrators cannot disable themselves or remove their own Administrator role from the account workspace.
- Front-controller authentication boundary protects private CTSMD routes while leaving published Playbills, health, activation/reset, and token-authenticated ICS feeds public as intended.
- Legacy `is_demo_current_user` lookups are resolved per authenticated browser session by the database adapter instead of changing a global database flag, preserving concurrent-user safety while older screens are migrated incrementally.
- `/dev/identity` now creates a local-only session identity rather than mutating the shared database current-user marker.
- Existing prototype display-role labels are used only once by migration 017 to bootstrap initial normalized roles; authenticated runtime authorization does not trust those strings.
- **Runtime verification:** pending local MAMP test after migration 017, including invitation activation, simultaneous browser identities, role denial, logout and password reset.

### Implemented — Calendar + schedule lifecycle

- First-class Calendar route at `/calendar` in the Theatre navigation.
- Month, week and 90-day agenda views.
- Consolidated personal schedule across all active productions the current account can access.
- Production and Production Group filters plus guardian child-focus filtering.
- Cross-production overlap/conflict warnings run against the actual visible personal event set.
- Cancelled events remain in calendar history and ICS feeds instead of being hard-deleted.
- Staff can duplicate a schedule item seven days forward and cancel events with a communication draft.
- Per-user revocable private ICS subscription feed.
- **Runtime verification:** pending local MAMP test after migration 016.

### Implemented — Volunteer hours, training + credential automation

- Member volunteer-hours history at `/volunteer/history` and training/readiness at `/volunteer/training`.
- Staff Volunteer Development workspace at `/admin/volunteer-development`.
- Completed shifts feed verified service hours; verified training and approved mapped forms feed canonical volunteer credentials.
- Core automated transitions have database-level safety-net triggers in migration 015.
- **Runtime verification:** pending local MAMP test after migration 015.

### Implemented — Dynamic forms

- Staff Form Builder at `/admin/forms/build` and `/admin/forms/builder?id=...`.
- Structured text, choice, date, acknowledgment and signature fields.
- Field-level answers with immutable submission definition snapshots.
- **Runtime verification:** pending local MAMP test after migration 014.

### Implemented — Attendance

- Attendance workspace at `/attendance` using schedule/Production Group targeting.
- Staff roll call, absence reporting and excused-absence acknowledgment.
- **Runtime verification:** pending local MAMP test after migration 013.

### Implemented — Community moderation

- Admin-managed moderation term library and review queue.
- Block/hold actions with controlled normalized/fuzzy matching; clean posts publish immediately.
- **Runtime verification:** pending local MAMP test after migration 012 and optional seed 002.

### Implemented — Production groups + targeted schedule audiences

- Production-native groups drive targeted schedule, notice, Attendance and Calendar audiences.
- **Runtime verification:** pending local MAMP test after migration 011.

## Near-term build order

### Next — File uploads/storage

- Storage abstraction that behaves consistently on MAMP and Bluehost/shared hosting.
- Production and organization resource uploads.
- File versioning and archive history.
- Download permission enforcement.
- Image/PDF preview support.
- Attachment foundation for Community, Messages, Forms and Playbills.

### Next — Email notifications

- Email delivery service, including automatic invitation/password-reset delivery.
- User notification preferences.
- Per-channel notification settings.
- Form/credential/shift reminders.
- Digest delivery.
- Later: push-notification architecture.

### Next — Parent multi-child / multi-production dashboard

- One family dashboard spanning children and active productions.
- Upcoming calls and schedule changes by child.
- Missing/overdue forms, volunteer commitments, unread communications and conflicts.

### Next — Public website / registration layer

- Public CTSMD website/CMS bridge.
- Production/audition/event pages and registration.
- Workshops/camps/classes, public calendar/Playbills, news, donations, sponsorship, RSVP/waitlists and later payments.

## Production operations backlog

- Audition registration and audition-session management.
- Casting workflow and role/character assignment improvements.
- Production checklist/readiness dashboard.
- Call sheets and production-day operational checklist.
- Production group reuse/templates between shows where appropriate.
- Production archive/history browser.
- Season setup and season-level reporting.
- Attendance aggregate reports, trends and exports.
- Rich recurring-schedule rules beyond duplicate +7 days.

## Community backlog

- Complete hybrid Channel UI: audience + selected people + Teams together.
- Team owners/roles, ordering and archive management.
- Channel pinning/featured posts and reactions.
- Moderation rule refinements and false-positive tuning.
- Search across Community posts.
- Attachments/photos after storage is available.
- First-class announcement composer separate from schedule-generated notices.
- Better channel notification preferences.

## Messaging backlog

- Participant management where safeguarding rules permit it.
- Better guardian selection when a student has multiple guardians.
- Conversation search and unread state.
- Attachments after storage is available.
- Staff escalation/reporting workflow.
- Safeguarded group conversations where product rules allow them.

## Forms backlog

- Conditional fields.
- Guardian-on-behalf-of-student completion.
- Production Group targeting.
- Bulk reminders and completion dashboards.
- File-upload fields after storage exists.
- Registration-oriented forms.
- External/e-sign provider evaluation only if required later.

## Volunteer backlog

- Shift duplication/recurring shifts.
- Waitlists and staff manual assignment.
- Credential expiration reminders/notifications.
- Background-check administration workflow improvements.
- Training content delivery beyond staff verification.
- Coordinator aggregate dashboards and roster/service-hour exports.
- Volunteer service goals/recognition if desired later.

## Resources backlog

- Actual file uploads/storage.
- Organization-wide resources in addition to production-scoped resources.
- Resource version history.
- Preview/render support for common document formats.
- Group-targeted resources.
- Download/view audit where needed.

## Playbill backlog

- Headshots/photos and actor/staff bios.
- Sponsor logos/ads and reusable sponsor management.
- Production artwork and drag/reorder sections.
- Credits beyond active production membership.
- Public QR sharing and print-friendly/PDF output.

## Notifications backlog

- Email delivery and push later.
- Notification/per-channel preferences and digest mode.
- Scheduled reminders, including credential expiration, forms and volunteer shifts.

## Home/dashboard backlog

- Stronger role-specific dashboards.
- Staff dashboard across concurrent productions.
- Attention-needed cards: missing forms, uncovered shifts, conflicts, safeguarding review and unread critical updates.
- Parent multi-child/multi-production summary and personal calendar overview.

## People/family backlog

- Richer profiles and emergency contacts.
- Preferred names/pronouns where appropriate.
- Multiple household/guardian relationship UX.
- Contact preferences and avatar/photo management.
- Guardian-managed student-account UX and credential recovery rules.
- Deliberate policy decision before storing sensitive medical/allergy information.

## Safeguarding backlog

- Broader audit-review tools and incident/report workflow.
- Staff training/credential requirements.
- Permission-review dashboard and policy acknowledgment tracking.
- Safeguarding alerts and retention/export tooling.
- Preserve guardian visibility as a structural server-side rule for safeguarded adult/student messaging.

## Reporting/admin backlog

- Organization-wide audit explorer.
- Volunteer, form, production-participation and attendance reports.
- Data exports and system settings.
- Custom role/permission administration only if CTSMD later needs roles beyond the system role set.
- Season setup and archive/history browser.

## Platform/technical backlog

- Consolidate canonical schema + migration strategy so fresh installs are predictable.
- Break remaining large PHP experience files into smaller services/layouts/partials.
- Incrementally replace legacy per-experience current-user queries with direct `Auth::currentUser()` calls; the session-safe DB compatibility adapter is transitional.
- Storage abstraction and mail delivery service.
- Add authentication rate limiting / lockout policy before public production launch.
- Consider persistent server-side session storage if Bluehost/PHP session behavior requires it.
- Background jobs/cron where appropriate.
- Automated tests, especially auth/RBAC/access regression tests.
- Accessibility review and keyboard/focus polish.
- Mobile polish across staff fallbacks.
- Bluehost deployment process/tooling and backup/restore procedures.
- Timezone-aware date handling, including ICS conversion validation.
- Search repository for remaining MySQL 8 DISTINCT/ORDER BY incompatibilities.
- Fix remaining legacy FormExperience CSRF exception edge.
- Remove remaining hardcoded prototype/domain data from legacy index.php.
- Immutable recipient snapshots for published communications where required.
- Validate database trigger privileges/behavior on Bluehost; retain PHP service equivalents where required.

## Architectural notes

- Multiple productions may be active concurrently.
- Production activity is independent from the per-session working-production selector.
- Authentication identity is browser-session scoped; no shared database current-user mutation is permitted.
- Runtime administrator authorization is role/permission based, not display-label based.
- Production membership and authentication roles are different concepts: a person can participate in a production without gaining administrative permissions.
- General Teams and Production Groups are separate concepts.
- Community channels may be audience-driven, selected-member, Team-backed or hybrid.
- Private Community membership must not bypass safeguarded direct-message rules.
- Community moderation is exception-based; clean posts publish immediately, matched content is intercepted before visibility.
- Historical records should normally be deactivated/archived rather than hard-deleted.
- Domain/demo records belong in the database/seed, never hardcoded into PHP views.
