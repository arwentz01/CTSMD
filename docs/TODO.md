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

### Implemented — Calendar + schedule lifecycle

- First-class Calendar route at `/calendar` in the Theatre navigation.
- Month, week and 90-day agenda views.
- Consolidated personal schedule across all active productions the current account can access.
- Production filter for narrowing one active show.
- Production Group filter; whole-production calls remain visible when they apply to the selected group.
- Guardian child-focus filter resolves the selected child’s real production/group permissions rather than cosmetically filtering the guardian view.
- Cross-production overlap/conflict warnings run against the actual visible personal event set.
- Cancelled events remain in calendar history and ICS feeds with cancelled state instead of being hard-deleted.
- Staff can duplicate a schedule item seven days forward while preserving production/group targeting.
- Staff can cancel an event from Calendar; cancellation creates a schedule communication draft for the affected audience.
- Per-user revocable private calendar-subscription token.
- ICS feed at `/calendar/feed?token=...` for Apple/Google/Outlook subscription; the UI renders an absolute subscription URL and allows token rotation.
- Calendar feed permissions are resolved from the subscription owner’s current active production/group access each time the feed is requested.
- Calendar lifecycle and subscription schema live in migration 016.
- **Runtime verification:** pending local MAMP test after migration 016, including subscribing to the private ICS URL from an external calendar client.

### Implemented — Volunteer hours, training + credential automation

- Member volunteer-hours history at `/volunteer/history` with verified totals and entry detail.
- Member training/readiness view at `/volunteer/training`.
- Staff Volunteer Development workspace at `/admin/volunteer-development`.
- Durable volunteer hour ledger supports shift-derived and staff-entered verified service.
- Completed volunteer shifts automatically create/update verified service-hour entries; moving a completed signup out of completed status voids the shift-derived hour entry.
- Training modules can be linked to an existing volunteer requirement and optional validity period.
- Staff verifies training completion; members cannot self-award credentials.
- Verified training automatically approves/refreshes the linked volunteer credential and expiration date when configured.
- Approved Forms can be mapped to volunteer requirements so form approval updates readiness automatically.
- Manual verified hours can be entered by staff with date, production context and notes.
- Core automated transitions have database-level safety-net triggers in migration 015.
- **Runtime verification:** pending local MAMP test after migration 015, including MySQL/MariaDB trigger creation.

### Implemented — Dynamic forms

- Staff Form Builder landing page at `/admin/forms/build`.
- Structured field editor at `/admin/forms/builder?id=...`.
- Field types: short text, long text, single choice, multiple choice, date, acknowledgment and typed signature.
- Required/optional fields, help text, stable field keys, ordering and deactivate/reactivate lifecycle.
- Choice options are stored with the field definition and validated server-side.
- Definition version increments whenever structured fields are changed.
- Existing forms without structured fields continue through the legacy Forms experience.
- Submitted answers are stored as field-level records with immutable definition snapshots.
- **Runtime verification:** pending local MAMP test after migration 014.

### Implemented — Attendance

- Attendance workspace at `/attendance` within selected production context.
- Expected attendees derive from schedule audience and Production Groups.
- Guardian schedule visibility does not make guardians expected attendees.
- Staff roll-call workspace at `/attendance/take?id=...`.
- Attendance states: unmarked, present, absent, late, excused and left early.
- Students/guardians can submit eligible absence reports for staff acknowledgment.
- **Runtime verification:** pending local MAMP test after migration 013.

### Implemented — Community moderation

- Admin-managed moderation term library at `/admin/moderation/terms` and queue at `/admin/moderation/queue`.
- Block or hold-for-review actions with exact and controlled normalized/fuzzy matching.
- Clean posts publish immediately; matched content is intercepted before visibility.
- Starter vocabulary lives in `database/seeds/002_moderation_terms.sql` rather than PHP.
- **Runtime verification:** pending local MAMP test after migration 012 and optional seed 002.

### Implemented — Production groups + targeted schedule audiences

- Production-native groups such as Full Cast, Ensemble, Principals and Tech Crew.
- Schedule items may target whole production or one/more Production Groups.
- Guardians inherit appropriate family-facing visibility without becoming group members.
- Schedule notices, Attendance and Calendar use the same resolved audience rules.
- **Runtime verification:** pending local MAMP test after migration 011.

## Near-term build order

### Next — Production authentication + RBAC

- Real login/logout.
- Invitations and account activation.
- Password reset/recovery.
- Normalized roles/permissions instead of parsing display-role strings.
- Staff permission tiers and safeguarding permissions.
- Account deactivation.
- Guardian-managed student-account considerations.

### Next — File uploads/storage

- Storage abstraction that behaves consistently on MAMP and Bluehost/shared hosting.
- Production and organization resource uploads.
- File versioning and archive history.
- Download permission enforcement.
- Image/PDF preview support.
- Attachment foundation for Community, Messages, Forms and Playbills.

### Next — Email notifications

- Email delivery service.
- User notification preferences.
- Per-channel notification settings.
- Form/credential/shift reminders.
- Digest delivery.
- Later: push-notification architecture.

### Next — Parent multi-child / multi-production dashboard

- One family dashboard spanning children and active productions.
- Upcoming calls and schedule changes by child.
- Missing/overdue forms.
- Volunteer commitments.
- Unread communications.
- Conflict and attention-needed cards.

### Next — Public website / registration layer

- Public CTSMD website/CMS bridge.
- Production/audition/event pages.
- Audition and production registration.
- Workshops/camps/classes.
- Public calendar and Playbills.
- News, donations and sponsorship.
- RSVP/waitlist flows.
- Payments later if approved.

## Production operations backlog

- Audition registration and audition-session management.
- Casting workflow.
- Role/character assignment improvements.
- Production checklist/readiness dashboard.
- Call sheets and production-day operational checklist.
- Production group reuse/templates between shows where appropriate.
- Production archive/history browser.
- Season setup and season-level reporting.
- Attendance aggregate reports, trends and exports beyond the per-call dashboard.
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
- Data exports, user/role administration and system settings.
- Season setup and archive/history browser.

## Platform/technical backlog

- Consolidate canonical schema + migration strategy so fresh installs are predictable.
- Break remaining large PHP experience files into smaller services/layouts/partials.
- Replace display-role string parsing with normalized RBAC.
- Production authentication.
- Storage abstraction and mail delivery service.
- Background jobs/cron where appropriate.
- Automated tests.
- Accessibility review and keyboard/focus polish.
- Mobile polish across staff fallbacks.
- Bluehost deployment process/tooling and backup/restore procedures.
- Timezone-aware date handling, including validating ICS conversion against the configured CTSMD timezone.
- Search repository for remaining MySQL 8 DISTINCT/ORDER BY incompatibilities.
- Fix remaining legacy FormExperience CSRF exception edge.
- Remove remaining hardcoded prototype/domain data from legacy index.php.
- Immutable recipient snapshots for published communications where required.
- Validate database trigger privileges/behavior on the eventual Bluehost environment; retain PHP service equivalents where shared-hosting constraints require them.

## Architectural notes

- Multiple productions may be active concurrently.
- Production activity is independent from the per-session working-production selector.
- General Teams and Production Groups are separate concepts even if they eventually share lower-level membership utilities.
- Community channels may be audience-driven, selected-member, Team-backed or hybrid.
- Private Community membership must not bypass safeguarded direct-message rules.
- Community moderation is exception-based; clean posts publish immediately, while matched rules may block or hold a post before visibility.
- Attendance expected rosters derive from schedule targeting and active production membership; guardian visibility does not imply guardian attendance.
- Structured form submissions retain their own definition snapshot/version so historical responses are not rewritten by later form edits.
- Volunteer readiness continues to use the canonical volunteer requirement/credential model; hours, training and approved-form automation feed that model rather than replacing it.
- Calendar is a read/operations layer over canonical schedule items; it does not create a parallel calendar-event domain.
- Private ICS tokens are revocable and must not weaken normal schedule audience rules.
- Historical records should normally be deactivated/archived rather than hard-deleted.
- Domain/demo records belong in the database/seed, never hardcoded into PHP views.
