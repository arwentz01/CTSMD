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

### Implemented — Lean public landing + registration

- `/` is now a deliberately small public CTSMD Connect landing page rather than a full replacement for the existing CTSMD website.
- Public landing focuses on the pieces Connect currently needs outside authentication: published registration opportunities, current digital Playbills and member sign-in.
- `/register` lists only registration opportunities explicitly published by authorized CTSMD staff and currently within their open/close window.
- Public registration opportunities support audition, workshop, camp, class, event and interest types; optional production relationship; dates/location; registration window; capacity; waitlist behavior; confirmation text; and draft/published/closed/archived lifecycle.
- Public registration intentionally collects a narrow data set: participant name, broad age group, contact information, guardian contact for minors and an optional operational note.
- Public registration does **not** collect date of birth, medical history, school or other sensitive data merely because it might be useful later.
- A parent/guardian name and valid guardian email are required when the participant is under 18.
- Capacity is enforced transactionally; registrations submitted after active capacity is reached enter the waitlist rather than overbooking.
- Each registration receives a private random manage token; only its SHA-256 hash is stored. Confirmation email provides a private manage/cancel link.
- Registration confirmation uses the existing outbound email queue and therefore follows the same local-log/SMTP deployment model as other CTSMD email.
- Public cancellation preserves the registration record and changes lifecycle state instead of deleting history.
- Staff Registration Operations workspace at `/admin/registrations` supports creating/editing opportunities, publishing/closing them, reviewing registrants, and changing submission status among submitted, waitlisted, accepted, declined and cancelled.
- Registration Operations currently uses `forms.manage` authorization rather than adding a new one-off RBAC permission before CTSMD demonstrates a need for separate registration administrators.
- Registration schema lives in migration 020.
- This build is intentionally **not** a CMS/news/donations/payments/public-site takeover. Those remain future options only if CTSMD chooses to expand Connect into the primary public website.
- **Runtime verification:** pending local MAMP test after migration 020, including public root routing, opportunity publication windows, minor guardian validation, capacity/waitlist behavior, email confirmation/manage URL and public cancellation.

### Implemented — Parent multi-child / multi-production dashboard

- Real database-backed family control tower at `/family-hub`, with `/parent` as an equivalent entry route.
- Replaces the old mock-data Family Hub while leaving the existing `/app` Today experience intact for a later DB-backed home rebuild.
- Guardian-to-student relationships come from active `family_relationships`; no child/domain records are hardcoded in PHP.
- Each linked student independently resolves active production memberships, Production Group-aware schedule visibility and upcoming calls through the same Calendar/ScheduleAudience rules used by that student account.
- Family schedule consolidates linked children across concurrent active productions while preserving child and production labels.
- Per-child cards show active productions/participation roles, next call, open-form count and overlapping-call count.
- Open forms aggregate the guardian's own assignments plus linked-student assignments and retain person/production context.
- Guardian volunteer commitments are shown separately from child obligations so volunteer work is not misrepresented as a student call.
- Household logistics conflict detection highlights simultaneous calls for different children at different locations while avoiding false alarms for the same event or same-location calls.
- Recent guardian-targeted in-app notifications and unread count appear in the family control tower.
- Sidebar adds a Family destination only when the signed-in account has at least one active guardian-to-student relationship.
- No new migration is required; the build composes existing family, production, schedule, form, volunteer and notification data.
- **Runtime verification:** pending local MAMP test with a guardian linked to multiple students across concurrent productions, including Production Group targeting and overlapping calls.

### Implemented — Email notifications + delivery queue

- Durable outbound `email_queue` plus delivery-attempt history in migration 019.
- Queue-first delivery separates web actions from transport reliability; browser requests do not need to complete an SMTP transaction before succeeding.
- Transport abstraction supports `log`, PHP `mail()` and authenticated SMTP with TLS/SSL options.
- Local development defaults to `MAIL_DRIVER=log`; rendered outbound mail is written to `storage/logs/mail.log` instead of contacting real recipients.
- Account invitations now automatically queue the private activation link for email delivery while retaining the one-time admin copy link for recovery/testing.
- Forgot Password now automatically queues the two-hour reset link while preserving a local-only visible reset link for MAMP testing.
- Account-security email cannot be disabled by ordinary notification preferences.
- Member notification settings at `/notification-preferences` cover the non-security master switch plus schedule, forms, volunteer and Community email categories.
- Delivery preference data includes an immediate/daily digest setting so digest-capable workflows can adopt it without another preference migration.
- Automated reminder generator queues form-due-soon reminders, next-day volunteer-shift reminders, and 30-/7-day credential-expiration reminders using deduplication keys.
- CLI queue worker at `bin/process-email-queue.php`; reminder generator at `bin/queue-email-reminders.php`.
- Queue delivery retries up to three attempts, delays retries, records the latest error and reclaims interrupted `sending` records after ten minutes.
- Administrator Email Operations workspace at `/admin/email` shows queued/sent/failed state and recent delivery results, and provides controlled manual reminder/worker actions for testing.
- Shared-hosting/cron and SMTP configuration guidance lives in `docs/EMAIL_DELIVERY.md`.
- **Runtime verification:** pending local MAMP test after migration 019, including log delivery, invitation/reset mail, reminder deduplication, preference suppression, retry behavior, and eventual Bluehost SMTP/cron verification.

### Implemented — File uploads + portable storage

- Generic storage layer in `StorageService` with local private-filesystem driver as the first implementation.
- Default storage path is `<project>/storage/private`; `STORAGE_PATH` can point to an absolute location outside the public web root on shared hosting without changing application code.
- Direct Apache access to the default private storage directory is denied; files are served through authenticated CTSMD routes only.
- Upload validation uses server-detected MIME type rather than trusting the browser filename/content-type.
- Initial allowlist: PDF, Word, Excel, PowerPoint, TXT, CSV, JPG, PNG and WebP. SVG, PHP/executable and arbitrary web-file uploads are not permitted.
- Default maximum upload size is 20 MB and can be changed with `STORAGE_MAX_UPLOAD_MB`.
- Files receive random storage keys rather than user-controlled filesystem names.
- Every stored version records original filename, MIME type, extension, size, SHA-256 checksum, uploader and timestamp.
- Stable `stored_files` objects can have multiple immutable `stored_file_versions`.
- Production file library at `/files`; authorized staff management at `/admin/files`.
- Production files support title, category, description, pinning, archive/restore and the same production audience concepts used by Resources.
- Replacing a production file creates a new version instead of overwriting history.
- Current or historical versions can only be downloaded after current production/audience access is checked server-side.
- Downloads are audited by production file and version.
- Archived production files remain in history and are hidden from normal members.
- Storage schema lives in migration 018.
- Foundation is now available for future Community/Message attachments, Form upload fields and Playbill imagery without duplicating upload/security code.
- **Runtime verification:** pending local MAMP test after migration 018, including PHP upload limits, writable storage path, MIME detection, version replacement and permission-denied downloads.

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

### Next — DB-backed Home + account-wide member aggregation

- Replace the remaining mock-driven `/app` Today screen with real account data.
- Parent/student/volunteer Home aggregates all current obligations across active productions rather than relying on a working-production context.
- Convert remaining member-facing selected-production assumptions—especially Files/Resources and any Production/Schedule shortcuts—into account-wide views with optional production filters.
- Keep staff/admin working-production context for actual production operations only.
- Preserve Messages, Community, Notifications, unread state and personal Calendar as account-wide for staff as well.
- Add clear production labels to aggregated member content so people can tell which show an item belongs to without switching contexts.

### Next — Registration operations follow-through

- Audition-session/time-slot management when CTSMD needs it.
- Convert accepted public registrations into existing people/family/account records through an explicit reviewed workflow rather than silently creating accounts.
- Registration-specific questionnaires can later reuse Dynamic Forms where appropriate.
- Status-change email for accepted/waitlisted/declined registrations.
- CSV/export/reporting once real registration operations demonstrate what fields staff actually need.

## Future public-site options — only if CTSMD chooses to expand Connect

- Full public CTSMD website/CMS bridge.
- Public production/audition/event detail pages beyond the lean registration page.
- Workshops/camps/classes catalog, broad public calendar, news, donations, sponsorship and RSVP experiences.
- Payments only after actual program/payment requirements are defined.
- Do not build these merely because Connect technically can; the existing CTSMD website may remain the public website indefinitely.

## Future product slices

### Backlog — Production archive + My Theatre History

- Deactivating or archiving a production removes its production-owned channels and operational content from normal active member views without deleting history.
- Archived production channels move to a clearly designated read-only archive rather than remaining mixed into current Community.
- Production-owned schedules, files/resources, notices, roster credits, Playbill data and relevant communication history remain preserved and viewable according to historical access/safeguarding rules.
- Account-wide direct messages are not automatically hidden just because a production closes; any future production-specific conversation archival must be explicit and must preserve safeguarding/audit history.
- Student accounts receive a **My Theatre History** area showing verified CTSMD production credits, including production, season/year, character/role, cast/crew participation and Production Group involvement where appropriate.
- Guardian accounts can view theatre history for linked students.
- Volunteer accounts receive a service-history version showing productions served, volunteer roles/categories, verified hours, training/credentials and leadership/coordinator service where recorded.
- Theatre history should distinguish CTSMD-verified records from any future user-entered external credits.
- Future **Generate Acting Résumé** workflow can build PDF/DOCX résumé output from verified CTSMD credits plus optional external credits, training and special skills.
- Future volunteer-history exports may support school/community-service verification and recognition.
- Historical production records are archival records, not hard-deleted domain data.

## Production operations backlog

- Audition registration and audition-session management beyond the public registration intake now implemented.
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
- Community photo/file attachments using the shared storage layer.
- First-class announcement composer separate from schedule-generated notices.
- Per-channel email/notification preferences and announcement-specific delivery controls.
- Account-wide Community remains the default; production filters organize channels but must not hide unread activity behind a required production switch.
- Archived production channels should leave the active Community list and remain available in a designated read-only archive.

## Messaging backlog

- Participant management where safeguarding rules permit it.
- Better guardian selection when a student has multiple guardians.
- Conversation search and unread state.
- Message attachments using the shared storage layer, with safeguarding-aware download access.
- Staff escalation/reporting workflow.
- Safeguarded group conversations where product rules allow them.
- Messages and unread state remain account-wide for all users, including staff; working-production context must never filter the inbox.

## Forms backlog

- Conditional fields.
- Guardian-on-behalf-of-student completion.
- Production Group targeting.
- Bulk reminders and completion dashboards beyond automated due-soon email.
- File-upload fields using the shared storage layer.
- Registration-oriented form extensions when a registration genuinely needs more than the lean intake fields.
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

- Merge or visually unify link/note Resources and Production Files where the product experience benefits from one library.
- Organization-wide files/resources in addition to production-scoped content.
- Image/PDF inline preview after browser/content-security behavior is fully tested.
- Group-targeted files/resources.
- More detailed view/download reporting where needed.
- Future remote/object-storage driver only if CTSMD outgrows shared-hosting filesystem storage.
- Convert member-facing file/resource views to account-wide aggregation across active productions with optional filters instead of requiring a production context switch.

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
- Notifications remain account-wide and must not be hidden by staff working-production context.

## Home/dashboard backlog

- Staff dashboard across concurrent productions after member Home is fully DB-backed.
- Attention-needed cards: missing forms, uncovered shifts, conflicts, safeguarding review and unread critical updates.
- Extend family logistics to include guardian volunteer-shift vs child-call collision warnings if useful in testing.

## People/family backlog

- Richer profiles and emergency contacts.
- Preferred names/pronouns where appropriate.
- Multiple household/guardian relationship UX.
- Contact preferences and avatar/photo management using shared storage.
- Guardian-managed student-account UX and credential recovery rules.
- Guardian-on-behalf-of-student form completion policy/UX.
- Guardian access to linked-student Theatre History.
- Deliberate policy decision before storing sensitive medical/allergy information.

## Safeguarding backlog

- Broader audit-review tools and incident/report workflow.
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
- Add authentication rate limiting / lockout policy before public production launch.
- Consider persistent server-side session storage if Bluehost/PHP session behavior requires it.
- Validate Bluehost/PHP upload limits (`upload_max_filesize`, `post_max_size`) and write permissions for the configured `STORAGE_PATH`.
- Add remote/object-storage driver only if shared-hosting filesystem constraints require one later.
- Validate Bluehost cron availability, PHP CLI path and authenticated SMTP settings; configure queue/reminder workers in deployment.
- Automated tests, especially auth/RBAC/storage/email/registration/access regression tests.
- Accessibility review and keyboard/focus polish.
- Mobile polish across staff fallbacks.
- Bluehost deployment process/tooling and backup/restore procedures, including private-file backups.
- Timezone-aware date handling, including ICS conversion and reminder-worker validation.
- Search repository for remaining MySQL 8 DISTINCT/ORDER BY incompatibilities.
- Fix remaining legacy FormExperience CSRF exception edge.
- Remove remaining hardcoded prototype/domain data from legacy index.php.
- Immutable recipient snapshots for published communications where required.
- Validate database trigger privileges/behavior on Bluehost; retain PHP service equivalents where required.
- Convert remaining member-facing selected-production screens to account-wide aggregation; the working-production session is an operations context, not a member navigation requirement.

## Architectural notes

- Multiple productions may be active concurrently.
- Production activity is independent from the per-session working-production selector.
- **Working-production context is for staff/admin production operations only.** Parents, students and ordinary volunteers should not need to switch productions to discover current obligations or communication.
- **Account scope is authoritative for member experience:** Home, Family, Calendar, Community, Messages, Notifications, assigned Forms, personal Volunteer activity and future member file/resource views aggregate everything the account is permitted to see across active productions.
- Staff/admin accounts participate in both scopes: production operations may be scoped to the selected working production, while Messages, Community, Notifications, personal Calendar and unread state remain account-wide.
- Production filters in account-wide views are organizational filters only; they must never function as a required context gate that can hide unread or actionable information.
- Authentication identity is browser-session scoped; no shared database current-user mutation is permitted.
- Runtime administrator authorization is role/permission based, not display-label based.
- Production membership and authentication roles are different concepts: a person can participate in a production without gaining administrative permissions.
- A family dashboard resolves each linked student's current permissions independently; guardian visibility never substitutes for or broadens the student's own production/group schedule access.
- Public registration intake and authenticated membership are separate lifecycles; a public registration must never silently create a CTSMD account or production membership without staff review.
- Public child/minor registration should collect only the minimum information necessary for the immediate registration workflow; richer sensitive data requires an explicit later policy decision.
- Stored files and their immutable versions are infrastructure objects; production files are permissioned domain objects that reference them.
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
