# CTSMD Connect — Single Source of Truth: To-Do & Product Backlog

**Updated:** October 26, 2026 · **Working branch:** `audit/board-pilot-readiness`

**This is the ONE maintained list of active work, pilot gates, and approved feature backlog.** Checkboxes represent verification or implementation remaining; a merged commit is not proof of production deployment. Keep status/owner/evidence here, link details elsewhere, and do not create new competing active to-do documents.

**Release stance:** The most recent [board pilot report](BOARD_PILOT_READINESS.md) remains **NO-GO for a real-family pilot** until its open gates are accepted. Older percentages are dated evidence, not current calculated coverage.

## How to use

- **P0**: security, safeguarding and live release blockers.
- **P1**: near-term verification and recently built workflows.
- **P2**: public launch polish and quality.
- **Waiting on specs**: do not build wider permissions without CTSMD requirements.
- **Product backlog**: ideas approved for future discovery, *not* claims that all are missing or prioritized. Revalidate against source and actual behavior before scheduling.
- When completing an item, add acceptance evidence (environment/date/tester/commit or issue) or link the verification notes.

**Current priority decision (October 26):** Stabilize the rest of the platform first. Casting and the published cast list are working in user smoke tests. Playbill is parked by choice, not considered a blocker for finishing other modules.

## P0 — Pilot blockers and release gates

- [ ] Verify safeguarding revocation across web/mobile, including former guardians, casting offers, student tasks and notices.
- [ ] Complete live email and push acceptance: actual delivery, deep links, retries, recipient authorization and queue operation.
- [ ] Validate HTTPS, secure cookies, secret-file protection, sensitive uploads and production hosting incident response.
- [ ] Assign backup/restore owners, retention, RPO/RTO and run a documented restore drill.
- [ ] Obtain policy-owner approval for privacy notice, terms/acceptable use, guardian consent and retention wording.
- [ ] Run staging regression suite and record real-device/mobile acceptance evidence. **Do not treat GitHub commits as deployment proof.**

## P1 — Verification and workflow completion

- [ ] **Caregiver mobile schedule response minimization (Oct 26 source):** `/api/mobile/caregiver-schedule` no longer returns internal `student_id` or `schedule_item_id`; intended keys are `student_first_name`, `production_title`, `title`, `starts_at`, `ends_at`, `location`. Verify native client field expectations and rerun mixed-role, active/revoked relationship, student-only item visibility, and cached-token authorization tests. Caregiver approval stays blocked.


- [ ] **Caregiver mobile family identity minimization (Oct 26):** `/api/mobile/my-family` now returns first name and relationship type only for caregiver-linked children (no student ID or full name), while preserving parent/guardian entries. Regression-test native family rendering, mixed-role adults, token access after relationship revocation, and verify no private child data is reachable through other mobile routes. No caregiver approval until full parity.


- [ ] **Caregiver-only mobile API gate (Oct 26 source):** `/api/mobile/caregiver-schedule` returns a minimal 45-day calls payload for active caregiver-linked students. Caregiver-only accounts are denied all mobile endpoints other than `/me`, `/my-family`, this schedule and `/logout`. Test direct requests including saved tokens before deployment; mixed parent/caregiver accounts still need per-child authorization audits. This is a conservative interim block, not caregiver launch approval.


- [ ] **Caregiver web navigation boundary (source committed Oct 26):** family dashboard and household child queries now exclude caregiver-only relationships, caregiver-only `/family-hub` and `/family/manage` redirect to read-only logistics view, child creation requires parent/guardian (server+form), and mobile `/my-family` includes relationship_type for clients to distinguish caregiver. Verify mixed-role adults, direct POST access, and native client behavior. **Caregiver approvals remain BLOCKED until all message/form/mobile safeguarding checks are complete.**


- [ ] **Caregiver logistics V1 (source committed Oct 26):** new `/family/caregiver-schedule` renders only the next 45 days of schedule title, call time and location for children with an active caregiver relationship; excludes private notes, forms, casting and conversation content. Runtime-test audience filtering and revocation, then add appropriate navigation and native API route after authorization parity. Do not enable caregiver approvals until existing web/mobile relationship gates are audited.


- [ ] **Caregiver safeguarding boundary follow-up (Oct 26):** ScheduleAudience family/guardian delegation and SafeguardedConversationPolicy eligibleGuardian queries now require an active parent/guardian relationship, not caregiver. Runtime-test eligible guardian coverage, student scheduling, existing safeguarded conversations and revocation, and mobile API family/message endpoints before activating caregiver account approval. Caregivers are still blocked in FamilyPickupService::decide; mobile family listing still returns active family children and requires a scoped logistics-only contract before enabling that role.


- [ ] **Caregiver authorization audit (Oct 26):** Casting offers, family forms, private casting communication, and student Theatre Profiles now distinguish active parent/guardian from caregiver relationships. Verify corresponding web and mobile API endpoints, family schedule-only presentation, safeguarded messaging and notifications, guardian-context production enrollment, and pending role approvals before enabling caregiver account access. Caregiver approval remains blocked intentionally. Run PHP lint and role-based tests after deployment.


- [ ] **Legacy staff family-editor consolidation (Oct 26 source):** `/admin/accounts/view?id=` now routes child relationships to Family & Pickup rather than direct add/deactivate actions that bypassed pickup selection and safeguarding review. Verify staff child and parent account pages, approved parent invitations, pickup choice, and old POST actions rejected. No automatic migration/backfill.


- [ ] **New child primary-adult pickup default (source committed October 26):** creating a managed child automatically inserts an active, linked pickup authorization for the creating adult within the same transaction. Requires migration 085. Verify new parent/guardian-created child, pickup visible in Family and Attendance, rollback behavior, and no changes to legacy/demo children. Parents/staff can later revoke pickup authorization independently.


- [ ] **Production People removal → casting cleanup (source committed October 26):** Removing an active student membership now deletes that student's linked casting record and offer/role assignment rows for this production, preserves the decision/offer snapshot in the audit log, and regenerates published cast/callback snapshots. Verify on staging: student removed from People and Casting, public/member published list updated, multi-child guardian membership retained when still needed, orphan guardians retired, and failed deletion fully rolled back. Important: original linked offer records are deleted via FK cascade; confirm archival/retention requirements before using on real-family productions. No migration required.


- [ ] **Casting acceptance → Production People (source implemented October 26):** accept a pending registered-student offer and verify automatic student and active parent/guardian production membership, no duplicates on existing memberships, no enrollment on decline, Theatre History sync, audit event and transaction rollback when enrollment cannot complete. Pickup-only people and pending adult relationship requests must never be enrolled. Existing accepted offers need a separate reviewed reconciliation; no automatic backfill was run.


- [ ] **Family & Pickup Management V1 deployed/runtime tested:** apply migration 085; verify each child has independent adult relationships and pickup-only people, edit/deactivate/restore, audit entries, CSRF, staff approval and final-guardian protections. Routes: `/student-profile/family?student=ID` and staff inbox `/student-profile/family/review`. New pending requests can be cancelled by their requester, and staff can approve/reject through a central review view. Source implemented October 26, not runtime verified.
- [ ] **Family & Pickup Management safeguarding closure:** design verified adult invitation delivery/claiming and safeguarding/custody-dispute review protocols, including custody/dispute holds; restrict caregiver account permissions before approvals (currently explicitly blocked), check same-child pickup authorization independently, and ensure former guardians lose future messages, tasks, casting and notices after deactivation. Avoid granting broad child access by pickup-only status.
- [ ] **Attendance pickup integration runtime acceptance:** staff-only authorized pickup names (including no-account contacts) now display in the checkout drawer from active per-child records, with inactive linked adults filtered at query time. **Requires migration 085 and live verification.** Identity checking, capturing who actually collected the student, pickup audits and release workflow remain unbuilt; do not treat a recorded pickup time as verified release.


**Deployment preflight added in source:** `php bin/check-schema.php` checks required feature tables/arrival column for migrations 079–084 without changing data or exposing connection details. This would detect the absent Volunteer Leads table. **Still needs to be run on hosting and incorporated into the deployment checklist/automation.**

- [ ] Run `php bin/check-schema.php` on the live hosting CLI, remediate any missing schema for **079–084**, and audit later migrations before subsequent deployment. **Migration 082 (Volunteer Leads) was identified as the Tasks 500 cause and subsequently smoke-tested by the user on October 26; remaining migration coverage is not independently confirmed.**
- [x] **User smoke test (October 26, live site):** casting offer acceptance and decline, staff response notifications, links and Action Center clear were reported working. This is functional smoke testing, **not** full safeguarding/device acceptance.
- [x] **User smoke test (October 26, live site):** casting workflow and published cast list reported working. Do not reopen normal casting/list UI without a concrete regression.
- [ ] Verify remaining casting safeguarding/permission edge cases, including manual participant authorization, offer revocation, and access to unpublished student information. **Playbill integration deliberately deferred.**
- [x] **User smoke test (October 26, live site):** Production Tasks reported working after the missing migration 082 issue was identified and addressed. Tests were informal; no full regression evidence.
- [ ] Verify Tasks grouped assignment, guardian/student completion, delegated owner/Volunteer Lead authorization and revocation in an explicit regression pass.
- [x] **User smoke test (October 26, live site):** attendance entry, drawer save, pickup times and general workflow reported working following recent fixes.
- [ ] Complete attendance regression: arrival-time corrections, tap-again clear, mobile sizing, cross-date/past-event handling, Late review and guardian-notice approval safeguards (no send before approval).
- [ ] Finish native Expo staff-side parity for casting and Tasks; test native Action Center and permissions.
- [ ] Verify Expo Student/Guardian casting offer display and Accept/Decline on physical iOS/Android devices, including decline-note requirement, rejected/stale offer, former-guardian revocation, and Home pending-offer visibility. Test new unread/past notification screen, mark-read, and casting notification navigation. Source update committed October 26, 2026; **not device verified**.
- [ ] Accessibility/responsive QA for desktop, mobile drawers, forms, attendance and keyboard navigation.
- [ ] Run link checks, performance baseline, media upload/optimization and server-side form validation audit.

### Production lifecycle closure — October 27 source changes

- [x] Added source-level closed-production guards for direct People roster additions/removals, volunteer shift creation/edit and roster-status changes, volunteer-facing signup and closed-show opportunity filtering, and Production Group form assignment; completed/archived and unknown-stage roster writes are blocked and rolled back in regression stubs. Historical read paths were not changed.
- [ ] Run migrated-database integration checks for each new boundary and verify on CTSMD.me with a disposable completed and archived production, including direct POST attempts, preserved historical casting/credits, and account-wide student/guardian access.
- [ ] Finish the remaining write-boundary audit for existing form submissions/reviews, volunteer member signup/cancel and approval routes, community replies/reactions, notifications, file archives, production announcements, and other writes that may require narrowly defined post-close exceptions.
- [ ] Decide and implement a controlled closeout process with pre-close readiness warnings, pending offers/forms/shifts review, archival handoff, and reversible stage changes only where policy allows.
- [ ] Reconfirm former-guardian rights, archived channel visibility, native API permissions, queued delivery authorization, and audit/retention after stage changes.
- [ ] Resolve all remaining pilot CI failures and capture separate PHP, database and device acceptance evidence. The volunteer-cancellation stub's invalid-ID pretransaction assertion was corrected; it requires verification by CI.

## P2 — Public experience and quality

- [ ] Audit public-only titles, descriptions, social previews, favicons and accessible image alternatives.
- [ ] Create approved public-only sitemap; verify robots/noindex handling on deployed host.
- [ ] Add branded safe 404 handling and check redirects.
- [ ] Decide whether any analytics or cookie consent is warranted after tracking/privacy inventory.
- [ ] Review public calls to action for Tickets, Volunteer, Join and digital Playbill.

## Waiting on specs / future planning

- [ ] Define broader **Volunteer Lead** authority, training prerequisites and safeguards once CTSMD supplies the role specification.
- [ ] **Playbill is deliberately deferred by product decision (October 26).** Do not schedule Playbill publication, PDF/QR polish, sponsorship, biographies, imports or résumé integrations until the wider platform is operational and safeguarding/release gates are addressed.


## Platform launch checklist — status reference

The 20-item website checklist, mobile-app gates, and privacy decisions are folded into P0–P2 above. For audit rationale, consult [Platform Launch Readiness](PLATFORM_LAUNCH_READINESS.md). The source and deployment need separate verification for: privacy policy, terms, secrets, HTTPS, consent if applicable, titles/meta, social previews, favicon, sitemap/robots, alt text, image compression, speed, contrast, mobile responsiveness, custom 404, broken links, validation, spam protection, analytics if appropriate, and public calls to action.

## Product backlog — approved directions (not scheduled)

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
- Casting workflow refinements after the first casting/role-assignment slice demonstrates real needs.
- Production-day enhancements such as staff assignments, reusable day templates or member-facing published call sheets only if actual operations require them.
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

## Playbill backlog — parked / not current build scope

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

- Playbill publication source is implemented in migration 068. Before production use, deploy migration 068, complete fake-data consent/public-route/QR runtime acceptance, and decide whether any published Student biography content may be search-indexed. Public pages default to `noindex`.

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


## Completed in source — retain as context, not open tasks

- Casting: separate Cast/Callbacks, role library/multi-select for registered students, offer-response flow, manual participants and publication privacy. Published cast lists have passed user smoke testing; Playbill integration is deferred. See [Casting Roles](CASTING_ROLES_ROLLOUT.md), [Casting Offers](CASTING_OFFER_WORKFLOW.md) and [Manual Participants](MANUAL_PARTICIPANTS.md). **Manual participant role parity and runtime acceptance remain P1.**
- Attendance: editable arrival time; late checkout under student More; late-pickup occurrence/draft/review logic. See [Late Pickup Workflow](LATE_PICKUP_WORKFLOW.md). Runtime acceptance remains P1.
- Tasks: standalone `/production/tasks` page, grouped assignees, per-person completion and guardian Action Center visibility. Volunteer Lead is only production-scoped task owner designation. See [Tasks Extraction](PRODUCTION_TASKS_EXTRACTION.md).
- Mobile: authenticated student/guardian Action Center with casting offers and individual To-Dos; native staff parity is **not** complete. See [Native Parity](../mobile/docs/CASTING_TASKS_PARITY.md).
- Search privacy: robots directives and private-app noindex headers in source, not yet verified deployed.
- Documentation: canonical index and archive of older roadmap notes; live and release testing still required.

## Reference documents (technical evidence, not parallel active checklists)

- [Documentation Index](INDEX.md)
- [Board/pilot evidence and blockers](BOARD_PILOT_READINESS.md)
- [Platform launch audit rationale](PLATFORM_LAUNCH_READINESS.md)
- [Late pickup workflow](LATE_PICKUP_WORKFLOW.md)
- [Native parity](../mobile/docs/CASTING_TASKS_PARITY.md)
- [Original long backlog archive](archive/TODO_PRE_CONSOLIDATION_2026-10-26.md)

## Maintenance policy

1. Add or change **actionable tasks only here**. Technical documents may contain acceptance criteria, but must link back to this tracker rather than claiming a second current work queue.
2. If functionality already exists, write the action as **verify / remediate** rather than accidentally reopening implemented work.
3. Do not mark pilot gates complete based on code presence; attach tests and approvals.
4. Preserve older content and dated decisions in `docs/archive/`, not in competing live lists.
