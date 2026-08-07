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

### Implemented — Lean public landing + restrained registration

- `/` is now a deliberately small public CTSMD Connect landing page rather than a full replacement for the existing CTSMD website.
- Public landing currently exposes selected public signups, current digital Playbills and member sign-in, but the intended primary public action is **platform registration/onboarding** once the next build is complete.
- `/register` is intentionally limited to **auditions, selected special/event signups, and interest/RSVP signups** explicitly published by authorized CTSMD staff and currently within their open/close window.
- **Classes, camps, workshops and the broader CTSMD program catalog are not registered through Connect at this stage.** They remain in the organization’s existing external registration system until CTSMD intentionally chooses to replace that system.
- The database enum retains broader opportunity values for future compatibility, but current public/service/admin logic fails closed and will not publish or accept Connect registrations for class, camp or workshop records.
- Connect signups may optionally relate to a production and support dates/location, registration windows, capacity, waitlist behavior, confirmation text and draft/published/closed/archived lifecycle.
- Public registration intentionally collects a narrow data set: participant name, broad age group, contact information, guardian contact for minors and an optional operational note.
- Public registration does **not** collect date of birth, medical history, school or other sensitive data merely because it might be useful later.
- A parent/guardian name and valid guardian email are required when the participant is under 18.
- Capacity is enforced transactionally; registrations submitted after active capacity is reached enter the waitlist rather than overbooking.
- Each registration receives a private random manage token; only its SHA-256 hash is stored. Confirmation email provides a private manage/cancel link.
- Registration confirmation uses the existing outbound email queue and therefore follows the same local-log/SMTP deployment model as other CTSMD email.
- Public cancellation preserves the registration record and changes lifecycle state instead of deleting history.
- Staff Registration Operations workspace at `/admin/registrations` supports creating/editing supported Connect signups, publishing/closing them, reviewing registrants, and changing submission status among submitted, waitlisted, accepted, declined and cancelled.
- Registration Operations currently uses `forms.manage` authorization rather than adding a new one-off RBAC permission before CTSMD demonstrates a need for separate registration administrators.
- Registration schema lives in migration 020.
- This build is intentionally **not** a CMS/news/donations/payments/public-site takeover and is **not** a class/program-registration replacement. Those remain future options only if CTSMD chooses to expand Connect deliberately.
- **Runtime verification:** pending local MAMP test after migration 020, including public root routing, supported-type restrictions, publication windows, minor guardian validation, capacity/waitlist behavior, email confirmation/manage URL and public cancellation.

## Near-term build order

### Next — CTSMD Connect platform registration + onboarding

- Make the primary public landing action **Create / register for CTSMD Connect**, with Member Sign In as the adjacent returning-user action.
- Adult/guardian users may start their own CTSMD Connect account from the public site and verify ownership of their email address.
- Self-registration creates only a basic authenticated account; it must **not** grant production membership, Community-channel access, staff permissions, student access, or any other privileged relationship merely because the public form was completed.
- Production memberships and production-scoped access are granted only from verified CTSMD participation/roster operations or an explicit staff-reviewed onboarding workflow.
- Students/minors do not anonymously self-register from the public site. Student identities/accounts are created or linked through an authenticated guardian workflow and existing family-relationship safeguards.
- Adult volunteers may create their own basic account, but volunteer readiness, credentials and production/shift eligibility remain controlled by canonical volunteer records and staff-managed requirements.
- Use email verification through the existing outbound email queue before a public self-registered account becomes active.
- Prevent duplicate accounts for an email address; where an invited or existing account already exists, route the person into activation/sign-in/recovery rather than creating another identity.
- Preserve staff-issued invitations for controlled staff/admin onboarding and cases where CTSMD wants to pre-link a person before they register.
- Add a clear post-registration state for accounts that are valid but not yet attached to any production, explaining that access appears when CTSMD links the account to current participation.
- Keep public audition/special-event registration as a separate secondary workflow; submitting an audition/special signup must not silently create a platform account.
- Update the public landing hierarchy so audition/special signups and Playbills sit beneath the primary platform-registration/sign-in actions.
- Add audit events and rate-limiting/abuse protections appropriate for a public account-creation endpoint.
- **No production context is involved in platform registration.** Account identity is organization-wide.

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
- Do not expand this follow-through into class/camp/workshop registration unless CTSMD explicitly decides to replace the current external registration system.

## Future public-site options — only if CTSMD chooses to expand Connect

- Full public CTSMD website/CMS bridge.
- Public production/audition/event detail pages beyond the lean registration page.
- Class/camp/workshop catalog and registration only as an intentional replacement project for the current external system, not as an automatic extension of migration 020.
- Broad public calendar, news, donations, sponsorship and RSVP experiences.
- Payments only after actual program/payment requirements are defined.
- Do not build these merely because Connect technically can; the existing CTSMD website and registration platform may remain in place indefinitely.

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
- **Public platform registration creates identity, not entitlement.** A verified self-registered account receives no production, Community, student or staff access until canonical CTSMD relationships/memberships grant it.
- **Students/minors do not anonymously self-register.** Student account creation/linking is guardian-mediated and must preserve family/safeguarding rules.
- A family dashboard resolves each linked student's current permissions independently; guardian visibility never substitutes for or broadens the student's own production/group schedule access.
- Public audition/special-signup intake and authenticated platform membership are separate lifecycles; one must not silently create the other.
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
