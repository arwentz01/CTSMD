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

### Implemented — Dynamic forms

- Staff Form Builder landing page at `/admin/forms/build`.
- Structured field editor at `/admin/forms/builder?id=...`.
- Field types: short text, long text, single choice, multiple choice, date, acknowledgment and typed signature.
- Required/optional fields, help text, stable field keys, ordering and deactivate/reactivate lifecycle.
- Choice options are stored with the field definition and validated server-side.
- Definition version increments whenever structured fields are changed.
- Existing forms without structured fields continue through the legacy Forms experience.
- Structured assignments automatically render through the dynamic member form experience.
- Submitted answers are stored as field-level records rather than one response-text blob.
- Each structured submission stores the form-definition version and immutable definition snapshot used for that response.
- Later edits to field labels/options do not rewrite historical submitted answers/snapshots.
- Structured forms continue using the existing assignment, due-date, review-required, approval/return and notification workflows.
- Staff review shows each submitted structured answer by its submitted label/type.
- Dynamic form definition/submission/review writes are audited.
- File-upload fields remain deferred until the shared storage layer exists.
- Conditional fields, guardian-on-behalf-of-student completion, Production Group targeting, bulk reminders/completion dashboards and credential automation remain future extensions.
- **Runtime verification:** pending local MAMP test after migration 014.

### Implemented — Attendance

- Attendance workspace at `/attendance` within the selected production context.
- Expected attendees are derived from the schedule audience and Production Groups rather than maintained in a duplicate roster.
- Guardian schedule visibility does not make guardians expected attendees; expected attendance excludes guardian-only membership records.
- Staff roll-call workspace at `/attendance/take?id=...`.
- Attendance states: unmarked, present, absent, late, excused and left early.
- Optional staff notes and marker/timestamp history.
- Per-schedule summary counts for expected, present, absent/excused and unmarked participants.
- Students may report their own absence when they are expected for a call.
- Guardians may report an absence only for an actively related student who is expected for that call.
- Family absence reports do not silently alter attendance; production staff acknowledges the report, which marks the student excused and records the decision.
- Acknowledgment fails closed if the student is no longer part of the schedule item's current expected audience.
- All write actions are production-context checked and audited.
- Attendance is surfaced from the Production workspace and staff Operations navigation.
- **Runtime verification:** pending local MAMP test after migration 013.

### Implemented — Community moderation

- Admin-managed moderation term library at `/admin/moderation/terms`.
- Rules can be activated/deactivated without deployment or hardcoded PHP changes.
- Rule action may be **Block immediately** or **Hold for review**.
- Exact or controlled normalized/fuzzy matching.
- Normalization handles case, common character substitutions such as `@ -> a`, `$ -> s`, `0 -> o`, and punctuation/spacing between letters.
- Optional aliases per canonical term.
- Starter profanity/slur vocabulary lives in `database/seeds/002_moderation_terms.sql`, not application code, and does not overwrite later administrator changes.
- Clean Community posts publish immediately; there is no universal pre-approval workflow.
- Review matches remain private until a moderator approves them.
- Block matches remain private and are retained for audit/moderation history.
- Community feed/count queries only include `published` posts.
- Moderator queue at `/admin/moderation/queue` shows matched rule/category/severity and the original submitted text.
- Moderators approve the original text unchanged or reject it; CTSMD does not silently rewrite author content.
- Ordinary users are not told the exact term/rule that triggered moderation.
- If moderation evaluation fails, posting fails closed rather than bypassing the filter.
- Applies to audience, selected-member, Team, and hybrid Community channels through the shared post pipeline.
- Changes and moderation decisions are written to the audit trail.
- **Runtime verification:** pending local MAMP test after migration 012 and optional starter seed 002.

### Implemented — Production groups + targeted schedule audiences

- Production-native groups such as Full Cast, Ensemble, Principals, Tech Crew, Dance Ensemble, Production Staff, etc.
- Production Groups remain distinct from general-purpose Teams. Groups are show-operational objects; Teams are reusable collaboration groups.
- Add/remove active production members from groups without duplicating production membership.
- A person may belong to multiple groups.
- Schedule items can target the full production or one/more Production Groups.
- Appropriate guardians automatically inherit family-facing calls for targeted student groups.
- Member schedules only show production-wide items or group-targeted items relevant to them/their student relationship.
- Schedule communication drafts resolve through the same production/group audience rules.
- Group membership and schedule targeting are retained in audit history.
- Inactive groups fail closed for schedule visibility/audience resolution.
- Group-targeted notice publishing is restricted to targeted in-app delivery in the schedule workflow; a broad Community channel cannot accidentally expose a narrow call.
- Future consumers: attendance, forms, resources, Community, call sheets, reports.
- **Runtime verification:** pending local MAMP test after migration 011.

## Near-term build order

### Next — Volunteer hours, training + credential automation

- Volunteer-hour ledger and service-hour reports.
- Shift duplication/recurring shifts.
- Waitlists and staff manual assignment.
- Training module completion tracking.
- Background-check workflow improvements.
- Credential expiration reminders.
- Automatic eligibility updates from approved forms/training.
- Volunteer coordinator dashboard and exportable staffing rosters.

### Next — Calendar

- Month/week/agenda views.
- Consolidated personal calendar across concurrent productions.
- Production/group/child filters.
- Conflict detection across concurrent productions.
- ICS export/subscription for Apple/Google/Outlook calendars.
- Schedule cancellation/archive lifecycle.
- Repeat/duplicate schedule items.

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
- Public calendar.
- Public Playbills.
- News, donations and sponsorship.
- RSVP/waitlist flows.
- Payments later if approved.

## Production operations backlog

- Audition registration and audition-session management.
- Casting workflow.
- Role/character assignment improvements.
- Production checklist/readiness dashboard.
- Call sheets.
- Production-day operational checklist.
- Production group reuse/templates between shows where appropriate.
- Production archive/history browser.
- Season setup and season-level reporting.
- Cross-production conflict detection.
- Attendance aggregate reports, trends and exports beyond the per-call operational dashboard.

## Community backlog

- Complete hybrid Channel UI: audience + selected people + Teams together.
- Team owners/roles, ordering and archive management.
- Channel pinning/featured posts.
- Post reactions.
- Moderation rule refinements: more organization-specific categories/aliases and false-positive tuning as real usage teaches us where needed.
- Search across Community posts.
- Attachments/photos after storage is available.
- First-class announcement composer separate from schedule-generated notices.
- Better channel notification preferences.

## Messaging backlog

- Participant management where safeguarding rules permit it.
- Better guardian selection when a student has multiple guardians.
- Conversation search.
- Unread counts and read state.
- Attachments after storage is available.
- Staff escalation/reporting workflow.
- Safeguarded group conversations where product rules allow them.

## Forms backlog

- Conditional fields.
- Guardian-on-behalf-of-student completion.
- Production Group targeting.
- Bulk reminders.
- Completion dashboards.
- Credential/readiness automation.
- File-upload fields after storage exists.
- Registration-oriented forms.
- External/e-sign provider evaluation only if required later.

## Volunteer backlog

- Recurring/duplicated shifts.
- Waitlists.
- Manual assignment.
- Volunteer hours/service reporting.
- Credential expiration workflows.
- Training content/completion.
- Background-check administration.
- Coordinator dashboards.
- Roster exports.
- Automated eligibility from forms/training.

## Resources backlog

- Actual file uploads/storage.
- Organization-wide resources in addition to production-scoped resources.
- Resource version history.
- Preview/render support for common document formats.
- Group-targeted resources.
- Download/view audit where needed.

## Playbill backlog

- Headshots/photos.
- Actor/staff bios.
- Sponsor logos and ads.
- Production artwork.
- Drag/reorder sections.
- Credits beyond active production membership.
- Director/staff bios.
- Public QR sharing.
- Print-friendly/PDF Playbill output.
- Reusable sponsor management across productions.

## Notifications backlog

- Email delivery.
- Push notifications later.
- Notification preferences.
- Per-channel preferences.
- Digest mode.
- Scheduled reminders.
- Credential expiration reminders.
- Form due-date reminders.
- Volunteer-shift reminders.

## Home/dashboard backlog

- Stronger role-specific dashboards.
- Staff dashboard across concurrent productions.
- Attention-needed cards: missing forms, uncovered shifts, conflicts, safeguarding review, unread critical updates.
- Parent multi-child/multi-production summary.
- Personal calendar overview.
- Better empty/error/no-permission states.

## People/family backlog

- Richer profiles.
- Emergency contacts.
- Preferred names/pronouns where appropriate.
- Multiple household/guardian relationship UX.
- Contact preferences.
- Avatar/photo management.
- Deliberate policy decision before storing sensitive medical/allergy information.

## Safeguarding backlog

- Broader audit-review tools.
- Incident/report workflow.
- Staff training/credential requirements.
- Permission-review dashboard.
- Policy acknowledgment tracking.
- Safeguarding alerts.
- Retention/export tooling.
- Preserve guardian visibility as a structural server-side rule for safeguarded adult/student messaging.

## Reporting/admin backlog

- Organization-wide audit explorer.
- Volunteer reports.
- Form completion reports.
- Production participation reports.
- Attendance aggregate/export reports.
- Data exports.
- User/role administration.
- System settings.
- Season setup.
- Archive/history browser.

## Platform/technical backlog

- Consolidate canonical schema + migration strategy so fresh installs are predictable.
- Break remaining large PHP experience files into smaller services/layouts/partials.
- Replace display-role string parsing with normalized RBAC.
- Production authentication.
- Storage abstraction.
- Mail delivery service.
- Background jobs/cron.
- Automated tests.
- Accessibility review and keyboard/focus polish.
- Mobile polish across staff fallbacks.
- Bluehost deployment process/tooling.
- Backup/restore procedures.
- Timezone-aware date handling.
- Search repository for remaining MySQL 8 DISTINCT/ORDER BY incompatibilities.
- Fix remaining legacy FormExperience CSRF exception edge.
- Remove remaining hardcoded prototype/domain data from legacy index.php.
- Immutable recipient snapshots for published communications where required.

## Architectural notes

- Multiple productions may be active concurrently.
- Production activity is independent from the per-session working-production selector.
- General Teams and Production Groups are separate concepts even if they eventually share lower-level membership utilities.
- Community channels may be audience-driven, selected-member, Team-backed or hybrid.
- Private Community membership must not bypass safeguarded direct-message rules.
- Community moderation is exception-based; clean posts publish immediately, while matched rules may block or hold a post before it becomes visible.
- Attendance expected rosters derive from schedule targeting and active production membership; guardian visibility does not imply guardian attendance.
- Structured form submissions retain their own definition snapshot/version so historical responses are not rewritten by later form edits.
- Historical records should normally be deactivated/archived rather than hard-deleted.
- Domain/demo records belong in the database/seed, never hardcoded into PHP views.
