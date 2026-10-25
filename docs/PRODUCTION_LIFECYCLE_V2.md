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
