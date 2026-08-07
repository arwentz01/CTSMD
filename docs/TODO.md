# CTSMD Connect Product Backlog

This document is the canonical product wishlist/backlog for CTSMD Connect. It captures approved future-facing ideas from product discussions so they do not live only in chat history.

**Build principle:** Experience defines the product. Background logic exists to support the approved experience.

## Status key

- **In progress** — current build slice
- **Next** — high-priority near-term build
- **Backlog** — approved direction, not yet scheduled
- **Platform** — foundational technical work that supports multiple features

## Current build

### In progress — Production groups + targeted schedule audiences

- Create production-native groups such as Full Cast, Ensemble, Principals, Tech Crew, Dance Ensemble, Production Staff, etc.
- Keep Production Groups distinct from general-purpose Teams. Groups are show-operational objects; Teams are reusable collaboration groups.
- Add/remove active production members from groups without duplicating production membership.
- Allow a person to belong to multiple groups.
- Allow schedule items to target the full production or one/more Production Groups.
- Automatically include the appropriate guardian visibility for targeted student groups.
- Filter member schedules so users only see production-wide items or group-targeted items relevant to them/their student relationship.
- Use the same resolved audience for schedule communication drafts.
- Preserve group membership and schedule targeting in audit history.
- Future consumers: attendance, forms, resources, Community, call sheets, reports.

## Near-term build order

### Next — Attendance

- Rehearsal/performance attendance tracking.
- Expected attendees derived from schedule audience/group targeting.
- Present, absent, late, excused, left early states.
- Staff notes and audit trail.
- Guardian/student absence-reporting workflow.
- Attendance history and production reports.

### Next — Dynamic forms

- Field builder: short/long text, multiple choice, checkboxes, date/time, acknowledgment, signature, file upload when storage exists.
- Conditional fields.
- Guardian completion on behalf of a student.
- Form/version snapshots so historical submissions retain the exact questions answered.
- Bulk reminders and completion dashboards.
- Production/group audience assignment.
- Automatic credential/readiness updates where appropriate.

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

## Community backlog

- Complete hybrid Channel UI: audience + selected people + Teams together.
- Team owners/roles, ordering and archive management.
- Channel pinning/featured posts.
- Post reactions.
- Moderation tools.
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

- Dynamic form builder.
- Guardian-on-behalf-of-student completion.
- Conditional fields.
- Form versioning/snapshots.
- Production Group targeting.
- Bulk reminders.
- Completion dashboards.
- Credential/readiness automation.
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
- Attendance reports.
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
- Fix remaining FormExperience CSRF exception edge.
- Remove remaining hardcoded prototype/domain data from legacy index.php.
- Immutable recipient snapshots for published communications where required.

## Architectural notes

- Multiple productions may be active concurrently.
- Production activity is independent from the per-session working-production selector.
- General Teams and Production Groups are separate concepts even if they eventually share lower-level membership utilities.
- Community channels may be audience-driven, selected-member, Team-backed or hybrid.
- Private Community membership must not bypass safeguarded direct-message rules.
- Historical records should normally be deactivated/archived rather than hard-deleted.
- Domain/demo records belong in the database/seed, never hardcoded into PHP views.
