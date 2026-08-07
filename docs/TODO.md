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

### Implemented — Production readiness + checklist dashboard

- `/production/readiness` is a staff-only Working-Production readiness workspace.
- Readiness remains intentionally scoped to the selected Working Production; `/staff` remains the cross-production operational overview.
- Automated readiness signals currently cover active student/guardian roster coverage, open production form assignments, uncovered upcoming volunteer slots, future active schedule items, draft schedule-change notices, Playbill publication state, and production resource/file counts.
- Automated signals link back to their authoritative management screens rather than duplicating mutation workflows.
- Migration 024 adds `production_checklist_items` for staff-defined production tasks with category, notes, due date/time, status, optional active-production-staff assignee, ordering, creator, completion actor, and completion timestamp.
- Checklist states are `open`, `in_progress`, `blocked`, and `done`; checklist writes are restricted to the current Working Production and audit logged.
- Readiness percentage is deliberately based only on explicit checklist completion. Automated system warnings are not mixed into a fake weighted readiness score.
- Overdue incomplete checklist items are surfaced separately.
- The Production workspace and Operations navigation now link directly to Production Readiness.
- No checklist/demo items are hardcoded in PHP; the production team defines the checklist records it actually needs.
- **Runtime verification:** pending local MAMP test after migration 024, including Working Production isolation, guardian-coverage signal, forms/volunteer/schedule/notices/Playbill signals, checklist creation/completion/reopen, overdue display, staff assignee validation, and audit events.

### Implemented — Staff cross-production dashboard

- `/staff` is now the canonical staff cross-production operations overview.
- Dashboard spans every active production available to staff and does not depend on the selected Working Production for its overview data.
- Working Production remains visible as a separate operations-context card and still controls production editing/management workflows.
- All staff see active productions and the next 21 days of production calls in one chronological view.
- Operational attention cards are permission-aware: staff only sees queues their normalized RBAC permissions authorize them to manage.
- Account managers see verified self-registered accounts still pending CTSMD organization membership approval.
- Forms/registration managers see missing/review/due-soon form counts across active productions and submitted/accepted public registrations that have not yet been linked to canonical CTSMD People/household records.
- Volunteer managers see upcoming shifts below required staffing and pending shift-approval requests.
- Community moderators see the count of Community posts pending moderation review.
- Short membership-review, registration-intake, and volunteer-gap lists link directly into the existing authoritative management screens instead of duplicating mutation workflows on the dashboard.
- Staff Overview is the first Operations navigation destination; `/app` remains the staff member's personal/account-wide Home.
- No new migration was required for this build.
- A safeguarding incident count is intentionally not fabricated because the current safeguarding experience does not yet have a canonical incident/review queue table.
- **Runtime verification:** pending local MAMP test.

### Implemented — Organization-wide member resources

- Migration 023 adds `organization_resources` as the canonical home for CTSMD-wide member material that does not belong to a production.
- Organization resources may be links, text/notes, or downloadable files backed by the existing private `stored_files` / immutable-version storage layer.
- Approved CTSMD organization membership is the member access gate; production membership is not required for CTSMD-wide resources.
- Member `/resources` and `/files` now combine approved organization material with all currently authorized active-production material in one account-wide library.
- Member library filters are organizational only: **All**, **CTSMD**, and active productions. No production switch is required to discover content.
- Staff `Member Resource Operations` is separate from Working-Production `Resource Operations` and `File Operations`.
- **Runtime verification:** pending local MAMP test after migration 023.

### Implemented — DB-backed Home + account-wide member libraries

- `/app` is DB-backed rather than mock-driven.
- Parent/student/volunteer Home aggregates schedule, forms, notifications, volunteer commitments, linked children and active productions across the account rather than requiring a production switch.
- Staff Home remains account-wide and shows Working Production only as a separate operations-context card.
- Member `/files` and `/resources` aggregate accessible content across all active productions with optional filters.
- **Runtime verification:** pending local MAMP test.

### Implemented — Registration intake follow-through

- Migration 022 adds `registration_submission_links`, preserving the reviewed connection between a public signup and canonical CTSMD people/household records.
- Registration Operations provides a dedicated Intake Review screen for each registrant.
- Staff may explicitly link a registration to existing CTSMD people or create/reuse reviewed household records without silently granting production membership.
- Accepted, waitlisted, declined and cancelled staff status changes queue family-facing email updates when the status actually changes.
- Audition-session/time-slot scheduling remains intentionally deferred until CTSMD's real audition workflow demonstrates the needed slot model.
- Classes, camps and workshops remain in the existing external registration system.
- **Runtime verification:** pending local MAMP test after migration 022.

### Implemented — Platform registration + household onboarding

- Public `/join` is the primary CTSMD Connect account-creation path for adults/guardians.
- Email verification activates sign-in but leaves CTSMD organization membership pending staff approval.
- Approved organization membership, authentication, and production membership remain separate concepts.
- Parents/guardians create child profiles through Household Setup / Manage Household; child creation and guardian relationship are transactional.
- Creating a child profile does not add the child to a production.
- **Runtime verification:** pending local MAMP test after migration 021.

### Implemented — Lean public landing + restrained registration

- `/` remains a deliberately small public CTSMD Connect landing page rather than a full replacement for the existing CTSMD website.
- `/register` is intentionally limited to auditions, selected special/event signups, and interest/RSVP signups.
- Classes, camps, workshops and the broader program catalog remain in the existing external registration system until CTSMD intentionally chooses to replace it.
- Public signup intake remains separate from CTSMD Connect account creation.
- Registration schema lives in migration 020.
- **Runtime verification:** pending local MAMP test.

## Near-term build order

### Next — Call sheets + production-day operations

- Build a Working-Production day-of-operations workspace from existing schedule, Production Groups, attendance, volunteer coverage, notices and readiness data.
- Allow staff to open a rehearsal/performance date and see the run of day in chronological order with locations, targeted groups, expected attendees and operational notes.
- Add a first-class call-sheet experience that can be reviewed before publication rather than requiring staff to assemble the same information manually from several screens.
- Keep member schedule/calendar as the authoritative personal discovery surface; production-day operations is a staff execution tool.
- Avoid creating a second attendance or volunteer system: deep-link to existing authoritative workflows where action is required.

### Next — Registration operations later follow-through

- Audition-session/time-slot management only when CTSMD defines the real audition-slot workflow.
- Registration-specific questionnaires may reuse Dynamic Forms where appropriate.
- CSV/export/reporting once real registration operations demonstrate what fields staff actually need.
- Do not expand into class/camp/workshop registration unless CTSMD explicitly decides to replace the current external registration system.

## Future public-site options — only if CTSMD chooses to expand Connect

- Full public CTSMD website/CMS bridge.
- Public production/audition/event detail pages beyond the lean registration page.
- Class/camp/workshop catalog and registration only as an intentional replacement project for the current external system.
- Broad public calendar, news, donations, sponsorship and RSVP experiences.
- Payments only after actual program/payment requirements are defined.

## Future product slices

### Backlog — Production archive + My Theatre History

- Deactivating or archiving a production removes its production-owned channels and operational content from normal active member views without deleting history.
- Archived production channels move to a clearly designated read-only archive rather than remaining mixed into current Community.
- Production-owned schedules, files/resources, notices, roster credits, Playbill data and relevant communication history remain preserved and viewable according to historical access/safeguarding rules.
- Account-wide direct messages are not automatically hidden just because a production closes.
- Student accounts receive a **My Theatre History** area showing verified CTSMD production credits, including production, season/year, character/role, cast/crew participation and Production Group involvement where appropriate.
- Guardian accounts can view theatre history for linked students.
- Volunteer accounts receive a service-history version showing productions served, volunteer roles/categories, verified hours, training/credentials and leadership/coordinator service where recorded.
- Future **Generate Acting Résumé** workflow can build PDF/DOCX résumé output from verified CTSMD credits plus optional external credits, training and special skills.
- Future volunteer-history exports may support school/community-service verification and recognition.
- Historical production records are archival records, not hard-deleted domain data.

## Production operations backlog

- Audition-session management when needed.
- Casting workflow and role/character assignment improvements.
- Call sheets and production-day operational checklist beyond the first day-of workspace.
- Production group reuse/templates between shows where appropriate.
- Production archive/history browser.
- Season setup and season-level reporting.
- Attendance aggregate reports, trends and exports.
- Rich recurring-schedule rules beyond duplicate +7 days.
- Readiness checklist templates/reuse only if real CTSMD workflows demonstrate useful repeatable patterns.

## Community backlog

- Complete hybrid Channel UI: audience + selected people + Teams together.
- Team owners/roles, ordering and archive management.
- Channel pinning/featured posts and reactions.
- Moderation rule refinements and false-positive tuning.
- Search across Community posts.
- Community photo/file attachments using the shared storage layer.
- First-class announcement composer separate from schedule-generated notices.
- Per-channel email/notification preferences and announcement-specific delivery controls.
- Account-wide Community remains the default; production filters organize channels but must not hide unread activity behind a required production switch.
- Organization-wide Community channels require approved CTSMD organization membership; production channels require their own production/access rules.
- Archived production channels should leave the active Community list and remain available in a designated read-only archive.

## Messaging backlog

- Participant management where safeguarding rules permit it.
- Better guardian selection when a student has multiple guardians.
- Conversation search and unread state.
- Message attachments using the shared storage layer, with safeguarding-aware download access.
- Staff escalation/reporting workflow.
- Safeguarded group conversations where product rules allow them.
- Messages and unread state remain account-wide for all users, including staff; Working Production must never filter the inbox.

## Forms backlog

- Conditional fields.
- Guardian-on-behalf-of-student completion.
- Production Group targeting.
- Bulk reminders and completion dashboards beyond automated due-soon email.
- File-upload fields using the shared storage layer.
- Registration-oriented form extensions when a Connect-managed audition or special signup genuinely needs more than the lean intake fields.
- External/e-sign provider evaluation only if required later.

## Volunteer backlog

- Shift duplication/recurring shifts.
- Waitlists and staff manual assignment.
- Background-check administration workflow improvements.
- Training content delivery beyond staff verification.
- Coordinator aggregate dashboards and roster/service-hour exports.
- Volunteer service goals/recognition if desired later.
- My Theatre History/service record for verified production participation, volunteer roles and hours.

## Resources backlog

- Narrower organization-resource audiences only if real CTSMD content needs them; current organization resources intentionally target all approved members.
- Image/PDF inline preview after browser/content-security behavior is fully tested.
- Group-targeted production files/resources.
- More detailed organization/production resource view/download reporting where needed.
- Future remote/object-storage driver only if CTSMD outgrows shared-hosting filesystem storage.

## Playbill backlog

- Headshots/photos and actor/staff bios using the shared storage layer.
- Sponsor logos/ads and reusable sponsor management.
- Production artwork and drag/reorder sections.
- Credits beyond active production membership.
- Public QR sharing and print-friendly/PDF output.
- Reuse verified Playbill/production credits as a source for My Theatre History where appropriate.

## Notifications backlog

- Wire schedule-change publishing directly to optional targeted email delivery.
- Per-channel notification/email preferences.
- Full daily digest composer/worker for digest-enabled users; preference storage already exists.
- Additional reminder types as real operations identify them.
- Push notifications later.
- Notifications remain account-wide and must not be hidden by staff Working Production context.

## Home/dashboard backlog

- Expand staff triage with canonical safeguarding/incident counts after a safeguarding review domain exists.
- Extend family logistics to include guardian volunteer-shift vs child-call collision warnings if useful in testing.

## People/family backlog

- Richer profiles and emergency contacts.
- Preferred names/pronouns where appropriate.
- Multiple household/guardian relationship UX.
- Contact preferences and avatar/photo management using shared storage.
- Guardian-managed student-account UX and credential recovery rules for when CTSMD chooses to give a managed child independent login credentials.
- Guardian-on-behalf-of-student form completion policy/UX.
- Guardian access to linked-student Theatre History.
- Deliberate policy decision before storing sensitive medical/allergy information.

## Safeguarding backlog

- Broader audit-review tools and incident/report workflow.
- Canonical safeguarding incident/review queue that can feed staff attention surfaces according to safeguarding permission.
- Staff training/credential requirements.
- Permission-review dashboard and policy acknowledgment tracking.
- Safeguarding alerts and retention/export tooling.
- Preserve guardian visibility as a structural server-side rule for safeguarded adult/student messaging.
- Production archival must preserve safeguarding and audit records even when content leaves active member navigation.

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
- Add authentication/self-registration rate limiting and abuse protection before public production launch.
- Improve duplicate-account recovery so an existing invited/active email gets a helpful activation/sign-in/recovery path without leaking account existence.
- Consider persistent server-side session storage if Bluehost/PHP session behavior requires it.
- Validate Bluehost/PHP upload limits (`upload_max_filesize`, `post_max_size`) and write permissions for the configured `STORAGE_PATH`.
- Validate Bluehost cron availability, PHP CLI path and authenticated SMTP settings; configure queue/reminder workers in deployment.
- Automated tests, especially auth/RBAC/storage/email/registration/membership/access regression tests.
- Accessibility review and keyboard/focus polish.
- Mobile polish across staff fallbacks.
- Bluehost deployment process/tooling and backup/restore procedures, including private-file backups.
- Timezone-aware date handling, including ICS conversion and reminder-worker validation.
- Search repository for remaining MySQL 8 DISTINCT/ORDER BY incompatibilities.
- Fix remaining legacy FormExperience CSRF exception edge.
- Remove remaining hardcoded prototype/domain data from legacy index.php.
- Immutable recipient snapshots for published communications where required.
- Validate database trigger privileges/behavior on Bluehost; retain PHP service equivalents where required.

## Architectural notes

- Multiple productions may be active concurrently.
- Production activity is independent from the per-session Working Production selector.
- **Working Production context is for staff/admin production operations only.** Parents, students and ordinary volunteers should not need to switch productions to discover current obligations or communication.
- **Account scope is authoritative for member experience:** Home, Family, Calendar, Community, Messages, Notifications, assigned Forms, personal Volunteer activity and member files/resources aggregate everything the account is permitted to see across active productions and the approved organization layer.
- **Staff operational overview is also organization scoped:** `/staff` aggregates active productions and permitted operational queues, while Working Production remains the editing context for one show.
- **Production readiness is Working-Production scoped:** it evaluates one show's operational state and checklist without affecting account-wide communication or staff overview behavior.
- Staff/admin accounts participate in both scopes: production operations may be scoped to the selected Working Production, while Messages, Community, Notifications, personal Calendar, unread state and `/staff` remain account-wide/cross-production.
- Production/library filters are organizational filters only; they must never function as required context gates that can hide unread or actionable information.
- Authentication identity is browser-session scoped; no shared database current-user mutation is permitted.
- Runtime administrator authorization is role/permission based, not display-label based.
- Authentication, organization membership and production membership are three separate concepts.
- A verified self-registered account starts with organization membership `pending`; email verification alone does not grant general Community or organization-resource access.
- Organization approval unlocks general member Community and organization-wide member resources without enrolling the person in any production.
- Active production membership grants show-specific access according to production/audience/group rules and does not require a member-facing production context switch.
- Students/minors do not anonymously self-register. Student profile creation is guardian-mediated and must preserve family/safeguarding rules.
- A family dashboard resolves each linked student's current permissions independently; guardian visibility never substitutes for or broadens the student's own production/group schedule access.
- Public audition/special-signup intake and authenticated platform membership are separate lifecycles. Registration may be explicitly linked/converted into canonical people/household records only through staff review; it never silently grants membership or production access.
- Stored files and their immutable versions are infrastructure objects; production files and organization resources are permissioned domain objects that reference them.
- Private files are never exposed by direct storage URLs; every download re-checks current CTSMD authorization.
- Outbound email is queue-first; web workflows enqueue messages and CLI/cron workers perform transport delivery.
- Account-security mail is transactional and cannot be disabled by ordinary notification preferences.
- General Teams and Production Groups are separate concepts.
- Community channels may be audience-driven, selected-member, Team-backed or hybrid.
- Private Community membership must not bypass safeguarded direct-message rules.
- Community moderation is exception-based; clean posts publish immediately, matched content is intercepted before visibility.
- When a production becomes inactive/archived, production-owned active experiences should leave current member navigation while their historical records remain preserved in read-only/archive experiences as appropriate.
- Historical records should normally be deactivated/archived rather than hard-deleted.
- Domain/demo records belong in the database/seed, never hardcoded into PHP views.
