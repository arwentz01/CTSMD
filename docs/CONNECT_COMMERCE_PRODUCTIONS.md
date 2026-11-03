# Production-centered Commerce

> **Current-source addendum (2026-11-02):** This report retains its earlier pricing/production acceptance evidence. The latest behavior and release status are consolidated in [Current Commerce implementation](CONNECT_COMMERCE_CURRENT.md). Add Performance can now set sales **open or closed** plus fees/tax at creation; legacy performance discount is collapsed under Advanced; saved drawer changes refresh the workspace on close. Calendar scheduling is more compact with advanced recurrence collapsed. See below for original pricing milestone details.

Implemented on `audit/board-pilot-readiness`, retaining the Phase 4 four-workspace shell and shared CTSMD navigation. No deployment, public-sales activation or live-payment activation.

## Staff workflows

Overview now groups ticket sales by production. Production cards show venue/space, aggregate sold positions and physical capacity, sellable availability, protected holds, allocations and net settled revenue. Individual performances are grouped by venue-local date and time, with timezone and UTC offset. Search matches production or venue; filters select on-sale, sold-out and attention-needed showtimes. Past productions collapse into a separate disclosure. Totals remain production-wide when a performance filter hides rows.

Productions & Performances uses the same records and counts, with artwork when published and available, the series date range, Add Performance and contextual Manage actions. Existing routes remain available. The calendar and seat-blocking tools remain secondary navigation.

Add Performance opens a native drawer and preselects its production. Staff choose venue/space, published layout, seating mode, dates and times. Multiple matinee/evening rows can be added or removed without closing it. Known seating zones and venue timezone are visible. Review uses `CommerceScheduleService::preview/create` and the existing atomic inventory creator; it introduces no second scheduling backend. Duplicate/venue conflicts, DST gaps and ambiguous local hours retain the existing authoritative checks. Invalid drawer submissions preserve entered fields; revising a review restores dates, times, offsets, layout and DST choice. The original build started sales closed by default; the later Add Performance update permits explicitly choosing **On sale** during reviewed creation, while **Closed** remains the default.

Production pricing supports normal decimal currency fields, editable categories and seating zones, production defaults, explicit inheritance and selected-performance overrides. A review lists the affected showtimes, old prices, new prices and existing order counts. A reason is required; staff must acknowledge order snapshots when history exists. Existing performances are not silently migrated to inheritance. Default revisions affect future scheduled/draft inheriting performances, preserving past and cancelled pricing and explicit overrides. New performances can opt into defaults through the same scheduler. The calendar also uses the normal money/category editor. Later updates moved per-performance discount into an Advanced disclosure; promo codes are configured separately in Commerce → Box Office → Discounts & promo codes.

Each Manage drawer has contextual disclosures for sales/readiness, pricing, seat allocations, recent orders and cancellation/administration. Its recent orders can open inside the same drawer, subject to reporting permission. The full order list can be filtered to that performance. Pricing and sales settings are separate so changing a sales switch cannot silently overwrite prices or detach inheritance. Sales-setting submissions reject stale settings.

## Counts and financial meaning

- Physical capacity is the actual inventory-position count for each recorded performance; a production total sums those snapshots across the series, including retained historical/cancelled records. This does not claim those positions are all currently for sale.
- Sold positions consume capacity even if a ticket is administratively revoked without releasing its seat. Complimentary-issued admissions also consume positions; unissued complimentary allocations are reported as allocated inventory.
- Sellable availability is free/released inventory **only when existing backend readiness and organization ticketing gates permit sales**. Closed, past, cancelled, unpublished or unreviewed shows have zero sellable availability.
- Expired unpaid holds count as effectively free in this read-only projection. Existing hold services still expire/release them transactionally. Payment-pending/uncertain holds stay protected even after the original deadline.
- Sold out means all physical positions are sold. Holds and blocks exhausting free inventory produce unavailable states rather than a false sold-out label.
- Revenue includes order fees and tax. Simulator/cash/terminal totals use settled paid orders and exclude refunded orders. Stripe uses authoritative captured amounts minus confirmed refunded amounts, including verified partial returns or exception states. Currency totals remain separate. Inventory-only/seller/door access does not expose revenue; reporting permission is required.

## Required migration

`database/migrations/110_commerce_production_pricing.sql` adds three Commerce-only InnoDB tables:

1. Organization/production default prices and revision.
2. Explicit performance inheritance/override bindings, constrained to the owning performance/organization.
3. Actor-scoped idempotent pricing batches.

Persistent defaults and opt-in inheritance cannot be represented reliably by copying an arbitrary performance's prices. The migration is additive and performs **no backfill or rewrite** of performance prices, order lines, held/paid order snapshots, inventory, payment settings or launch gates. Existing order and inventory schema is unchanged.

Migration 110 was applied only to the existing disposable loopback test schema. **It has not been applied to the normal application or any hosted database.** A future authorized release must review/back up and apply it after 101–109. Overview/management retain existing data without it; the inheritance UI explains the prerequisite. Fresh RC1 bootstrap includes it automatically.

Pricing application rechecks the review under production/performance locks and writes prices, bindings, audit events and its retry result in one transaction. Concurrent order history, default revisions or scope changes invalidate a review. Existing reservation services continue to snapshot the authoritative performance prices. Deadlock/serialization rollback never permits partial application; staff can reload and review again.

## Verification

All data was synthetic and confined to the disposable loopback schema. No provider network call, live financial transaction, public launch or mail worker was enabled by this task.

- **31 existing Commerce regression suites passed**, covering access boundaries, inventory, held/paid snapshots, refunds, complimentary issuance, admission, payments/delivery, scheduling and concurrent cross-channel purchases. The existing inventory snapshot test now makes its price change through the acknowledged review path; its original immutable-order assertion is retained. [Results](evidence/commerce-productions/regressions.json).
- **25 new production/pricing assertions passed**: opt-in defaults, inherited reserved/GA prices, multiple same-day showtimes, bulk override isolation, stale-review rejection, retry idempotency, order snapshot preservation, refunds, true sold-out behavior, blocked versus sellable capacity, expired versus uncertain holds, role boundaries and exact currency parsing. [Details](evidence/commerce-productions/verification.json).
- A separate two-process pricing/purchase race passed: a coherent old/new quote or safe rollback, no duplicate/partial hold. Existing reserved/GA competing-purchase suites also passed.
- **33 production browser assertions**, **59 existing staff assertions**, and **34 existing storefront assertions passed**. New screens were checked at 1440/768/390/320px for document overflow, labels and errors. Real browser submissions exercised defaults, category creation, multiple showtimes, duplicate rejection with retained input, schedule revision, overrides, inheritance, mobile management and Escape dismissal. A targeted follow-up also verified authorized order management within the same drawer. [Production](evidence/commerce-productions/production-browser.json), [staff](evidence/commerce-productions/staff-browser.json), [storefront](evidence/commerce-productions/storefront-browser.json).
- All **10 pilot PHP safety suites**, the copied-local-environment hosted-access run, final planning HTTP checks, geometry checks, 12 Apache camera-policy cases, PHP/JS syntax and whitespace checks passed. [Safety](evidence/commerce-productions/safety.json).

CI now includes the production pricing, concurrency and browser tests. Browser tests use the existing guarded fixture helper, real login/CSRF and loopback router. They accept the same `CTSMD_COMMERCE_*`, `CTSMD_PLAYWRIGHT_MODULE` and optional `CTSMD_CHROMIUM_EXECUTABLE` settings as the earlier suites. Use a fresh fixture per production-browser run because its scheduling assertions intentionally create actual performances.

## Screenshots

Native Chromium captures of synthetic data:

- [Overview desktop](evidence/commerce-productions/overview-populated-1440.png), [mobile](evidence/commerce-productions/overview-populated-390.png)
- [Production showtimes](evidence/commerce-productions/performances-populated-1440.png), [mobile](evidence/commerce-productions/performances-populated-390.png)
- [Add Performance](evidence/commerce-productions/add-performance-desktop.png), [mobile](evidence/commerce-productions/add-performance-mobile.png), [schedule review](evidence/commerce-productions/add-review-desktop.png)
- [Pricing review](evidence/commerce-productions/pricing-review-desktop.png)
- [Management](evidence/commerce-productions/management-desktop.png), [mobile](evidence/commerce-productions/management-mobile.png), [authorized order detail](evidence/commerce-productions/management-order-detail.png)

## Limits

Scheduling and reviewed pricing remain bounded to 100 affected performances per batch; default revisions affecting more than 100 inheriting shows are rejected rather than partially applied. Pricing uses the existing two-decimal currency and category/zone validation contracts. Venue-conflict detection retains the existing exact-auditorium/showtime rule; no duration/turnaround-time collision model was added. Seat-blocking and the full order list retain their existing workspaces. Browsers without JavaScript use full server pages rather than drawers. Responsive emulation is not a physical-tablet usability study or screen-reader certification.

## Milestones

- `649b424`: persistent reviewed pricing inheritance and authoritative production sales projections.
- `abc9846`: production cards and Add Performance, pricing and management drawers.
- Final verification/evidence milestone: see this branch's commit history.
