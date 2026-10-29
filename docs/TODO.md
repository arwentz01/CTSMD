# CTSMD Connect — Single Source of Truth: To-Do & Product Backlog

**Updated:** October 28, 2026 · **Working branch:** `audit/board-pilot-readiness`

**This is the ONE maintained list of active work, pilot gates, and approved feature backlog.** Checkboxes represent verification or implementation remaining; a merged commit is not proof of production deployment. Keep status/owner/evidence here, link details elsewhere, and do not create new competing active to-do documents.

**Release stance:** The most recent [board pilot report](BOARD_PILOT_READINESS.md) remains **NO-GO for a real-family pilot** until its open gates are accepted. Older percentages are dated evidence, not current calculated coverage.

## RC1 signoff blockers — October 28, 2026

### Operations UX implementation and acceptance exceptions

- [x] Implement Batches A–F with per-route presentation and browser evidence in [Operations coverage](UX_OPERATIONS_COVERAGE.md). 121 route entries, 101 screen templates adopted, 100 actual screens rendered at three widths; Playbill Media is explicitly blocked before rendering. Existing permission, lifecycle, form and safeguarding behavior is frozen to baseline. No deployment, production-data changes or delivery workers were part of this work.
- [x] Verify code `1b02b97`: web safety and isolated RC1 CI pass; local PHP/JS checks, 52 PHP behavior contracts, 76 browser interaction/role checks, 120 real HTTP/API checks and 36 transactional safeguarding checks pass. These checks do not prove every screen save or close the existing RC1 release gates.
- [ ] Complete physical-device/assistive-technology and communicating/destructive POST acceptance separately. A styled control is not evidence that a transaction or real-device workflow passed.
- [ ] **Operations dependency — Playbill Media:** populated `/admin/playbill/media` fails in `PlaybillEnhancementService::syncCastProfiles()` because the query selects `cr.role_title` without a `cr` join. Reproduced against fully migrated isolated schema; not a missing-column migration. Requires a separately reviewed backend correction and sync/consent regression test. UI adoption does not close this blocker.
- [ ] **Local demo dependency:** read-only preflight on the existing `ctsmd` demo still finds 45 declared-contract issues, including safeguarding statements, videos and registration/notification columns. Browser verification used a fully migrated isolated schema. Reconcile the default demo through the migration review process separately; no schema changes were made during Operations UX work.

Release preparation is not approval to deploy. See [RC1 evidence and required signoff proof](RC1_ACCEPTANCE.md) and [manifest/preflight/rollback/restore runbook](RC1_RELEASE_RUNBOOK.md). No hosting writes or workers were authorized or executed. The following IDs map to the dated evidence table; appoint owners through the release process.

Verified candidate code `6a891e7`: [RC1 CI](https://github.com/arwentz01/CTSMD/actions/runs/40691477305) and [web safety CI](https://github.com/arwentz01/CTSMD/actions/runs/40691477248) passed. 27 tooling contracts, 120 seven-persona/two-production real HTTP checks and 36 transactional safeguarding checks pass; Expo CI/local exports pass on unchanged native source. Read-only preflight correctly blocks incomplete demo schema. These results do not close the 13 gates below.

- [ ] **RC1-01:** Review exact hosted migration/trigger/permission/index/FK/type/backfill state and checksum-bound statement plan; staging restore/rehearsal and DBA approval. New preflight detects local demo gaps through 092; 062/base overlap and duplicate 069 numbers forbid blind batch imports.
- [ ] **RC1-02:** Complete mixed-family/guardian/caregiver, group/attachment/search/casting/task/notice/queue safeguarding and saved-session/device matrix; safeguarding signoff.
- [ ] **RC1-03:** Resolve retention/cascade risk on roster removal; prove completion/archive/history preservation for offers/credits/forms/resources/messages/audit on isolated restored data before real-family use.
- [ ] **RC1-04:** Execute concurrent close/form/review/assignment, last-guardian, volunteer-capacity and dispatch/revocation race tests; verify lock/retry behavior.
- [ ] **RC1-05:** Verify password/admin/session revocation, concurrent native account switch, offline logout retry and credential reconciliation after restore.
- [ ] **RC1-06:** Obtain signed physical-device builds and native parity/cache/privacy acceptance, with explicit launch scope for missing workflows.
- [ ] **RC1-07:** Close in-scope Web Push OS/tap and native provider/delivery authorization gates; approve old queue disposition separately. No worker activation in RC1 preparation.
- [ ] **RC1-08:** Name backup/incident/storage owners; approve retention/RPO/RTO and run a new matched code/DB/private-file restore with hash/access/queue/credential reconciliation evidence.
- [ ] **RC1-09:** Verify candidate hosting deny rules, private files/logs, TLS/cookies/proxy, credential rotation/incident closure and clean-profile startup.
- [ ] **RC1-10:** Obtain privacy/terms/guardian consent/retention/media and supported-student-cohort policy approvals.
- [ ] **RC1-11:** Complete browser/mobile keyboard, focus, long-table, screen-reader and readability acceptance with dated devices and resolved defects.
- [ ] **RC1-12:** Resolve/review SDK tooling advisories with SDK-compatible changes and release-owner security decision; do not infer closure from bundle exports.
- [ ] **RC1-13:** Do not replay 062's historical enrollment backfill on caregiver-populated data: it lacks a parent/guardian relationship-type filter. Require an eligibility-safe reviewed statement plan and populated migration regression preserving revoked cutoffs; empty-schema CI is insufficient.

## Phase 4 native acceptance follow-up — October 28, 2026

Evidence and dated acceptance checklist: [Native Phase 4](NATIVE_PHASE4_ACCEPTANCE.md). Existing Expo app extended; no production deployment or new identity system.

Source evidence: `c00a91a` native integration and `07a96f2` current-stage form locking. Remote [Expo CI](https://github.com/arwentz01/CTSMD/actions/runs/40686120022) and [web safety CI](https://github.com/arwentz01/CTSMD/actions/runs/40686270573) passed; six native contracts, 29 local bearer/API checks and 36 transactional safeguarding checks passed. These do not close the device/pilot gates below.

- [ ] **Device / safeguarding acceptance:** physical iOS/Android parent/student/staff tests for cold start, session replacement/revocation, multiple shows, former guardians, closed resources, notifications, upload/video, large text, VoiceOver/TalkBack and keyboard/safe areas. Local API and mocked native tests are not device signoff.
- [ ] **Native push remains blocked:** APNs/FCM sender/credentials/build configuration, shared delivery-time authorization/preferences, receipt/token cleanup, offline logout retry and OS-permission revocation. Registration is `native_pending`, not delivery.
- [ ] **Backend/native parity:** prioritize guardian-on-child structured forms, secure profile/contact and Family & Pickup workflows, caregiver logistics and staff workflows. Do not relax server policy to achieve parity.
- [ ] **Closed forms acceptance:** shared transaction guards and legacy structured-submit rejection now in source; local closed-submit HTTP and lifecycle tests pass. Verify staff edit/review/assignment, mixed production scopes and concurrent closure in staging before closing the release gate.
- [ ] **Schema 086 hosting preflight:** existing additive DOB/contact/age migration applied only to local demo; Family & Pickup now loads. Read-only preflight checks its ten columns. Independently inspect/back up/review hosting migration state; no production schema changes authorized or performed in Phase 4.
- [ ] **SDK tooling advisories / distribution:** review 8 npm advisories (3 high, 5 low), use SDK-compatible patches rather than forced downgrade, then signed internal builds and owner approval. Existing pilot operational gates remain open.

## How to use

- **P0**: security, safeguarding and live release blockers.
- **P1**: near-term verification and recently built workflows.
- **P2**: public launch polish and quality.
- **Waiting on specs**: do not build wider permissions without CTSMD requirements.
- **Product backlog**: ideas approved for future discovery, *not* claims that all are missing or prioritized. Revalidate against source and actual behavior before scheduling.
- When completing an item, add acceptance evidence (environment/date/tester/commit or issue) or link the verification notes.

**Current priority decision (October 26):** Stabilize the rest of the platform first. Casting and the published cast list are working in user smoke tests. Playbill is parked by choice, not considered a blocker for finishing other modules.

## P0 — Pilot blockers and release gates

- [x] Phase 3 closed-form source gap addressed in Phase 4 with shared transaction guards. RC1 adds migrated real-HTTP closed submit/review/definition/toggle/builder rejection and state fingerprints. Hosted scope/concurrency acceptance remains RC1-01/04; no production deployment claimed.
- [x] Local Phase 3 `users.date_of_birth` dependency reconciled with reviewed existing 086 in Phase 4; Family & Pickup local smoke passed. Broader RC1 preflight now detects missing local 087–092 state, tracked under RC1-01, not silently applied.

Phase 2 UX: Family, personal/student Today, Community, Messaging and Action Center implemented on the existing branch. See [implementation and verification](UX_MODERNIZATION_AUDIT.md) and subordinate [cosmetic refinement notes](UX_POLISH_BACKLOG.md). Local browser and safeguarding evidence does not close the live-pilot gates below or imply deployment.

Phase 3: six implementation areas addressed and pushed in focused batches. See the same audit for source/browser/native distinctions. Acceptance remains open for closed-form backend enforcement, local Family & Pickup schema reconciliation and native device testing. Do not mark the pilot or full Phase 3 acceptance complete from presentation commits.

- [ ] Verify safeguarding revocation across web/mobile, including former guardians, casting offers, student tasks and notices.
- [ ] Complete live email and push acceptance: actual delivery, deep links, retries, recipient authorization and queue operation.
- [ ] Validate HTTPS, secure cookies, secret-file protection, sensitive uploads and production hosting incident response.
- [ ] Assign backup/restore owners, retention, RPO/RTO and run a documented restore drill.
- [ ] Obtain policy-owner approval for privacy notice, terms/acceptable use, guardian consent and retention wording.
- [ ] Run staging regression suite and record real-device/mobile acceptance evidence. **Do not treat GitHub commits as deployment proof.**

## P1 — Verification and workflow completion

- [ ] **Adult participation enhancement (separate from RC1):** implement and verify six batches in [architecture](ADULT_PARTICIPATION_ARCHITECTURE.md), including all ingress enforcement, audited Student transition, casting/enrollment, mixed-age safeguarding, consent/history, web/native parity and real MySQL concurrency. No hosting migration or deployment approved. Existing pilot gates remain open.

- [ ] **Archived viewer revocation:** historical memberships no longer grant archive access to disabled/inactive viewers; active managed student accounts remain eligible (9a2b3b4, 4189333, assertion a526bac). Add database-backed inactive/member/managed student/guardian tests before pilot sign-off.


- [ ] **Archived guardian history linked-student safety:** require active linked student account for guardian archive listing, detail and historical audience checks (b1bfb59; source regression bcbed85). Verify direct membership and child activation/deactivation cases with database-backed multi-persona integration tests.


- [ ] **Archive readiness extension:** pending casting offers and volunteer approvals now block archival with transactional recheck; notices/forms/checklist are visible retained warnings (38ff902, 0a86305, db32484). Full queue/service-hour/role checks and live integration remain outstanding.


- [ ] **Mobile lifecycle parity (Oct 27 continuation):** daily tasks now filter completed/closed-production forms and future shifts; Community reaction endpoint uses a locked lifecycle write check (52ee0d7, de3b30e, regression e19ada9). Still audit mobile Community reply/moderation and other mutations, archived visibility, combined family actions and test on real database/native clients.


- [ ] **Volunteer email delivery-time gate:** queued shift-reminder and shift-eligibility messages now revalidate signup owner, status and writable production lifecycle prior to sending (1e51e96, 50f3cce). Add database-backed tests and remaining origin checks; no live delivery tests performed.


- [ ] **Delivery reauthorization partial (Oct 27):** form-due email jobs recheck assignment, production stage and parent/guardian access at send time; push bridge and push worker recheck source notification access (0ceca9a, 873a53f, 177a991, tests c1e0d84/4752f5d). Still required: production-scoped volunteer email dedupe recheck, push without source ID, notification origin metadata coverage, cross-process races, live test and mobile token delivery.


- [ ] **Family/current-view lifecycle filtering:** Family and Home actionable forms and volunteer commitments, Family active production summaries, and shared Calendar current events now individually exclude closed production records while retaining organization-wide and separate active-production data (05d9f87, a3ffe79, 177b004, eb4699a). Need database-backed Student/Guardian/multiple simultaneous show regression and native API parity.


- [ ] **Completion readiness expansion (Oct 27 source):** pending casting offers now block completion, with server-side recheck; unmarked attendance and draft schedule notices are warnings. Commits 5ffb37f, d65dfa0, 02fc783. Still expand production tasks, service-hour readiness and unsent messages; perform database-backed and family persona tests before closure acceptance.


- [ ] **Archive Production V1 source (Oct 27):** explicit completed-only archive confirmation, transaction, audit and legacy is_active/status closure added (f6c6cf6, 90c978a); legacy archived reactivation blocked (7ac735e); source regression contracts (44d6995). Needs full readiness checklist, queue filtering, mixed-production family checks, database tests, live deployment and release approval. Do not archive real-family shows until verified.


- [ ] **Complete Production V1 source (Oct 27):** staff production detail has explicit title confirmation, outstanding-warning summary and acknowledgement; ProductionCompletionService locks and updates lifecycle, writes audit, and preserves records. Commits 486172e, bef1e70, 0c873b5. Still missing full readiness inventory and blockers, archive workflow, mobile/family lifecycle filtering, staging integration/rollback testing and deployed acceptance. Do not treat V1 as production-ready closure.


- [ ] **CRITICAL lifecycle transition coordination:** date automation no longer directly completes or archives productions (c1ef61e). Controlled manual completion/archive confirmations remain unimplemented; current lifecycle stage editor still permits raw stage changes. Complete these together before allowing staff to transition real productions. Regression contract f18fcab; staging and cron verification pending.


- [ ] **Notification lifecycle (Oct 27 source partial):** reminder generator excludes closed/unknown production form assignments and volunteer shifts while retaining organization-wide reminders; active parent/guardian relationship required for student form-reminder recipients. Commits 319c51d and 59b318e. PHP CI passed. Outstanding: queue delivery-time reauthorization, existing queued mail, app/web/native push parity and other production notification origins. No deployed/real-member delivery validation.


- [ ] **Community lifecycle hardening (Oct 27 follow-up):** block posting capability for closed/unknown production stages, filter production-guardian audiences by active parent/guardian roles, and recheck channel write scopes inside save transactions. Source changes bbd8f9c, d373dcd, regression 695b215. Pending: group/team membership history, concurrency, former-guardian integration, mobile persona tests and hosted acceptance.


- [ ] **Additional closure gates (Oct 27):** production-scoped volunteer approval requests, coordinator decisions, signup cancellations, and Community heart reactions now call ProductionLifecycleGuard at service boundaries. Pending: authorized historical volunteer corrections, all Community mutations/membership and notification queues, plus full Complete/Archive workflows. Commits 91c70db, 1cab45f, 863ba07, 354f058. Treat CI as source verification only; staging and production remain unverified.


- [ ] **Production completion/archival workflow (Oct 27 partial source hardening):** transactional form submission/review closure guard added; casting notification and archive family access now require active parent/guardian relationship. Source-boundary checks added. Pending: comprehensive Forms, volunteer, Community and notification mutation audit; manual/automated stage transition controls; readiness review and explicit complete/archive confirmations; cross-production family UI; database/persona tests and deployed validation. Evidence: commits 725498e, ff77f3b, 8f2d426, 99e4d5a. No production deployment claimed.


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

### Production directory organization — October 27

- [x] Staff `/admin/productions` now shows Current & Upcoming first, sorted by production dates (performing/in-production before future shows), with Completed/Archived in a collapsed Past Productions section sorted newest to oldest. Legacy `is_active` alone cannot keep closed shows in the current list.
- [ ] Verify the date ordering and collapsible behavior in the deployed desktop/mobile browser with real show data, including missing date fields and conflicting historical status flags.

### Production lifecycle closure — October 27 source changes

- [x] Added source-level closed-production guards for direct People roster additions/removals, volunteer shift creation/edit and roster-status changes, volunteer-facing signup and closed-show opportunity filtering, and Production Group form assignment; completed/archived and unknown-stage roster writes are blocked and rolled back in regression stubs. Historical read paths were not changed.
- [ ] Run migrated-database integration checks for each new boundary and verify on CTSMD.me with a disposable completed and archived production, including direct POST attempts, preserved historical casting/credits, and account-wide student/guardian access.
- [ ] Finish the remaining write-boundary audit for existing form submissions/reviews, volunteer cancellation and approval routes (signup now has a lifecycle guard; requires live acceptance), community replies/reactions, notifications, file archives, production announcements, and other writes that may require narrowly defined post-close exceptions.
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
