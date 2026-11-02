# Commerce performance scheduling and multi-show seat management

Implemented on `audit/board-pilot-readiness`, October 31, 2026. Backend milestone `7f59418`; calendar/review/customer milestone `3055782`. No deployment, production SQL import, real payment, audience email or live-account test was performed.

## Staff entry points

- Commerce → **Schedule**: `/admin/commerce?tab=schedule`.
- Commerce → **Seat blocks**: `/admin/commerce?tab=blocks`.
- Existing **Performances** links to both tools. Its single-performance creation and allocation forms now enter the same review workflow.
- **Venues & seating → Venue timezones** configures an IANA timezone per venue.
- A performance’s **Pricing & availability → Performance schedule & cancellation** controls future edits, unpublishing and cancellation, or reviews an older record’s timezone.

All commands require current Commerce capabilities and CSRF. Review records are scoped to the current organization, actor and authenticated session, expire after 20 minutes, and contain the server-side reviewed payload. Apply does not trust browser-submitted prices, performance IDs or a browser-supplied success status.

## Calendar creation

Choose a production and calendar month. Day buttons populate exact-date rows; native date/time inputs provide a keyboard alternative. Multiple rows on a date represent distinct matinee/evening performances. Clicking a calendar day alone creates nothing.

Use optional weekly templates with independent Monday–Sunday time lists and a bounded inclusive date range. Individually selected showtimes can be added to the template. Exceptions exclude a whole local date or one exact local date/time. Duplicate requested instants are rejected rather than silently merged. A batch contains 1–100 actual performances and covers at most 366 days of recurrence.

Review shows the production, venue/auditorium, layout version, seating mode, ticket prices, every local showtime, UTC offset and UTC instant. Conflicts disable creation. Apply revalidates the payload and creates every performance/inventory snapshot in one transaction through `CommerceInventoryService::createLocked`; a failure creates none. No performance or inventory is inferred from production start/end dates or from blank calendar days.

Each new performance uses the chosen published layout and reviewed ticket-price map. Choose reserved seating or general admission; GA uses the existing inventory capacity and shared mode configuration. New sales are closed, check-in is disabled, and fees/tax/discount start at zero. Configure those settings deliberately before opening sales; current order snapshots are never rewritten.

A database uniqueness key reserves each **organization + physical auditorium + UTC showtime**. Different layout versions or productions cannot double-book that auditorium at the same instant. Separate auditoriums in a multi-space venue can host simultaneous shows. This is an exact-start collision check; no duration/overlap model has been invented. Cancelled slots remain reserved as historical identities. Idempotent creation keys return the original IDs after repeated submissions, and concurrent distinct requests cannot create duplicate slots.

## Timezones and daylight saving

A venue’s saved timezone takes precedence over `COMMERCE_VENUE_TIMEZONE`, then the existing `APP_TIMEZONE` configuration, with `America/New_York` as the installation default. Set the actual IANA venue name before scheduling. Updating this default or a venue setting does not rewrite existing performances.

Each performance saves its original local `starts_at`, explicit `starts_at_utc`, IANA timezone and offset in minutes. DST validation enumerates possible UTC instants and round-trips them to the requested local time:

- A spring-forward time that does not exist is rejected, never silently shifted.
- A repeated fall-back time requires an explicit first/second occurrence or a matching per-row offset.
- Two occurrences of the same fall-back wall time can be represented as different UTC instants if deliberately selected.
- Recurrence retains the requested wall time while each date receives its own correct offset.

The server’s current UTC clock determines whether a performance has started. Public choices display venue time and the saved offset. The existing inventory/order hold lifecycle is reused; scheduling does not introduce another expiry, locking or payment-state engine.

## Future edits, cancellation and history

An authorized manager can change a future performance’s date/time only before **any** order history exists, including expired or cancelled orders. Production/layout identity remains immutable under the existing protections. Once order history exists, retain that showtime and, if necessary, cancel and create a separate performance instead of silently moving tickets.

Version checks reject stale edits. Cancellation/unpublishing requires a reason and explicit acknowledgement when paid ticket holders exist. Pending or uncertain payments must be reconciled first. The transaction closes sales/door entry and cancels unpaid held orders, releasing their held inventory with order audit events. It retains paid orders, original ticket rows, original dates, financial snapshots and all audit history. Contacting purchasers and refunding paid orders are separate authorized workflows; no email or refund occurs automatically. Cancelled/completed/past shows are not rescheduled through this UI.

Cancelled/unpublished shows disappear from customer choices. Cancelled ticket retrieval/printing displays the performance status and suppresses the admission QR; existing ticket/order records remain. Door verification also rejects a non-scheduled show, regardless of stale browser controls.

## Shared seat blocking and release

The allocation service now supports:

1. One performance or multiple explicitly selected performances.
2. Actual scheduled performances within an inclusive **venue-local date** range.
3. All existing performances in one production, without creating future/inferred shows.

Choose seat keys, an allocation and a required reason. Preview lists each affected performance, venue, local showtime, section/row/seat, current state and conflict. Different layouts must contain every selected key; staff must review their actual labels. Missing keys, sold/held seats, incompatible allocations, cancelled/past/unreviewed shows and closed productions are conflicts. Whole-production scope deliberately reports these conflicts instead of silently excluding records; narrow the selection and review again.

Blocks/house/complimentary allocations may take only available/released positions or their identical existing allocation. Release may take only eligible blocked/house/complimentary positions. No administrative allocation can override held or sold inventory. Expired-but-not-yet-released held positions remain conservative conflicts until the existing authoritative expiration workflow releases them.

Apply locks performance rows in deterministic order and rechecks the exact reviewed rows. Whole-production/date-range scope is reselected under the production lock, so a new show added after preview requires another review. If anything conflicts or changed, **nothing is applied**. There is no partial-success override. Repeating the identical apply key returns the original result without duplicate mutation or audit.

Audit records retain the actor, timestamp, reason, batch key, affected performances/seats, previous state/reason/order reference and resulting allocation. Limits are 100 performances, 1,500 selected positions and 50,000 affected positions per operation. Blocks apply to the reviewed set at commit time; later-created shows do not inherit them automatically.

## Sales boundaries and customer choices

`CommercePerformancePolicy` is shared by order holds, payment initiation, cash/external-terminal completion, complimentary issuance and fulfillment. New sales require an explicitly created, registered, scheduled, future performance; open sales; and an existing published production in a writable lifecycle stage. Draft/unpublished, cancelled, completed, past and unreviewed legacy performances cannot accept new sales. Staff and public-preview paths use the same inventory and guard.

This supersedes the earlier sandbox exception allowing complimentary issuance while sales were closed. Complimentary issuance now obeys the same saleability boundary. Reissue/relocation preserves existing entitlements and additionally rejects unscheduled/unpublished/past shows; a sales-closed but otherwise scheduled future show can still have an existing ticket corrected.

A delayed simulator success cannot issue tickets after the performance becomes unsaleable. Its known success is retained, the order becomes `exception`, and inventory stays protected. Authorized Orders staff can explicitly simulate a full return of that known, unfulfilled payment, with a reason; unknown results cannot use this path. No real money is moved or claimed refunded.

The customer storefront groups actual approved future showtimes by production and date, displaying multiple time choices and their venues/offsets. Closed sales are labelled without a purchase link. Zero availability is labelled Sold out, or Currently unavailable when holds exist. Cancelled, draft, completed and unapproved/unpublished performances are excluded; dates without performances are absent. Ordinary visitors still cannot submit simulated purchases, and real public sales remain disabled.

## Hosted migration / FTP requirements

1. Keep Commerce/public sales paused for a reviewed maintenance window, record the current application revision, and back up the database.
2. Confirm Phase 1/2 migrations 101–106 are already present. Review and import **`database/migrations/107_commerce_performance_schedule.sql`**. FTPing SQL files does not execute them. Its column ALTERs must not be repeated blindly after a partial import.
3. Upload matching PHP files, including `CommercePerformancePolicy`, `CommerceScheduleService`, `CommercePlanningExperience`, the updated existing Commerce services/UI, CSS and **`assets/js/commerce-planning.js`**. No new top-level routes are needed.
4. Configure each venue’s actual IANA timezone. Review every legacy performance through its schedule/cancellation section, including the actual offset for repeated hours. Migration 107 intentionally leaves old UTC/timezone fields null: **it does not guess or backfill history**. Normalized showtimes occupy unique auditorium slots; an unreviewed historical row blocks additional bookings in that auditorium and cannot accept new sales.
5. If legacy records duplicate one physical showtime, contain a nonexistent local time, or require second-level timestamp precision, keep the affected area paused for a separate reviewed historical/financial resolution. Do not invent offsets, shift sold tickets, merge accounts, delete transactions or bypass the unique slot. The normalizer preserves the original wall time; minute-based UI does not silently truncate legacy seconds.
6. Confirm exact showtime IDs/UTC metadata, production publication, sales status and separate storefront approval. Run synthetic schedule/create/cancel/block/release/purchase/check-in tests on the host. Verify that anonymous visitors remain denied and real payment flags remain off.

Migration 107 adds nullable venue/performance timezone metadata, a schedule-status field, unique showtime slots and idempotent schedule/allocation batch tables. It does not rewrite accounts, safeguarding relationships, production ranges, layout versions, orders or tickets. Schema readiness detects missing tables/columns and displays setup guidance. No runtime migration runner was added.

Rollback: pause writes first and retain additive schema, slots and history. Restoring an older application without these guards could sell cancelled/unreviewed shows; do not resume sales with that older application. Any database restore must follow the approved backup/restore process and reconcile post-backup transactions. No rollback/restore was executed here.

## Verification

All 22 Commerce acceptance suites passed locally using only the disposable loopback schema `ctsmd_rc1_commerce_20261030` and synthetic accounts, plus all 18 existing PHP safety suites, changed-file PHP lint, JS syntax, geometry and RC1 tooling checks. [Machine-readable results](evidence/connect-commerce-scheduling/local-verification.json).

| New suite | Verified behavior |
| --- | --- |
| `commerce_scheduling.php` — 36 checks | Irregular dates; two shows/day; empty intervening dates; exact inventory counts; duplicate slots/input; weekly templates/additions/exceptions; DST gaps/folds; venue timezone changes; stale edits; order-history protection; paid acknowledgement; unpaid hold release; cancellation; known late-success recovery; legacy review; draft/completed denial; GA batch mode |
| `commerce_bulk_allocation.php` — 21 checks | Read-only preview; all-or-nothing block/release; idempotency; sold/held/missing conflicts; stale preview; selected/range/all scopes; new-show scope changes; authorization and audit detail |
| `commerce_schedule_concurrency.php` — 3 two-process races | Distinct duplicate batches: one winner; identical retried batch: same two rows with both callers returning; seat blocking versus purchase: one winner, no incompatible mutation |
| `commerce_planning_http.php` — 18 checks | Real authentication/CSRF; calendar and review UI; no creation on preview; double-submit handling; multi-show allocation; grouped matinee/evening choices; missing dates; closed/cancelled states; sold-conflict recovery |

Earlier geometry/quantity/landmark tests were corrected to use distinct actual showtimes instead of double-booking cloned layouts in one auditorium. The duplicate guard was retained. Existing cross-channel purchase/scan/refund race coverage also passed.

Actual local browser checks: calendar entry created two reviewed performances on May 15; a four-position block applied across the May 8 matinee/evening shows; the same four blocks were released through a one-day date-range review. A stale review required re-preview. Enter on a calendar day populated its exact date and focused the time input. Desktop 1440px and mobile 390px checks found no document-wide horizontal overflow on the checked calendar, showtime and allocation-review screens. Screenshots contain synthetic data only:

- [Desktop calendar](evidence/connect-commerce-scheduling/calendar-desktop.png) / [mobile calendar](evidence/connect-commerce-scheduling/calendar-mobile.png)
- [Two-show schedule review](evidence/connect-commerce-scheduling/schedule-review-desktop.png)
- [Multi-show allocation review](evidence/connect-commerce-scheduling/bulk-review-desktop.png) / [mobile release review](evidence/connect-commerce-scheduling/bulk-release-mobile.png)
- [Desktop customer showtimes](evidence/connect-commerce-scheduling/showtimes-desktop.png) / [mobile customer showtimes](evidence/connect-commerce-scheduling/showtimes-mobile.png)

These are local browser and automated results, not hosted, physical-device, physical-scanner, screen-reader or remote CI signoff. ctsmd.me migration state, historical timezone evidence and host-specific operation remain to be verified after an authorized deployment. No deployment or real-payment activation was performed.
