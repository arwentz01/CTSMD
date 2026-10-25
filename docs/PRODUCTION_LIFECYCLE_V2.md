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
