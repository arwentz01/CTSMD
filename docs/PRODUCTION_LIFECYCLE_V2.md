# Unified production lifecycle rollout

## Implemented in this phase
- Migration 075 adds lifecycle_stage: upcoming, planning, in_production, performing, completed, archived.
- Legacy active productions backfill to in_production, including legacy planning productions, to preserve existing access.
- Production management includes manual stage selection and audition/performance/archive dates with basic validation.
- Mobile Theatre categories use lifecycle_stage where available.
- Authenticated mobile Production Hub planning badge uses lifecycle_stage.
- CLI worker `php bin/run-production-lifecycle.php` moves in_production to performing on performance start, performing to completed at midnight after the final performance day, and completed to archived at the configured archive date (default 30 days after completion). Each transition is audited.
- The worker is not scheduled by the application; configure a daily cron job only after integration testing.

## IMPORTANT: compatibility phase, NOT permission cutover
- Existing `is_active` and old `status` remain in place. Legacy production-membership, schedule, channel and artwork authorization still uses is_active.
- New Upcoming and Planning productions are NOT yet automatically visible as public/member production cards; the current mobile API exposes active productions only.
- A lifecycle-stage change does not yet activate/deactivate production access and does not change older web labels or casting release behavior.
- Avoid manually putting an access-active production into Upcoming/Planning expecting it to become private until authorization parity has shipped.
- Public release of full student names is NOT enabled. Public casting page continues using first name and last initial. Authenticated published casting snapshots retain full names.

## Pending coordinated rollout
1. Introduce separately approved public production summary and stage-specific audience queries on web + mobile, preserving private schedule/channel/form authorization.
2. Map existing active memberships, published casting snapshots, notification recipients and historical data safely to the lifecycle.
3. Deprecate is_active and old status only after every backend route is migrated and tested.
4. Expose audition/performance windows on public production and signup pages, with staff publication approval.
5. Add stage-transition previews and reschedule protections; confirm performance end date and archive defaults against CTSMD timezone.
6. Add tests for staff, cast, guardian revocation, general organization member, public visitor, before/after window boundaries, staff override, and completed/archived read-only behavior.
7. Run migration and php lint on staging, then schedule the CLI worker only after operational acceptance.

## Phase 2: Explicit announcement discovery (October 25)
- Migration 076 adds nullable announcement_published_at. No show becomes discoverable merely because its stage changes.
- Authorized production staff can publish or withdraw basic member-facing announcements from Productions & Seasons.
- Signed-in mobile Theatre lists include only explicit published summaries for productions outside the user's active production memberships; these contain id, title, season and lifecycle stage only.
- Announcement-only production detail avoids member-only channels and casting API calls. Member-only schedule/calendar rules remain unchanged.
- Private artwork and unpublished casting are not exposed with announcement previews. Native stage fallback remains until a separately approved/public artwork serving policy is implemented.
- This is authenticated general-member discovery, NOT yet a public unauthenticated website listing. No new public URL or visitor-facing route has been introduced.
- Legacy is_active still governs all member-only permission checks, and web production listings are not yet harmonized.
- Migration 076 and migration 075 must be applied before deploying PHP changes. PHP lint, mobile typecheck, staff/guardian access and real device testing remain required.

## Phase 3: Approved public production discovery
- Public /productions and /productions/view?id=ID present only entries whose announcement_published_at is set, and show title, season, lifecycle stage and audition/performance date windows. They do not query casting, schedule, student, or channel records.
- Public landing header links to the production directory. Announcements may be withdrawn in production management.
- Mobile announcement-only production details show audition/performance windows and the public production link; private production hub calls remain disabled for previews.
- No public artwork streaming route is provided yet. Public casting remains separate and first-name/last-initial only. Full names remain in deliberately published, authenticated member views.
- Requires migrations 075 and 076 deployed before enabling routes. Do not run the date worker until staging validation passes.
- Pending: web authenticated member/preview parity, public-approved description and artwork, audition signup association, per-stage permission cutover, push delivery verification, archival operational behavior and test coverage.

## Phase 4: Staff automation override and authenticated date parity
- Migration 077 adds `lifecycle_automation_paused` (default false).
- Authorized production managers can pause automatic stage transitions and record a note. The CLI lifecycle worker skips paused productions; unpausing makes eligible date transitions possible at the next worker run.
- Authenticated web production workspace now displays the lifecycle stage and configured audition/performance dates instead of only the legacy Active badge.
- Authorized production-context queries now provide audition/performance windows, not only announcement previews. The mobile production page shows the date card for assigned members as well as announcement-only viewers.
- No automatic worker is scheduled. Do not schedule it until migrations 075–077, timezone policy, and lifecycle integration tests are validated.
- CAUTION: These changes do NOT remove the legacy `is_active` authorization gate or prove all pages safe when stage is Upcoming/Planning. The new lifecycle stage remains informational for some existing routes; staff must not assume it is a privacy enforcement control.
- Verify date edge cases, archived read-only policies, and former-guardian revocation before rollout. Backfill intentionally retains existing members' access.

## Phase 5: Lifecycle enforcement at ProductionContext boundary
- Nonstaff production member queries (`activeProductions`, `userHasActiveProduction`, `isActiveMember`, `audienceType`) now require BOTH legacy `is_active=1` AND lifecycle stage `in_production` or `performing`. Guardian/family relationship checks remain required.
- Authorized platform staff's production selection can include Upcoming/Planning workspaces even if legacy `is_active=0`; this allows staff preparation without opening family schedules.
- Published public production summaries remain a separate read-only discovery path with an explicit staff publish control.
- Transition to Completed immediately removes ordinary active-member production-context access. Historical/archive readers still require separate review. This is not a comprehensive read-only archive implementation.
- Remaining risk: Audit all direct production_memberships, schedule, channel and notification SQL codepaths that bypass ProductionContext. Do NOT claim broad authorization parity yet.
- Legacy activation remains necessary as a second permission check; no retirement yet. Test active stage transitions and former guardian revocation before deployment.
- Earlier migration backfill moves previously active legacy Planning shows into In Production to avoid surprise lockouts. Stage editing back to Planning now intentionally restricts nonstaff access.
- Do not deploy these stage enforcement changes before all migration 075–077 SQL changes and production-member access regression tests are completed.

## Phase 6: Casting publication boundary audit
- Published cast/callback content is now strictly the saved review snapshot. A missing or malformed snapshot yields an empty list rather than reading the current private casting board.
- Casting release in-app notice creation now requires the production to be both legacy-active and in_production/performing. Every proposed recipient is rechecked through ProductionContext::isActiveMember (including active guardian/student linkage), rather than trusting the raw production_memberships audience query.
- These controls intentionally prevent publication notifications during Planning; release behavior for approved public announcements remains separate and must be designed explicitly.
- Outstanding: existing app_notifications retention/delivery workers may still require reauthorization before displaying/sending a queued notice after guardian revocation. Audit direct SQL in archive, community, schedule and push codepaths; no production deployment until the full safeguarding test matrix passes.

## Phase 7: Notification visibility on access revocation
- A shared ProductionNotificationAccess policy now rechecks the current production-member and active guardian/student relationship before web or mobile feeds show `casting_release` notifications.
- The mobile mark-read endpoint also rejects casting notification IDs that are no longer authorized.
- Notification records remain in the database for audit; the feed hides them after access is lost.
- This fix currently covers `casting_release` source-type only. Other notification source types and the outbound push queue still need source-aware recipient authorization at delivery time; absence of that control is a pilot blocker.
- Mobile unread count is calculated from authorized results in the current page window, rather than the unrestricted database count. Pagination/count parity remains to be improved.
- No migration is needed for this slice, but migrations 075-077 and all previous code changes still require integrated staging validation.

## Phase 8: Production-scoped web push authorization
- Migration 078 adds nullable production_id to push_queue. Producers can pass a production id through the optional final argument of PushService::queue.
- A production-scoped push is reauthorized at actual delivery with ProductionContext::isActiveMember, including the lifecycle stage and guardian relationship. Revoked recipients are suppressed rather than sent to.
- Publishing a cast or callback snapshot now queues a neutral, production-scoped web push for eligible recipients. Push text does not contain student names or casting decisions. Dedupe keys include the reviewed snapshot so resubmitting identical content does not create another push.
- This covers NEW explicitly production-scoped push entries. Existing unscoped queued jobs and other notification categories are NOT proven safe under lifecycle changes. Audit/fix all push producers before declaring delivery parity complete.
- The push worker and publication flow require migration 078 before deployment. If 078 is missing, publishing may fail; apply migrations in order 075–078 on staging first.
- No push worker was run; browser delivery, native-device delivery, database transactions and safeguarding regression cases remain unverified.

## Phase 9: Archived lifecycle access and guardian revocation
- Historical production listings, details, channel previews, and archive file reads now use `lifecycle_stage='archived'`, not legacy `is_active=0 AND status='archived'`.
- An old guardian production membership no longer independently grants historical archive access. Guardians require a currently active family relationship to a student in the archived production. Historical audience classification also excludes stale guardian membership and adds guardian only when the active family relationship check succeeds.
- Staff archive permissions and qualifying non-guardian historical memberships remain available.
- Archive reads are historical only; these changes do not implement comprehensive write-blocking on all Completed/Archived operations.
- IMPORTANT: This change requires migration 075 and coordinated rollout; audit every archive-linked endpoint and run revoked-guardian regression tests before deployment.
- The push producer audit remains incomplete: only explicit production-scoped push jobs receive the new delivery-time production check. Do not declare notification safety parity complete.

## Phase 10: Closed-production operational write guard
- Shared ProductionLifecycleGuard::assertWritable checks the production lifecycle stage before accepting operational mutations; Completed and Archived fail closed.
- CastingService add/update/finalizeRoster now enforce it at the service boundary, not only in staff web forms.
- CastingCommunicationService result messaging, publication creation/update/unpublish and public casting-page settings also enforce it. Deliberately published historical snapshots remain readable.
- ScheduleNoticeExperience rejects publishing new schedule-update communications for Completed/Archived productions.
- Staff lifecycle-stage updates, approved announcement basics, historical reads and administrative record retention are not blocked by this operational guard.
- This is a targeted first pass, NOT a comprehensive guarantee: direct schedule/calendar edits, channels, forms, attendance, and other write endpoints must be audited and guarded individually.
- Do not deploy or schedule automation until regression tests verify that legacy routes do not bypass these checks.

## Phase 11: Closed production schedule and attendance safeguards
- ScheduleCreateExperience refuses new schedule items for Completed/Archived productions.
- ScheduleImportExperience refuses actual ICS imports for Completed/Archived productions.
- CalendarExperience refuses duplicates and cancellations on Completed/Archived production items.
- AttendanceService checks the owning production before roster changes or new absence reports. A new ProductionLifecycleGuard::assertScheduleItemWritable resolves the item's production and checks stage.
- Existing historical schedule and attendance records remain readable. Staff may still edit the lifecycle stage in production management, but operational writes require a writable stage.
- Incomplete: audit direct schedule edit endpoint (`/production/edit`), channel messages/posts, forms, shift signups, file changes, and attendance routes outside AttendanceService. A complete read-only guarantee is not yet established.
- Staging PHP lint and regression tests are mandatory before deployment; these commits have not been locally executed.

## Phase 12: Direct schedule editing and day-brief write closure
- ProductionExperience::saveScheduleChange now checks the owning schedule item's lifecycle before updating it, blocking Completed/Archived direct edits via /production/edit.
- ProductionDayService::saveBrief now rejects changes to production-day briefs after completion or archival.
- These are targeted service-level protections; remaining channel, form, volunteer, file, and other operational writes require independent audits.
- No automated tests or production deployment were performed as part of this commit series.

## Phase 13: Community posting and production file closure
- CommunityExperience::createPost now rejects new posts and native mobile posts for production-linked channels where the lifecycle is Completed or Archived. Organization-wide channels without a production association are unaffected. Historical channel reading remains separate.
- ProductionFileExperience::save and its archive/unarchive operation now reject changes when a production is Completed or Archived. Historical file reads remain available under existing permissions.
- Existing moderation actions (hide/delete/pin/reactions), channel-administration writes, other Community endpoints, forms and volunteer scheduling have NOT all been lifecycle-guarded. Moderation may need to remain possible after archival; model that exception explicitly.
- This pass is code-only. No PHP lint or database/device integration tests were run. Do not claim lifecycle write parity or pilot readiness from these fixes alone.

## Phase 14: Unknown lifecycle stage fails closed (October 25)
- `ProductionLifecycleGuard::assertWritable` now permits operational writes only for the four explicitly writable stages: Upcoming, Planning, In Production, and Performing. Completed and Archived retain their existing closed-operation error. Null, malformed, or future unknown stages now raise an error instead of silently permitting writes.
- This reduces accidental authorization bypass from inconsistent migrations or unexpected lifecycle values. It does not supersede staff authorization, guardian checks, or resource-specific safeguards.
- Repository change committed via GitHub connector. PHP lint, automated tests, migration checks, deployment, and runtime verification have **not** been executed for this change; pilot readiness remains unchanged.
- Still open: inventory direct write routes for forms, volunteer shifts, channels, reactions and moderation; remove contradictory legacy `is_active` semantics only after audited cutover; verify timezone/automatic archive behavior and mobile parity; test former-guardian access and all notification sources.

## Phase 15: Production-group channel fallback stage gate (October 25)
- `ProductionGroupChannelAccess::canAccess` now requires In Production or Performing for its group-inherited access query, in addition to legacy active state, active group membership, and existing guardian relationship checks. A group-inherited channel grant can no longer survive merely because legacy `is_active` remained set after completion.
- **Important remaining gap:** the earlier explicit `channel_members` shortcut in the same function still returns a grant without checking production lifecycle, current production membership, or former-guardian relationship. This separate authorization path needs policy-aware remediation and adversarial tests before pilot. Direct channel permissions must not become a bypass for removed production members or former guardians. Archived historical reads should be handled separately from live group permissions.
- Change has not been PHP-linted, integration-tested or deployed. No readiness percentage change is justified.

## Phase 16: Direct production-group channel authorization (October 25)
- `ProductionGroupChannelAccess::canAccess` now resolves the channel scope before evaluating its explicit `channel_members` grant. For production-group channels it requires `ProductionContext::isActiveMember` at the time of each access check. This includes the active-production lifecycle check, active production membership, enabled user, and current guardian/student relationship.
- Ordinary non-production-group channels keep their existing explicit-channel membership semantics. Historical archived conversations remain a separate archive authorization path rather than silently granting live group access.
- **Integration review required:** Inspect channel lists, attachments, native mobile group access and staff who have a direct channel assignment without production membership. Verify intended behavior and run direct-request tests for removed cast, former guardians, closed productions, and unaffected organizational channels.
- The change was committed through GitHub; no runtime PHP lint, database-integrated tests, or mobile tests were executed. Pilot approval remains blocked on full safeguarding verification.

## Phase 17: Community channel administration closure (October 25)
- CommunityManagementExperience now applies ProductionLifecycleGuard when creating or editing a production-linked channel, including validating the existing production on reassignment so a closed show's channel cannot be moved to evade restrictions.
- Restoring an archived production channel requires the production to be writable. Channel archival remains available to administrators after production completion because hiding inappropriate communication can be a safeguarding action; existing audit logging is preserved.
- Organization-wide channels are not restricted by production stages. Closed productions still require a separate, intentionally authorized historical viewing experience.
- Follow-up required: safeguard channel archival against races, audit channel membership administration and Community reactions/comments/edit routes, and test creation, editing, cross-production reassignment, restoration, and safety archival across lifecycle stages.
- Code is committed but PHP lint, integration tests, and staging deployment were **not** executed in this connector-only pass. Do not change pilot readiness classification without test evidence.

## Family Hub: optional student account email invitation (October 25)
- Family Manage / Onboarding Add Child now accepts an optional child email. Without an email, the existing guardian-managed profile workflow remains unchanged.
- With an email, the guardian-created student profile keeps its existing student role and family relationship; a 48-hour hashed single-use activation token is stored using `auth_email_verifications`, and MailService queues a student invitation. Existing email addresses are rejected to avoid duplicate identity creation.
- `/student/activate` verifies the invitation and lets the student create an 8-character-minimum password on the **same** profile; email verification and audit recording occur during activation. This does not assign production access or bypass staff membership approval.
- Queue processing must already be operational for actual delivery. This change does not assert that an email was delivered.
- **Unverified:** PHP lint, SQL schema compatibility, mail-worker delivery, successful/expired/replayed links, concurrent activation, child identity/address corrections, resend flow, failed-email recovery, guardian/child session interactions, and real-device mobile support. No live migrations/deployment/tests were performed. Existing child profiles with no email need a future staff/guardian invitation-management affordance.

### Student invitation email worker eligibility
- `MailService::process` formerly suppressed all queued user-addressed mail for account statuses `managed` and `pending_verification`, even when the message category was `account_security`. The delivery eligibility policy now allows only **account-security** mail to those states, as well as existing invited accounts. Non-security mail remains suppressed until activation.
- This fixes the managed-child invitation delivery path and existing adult verification queue eligibility. Operational mail transport remains subject to configured worker, queue monitoring and staging delivery verification; no real messages were sent in this change.

## Closure security hardening (October 27, 2026; partial)
- FormExperience now checks ProductionLifecycleGuard inside transactions before accepting a production-linked form submission or staff approval/rejection. Organization-wide assignments with no production ID continue to work. Historical submitted and reviewed records are not deleted or modified by this guard.
- ProductionNotificationAccess casting-offer visibility requires an **active parent/guardian** relationship, not merely an active caregiver relationship. Delivery-time authorization and all notification source types still require broader audit.
- ProductionArchiveService historical parent/guardian access now requires an active parent/guardian relationship in archive listing, production access and audience evaluation. Prior relationship or pickup-only authority does not grant guardian archives.
- CI safety workflow passed after the three source commits; source-contract regressions added for the two form guards and historical role boundaries. These are not MySQL integration tests or hosted production validation.
- **Still open:** all remaining mutation paths, Community interactions and volunteer approvals, current family actionable filtering, readiness reports, confirmed completion and archive actions, automatic worker parity, native/mobile parity, staged transactional rollback testing and live deployment verification. Do not claim the Complete → Archive workflow has shipped.

### Additional service-boundary closure (October 27)
- Volunteer approval requests and staff approval/decline decisions now check the production lifecycle inside their transactions. Organization-wide shifts with a null production ID remain unaffected. Pending historical requests are retained rather than automatically decided.
- Self-service signup cancellation is blocked for completed or archived production shifts. Historical service corrections need a separate authorized, audited operation; regular volunteer cancellation must not be used for post-close changes.
- Community heart reactions now check production lifecycle while holding the parent post in a transaction. Closed production posts remain readable only where existing authorization allows, but participant reactions no longer change. Organization-wide posts remain interactive.
- Added source-contract regression checks. Database-backed concurrency, guardian revocation, mobile route parity, hosted deployment and manual acceptance are still outstanding.

### Community lifecycle and relationship UI parity (October 27 follow-up)
- Community post composer and mobile posting-capability checks now fail closed for completed, archived, missing, and unknown production lifecycle stages. The create-post service already enforces the transactional write boundary. Organization-wide channels continue using ordinary permissions.
- Production guardian Community audience eligibility now requires active parent/guardian relationships, not caregiver links. Historical channel visibility and group/team membership authorization still require broader persona testing.
- Channel edit/create revalidates both relevant production lifecycle scopes inside the database transaction, in addition to its earlier input validation. This is not a proof against all concurrent stage transition races and requires database-level integration verification.
- Source regression contracts added; hosted access and mobile behavior are unverified.

### Operational email reminder closure (October 27)
- NotificationReminderService now excludes form-assignment reminders when the associated production is completed, archived, missing or at an unknown stage. Organization-wide assignments with a null production ID are unaffected.
- Production-specific volunteer shift reminders and eligibility-warning email jobs likewise require a writable lifecycle stage. Organization-wide shifts remain eligible.
- Student form reminders are addressed only to current active parent/guardian relationships; caregivers are excluded. Recipient revocation for previously queued emails still requires a distinct delivery-time authorization mechanism.
- Regression source checks added. PHP safety CI passed. This does **not** resolve old queued emails, all production notification origins, native/web push, or delivered-message verification.

### Critical temporary guard: automated terminal transitions (October 27)
- The unscheduled date worker previously transitioned Performing → Completed and Completed → Archived without a readiness review. It now only advances In Production → Performing when the performance start date has arrived and the end date has not passed. It never automatically closes or archives an operational workspace.
- Completion and archival counters remain in worker output for compatibility but stay zero until controlled transitions are implemented. Existing manual lifecycle editor still allows stage selection and requires integration into the upcoming confirmation workflow; it must not be treated as readiness-reviewed.
- Coordinate date-worker deployment with manual stage-guard closure before enabling cron. Earlier documentation describing automatic completion and archival is superseded by this safety restriction.

### Temporary terminal-stage editor gate
- The old admin stage dropdown now rejects transitions into or out of Completed/Archived. Editing nonterminal dates and staying on the same terminal stage remains possible. This prevents bypass of the future controlled readiness and audit confirmations, but temporarily means staff cannot complete, archive or reopen through that editor.
- Finish dedicated staff-confirmed completion, archive and restore services promptly before CTSMD performs actual closure. The new checks are a protection, not the requested finished workflow.

### Controlled completion V1 (October 27)
- Production detail now offers a completion readiness panel for In Production and Performing shows. It reports future schedule entries, incomplete form assignments, and pending volunteer approval requests as warnings, preserving them without auto-resolution.
- Staff must acknowledge read-only consequences and type the exact production title. CSRF is checked in the existing staff controller; the completion service independently checks production.manage authorization, locks the production row, recalculates readiness, transitions to Completed, sets completed_at and pauses automatic transitions, writes audit metadata, and commits atomically. Repeated completion is rejected.
- This is an initial readiness subset, not a complete operational sign-off. Casting offers, attendance review, tasks, unsent notices, service-hour verification, queued messages, family-facing current views, and archive readiness must still be added before a real-family lifecycle acceptance.
- No records are deleted. Bluehost database-backed verification has not been performed.

### Controlled archive V1 (October 27)
- Staff now have a distinct Archive Production confirmation after completion. It requires the exact title, acknowledgement, staff production.manage authorization and existing CSRF token. A row-locked transaction changes lifecycle to archived, marks the legacy workspace inactive/archived, pauses automation, and writes an archive audit event with the previous active state.
- Existing roster, casting, attendance, form submissions, volunteer service history, files, and Community records are preserved, not deleted. Archive access continues to use the existing authorized historical viewer.
- Legacy Activate and Return to Planning paths now reject reopening terminal lifecycle states; safeguarded restoration needs a separate reviewed service.
- Remaining: comprehensive pre-archive checks, pending communications cleanup, current family dashboard cross-production behavior, live staging database transaction/persona tests and deployment verification. This is not authorization to archive a real production yet.

### Completion readiness extension (October 27)
- Readiness now includes unresolved pending casting offers (blocking), unmarked attendance (warning), and draft schedule notices (warning), in addition to future schedule entries, incomplete forms and pending volunteer approvals. Blocking offers are rechecked under the completion transaction before the lifecycle can change. The staff completion button is disabled while blockers exist.
- Warning counts remain visible for acknowledgement, but no records are silently completed or deleted. The readiness inventory is not yet exhaustive: production tasks, service-hour verification, delivery queues, and cross-production family behavior still need coverage and integration testing.
