# CTSMD Commerce — current implementation and release status

**Updated:** November 2, 2026  
**Repository/branch:** `arwentz01/CTSMD` · `audit/board-pilot-readiness`  
**Scope:** current source implementation of production-centered Commerce, public tickets, combined-cart checkout, discounts, and the Eastern Time policy. Historical phase reports remain dated evidence, not the latest operational specification.

> **Status:** Code is committed and isolated CI has passed through timezone milestone `2f1dfb2` ([RC1 acceptance](https://github.com/arwentz01/CTSMD/actions/runs/41576124381), [PHP safety](https://github.com/arwentz01/CTSMD/actions/runs/41576124361)). This **does not establish that CTSMD.me has the same files or schema**. No hosted migration, deployment, live payment activation, email-delivery approval, or production signoff was performed as part of the work recorded here. The operator reported reaching a Stripe **test** Checkout page during earlier hosted testing, but end-to-end payment/QR/email acceptance on that host is not established by that observation.

## Primary entry points

| Surface | Route | Current behavior |
| --- | --- | --- |
| Commerce overview | `/admin/commerce?tab=overview` | Production-level sales and readiness summary, permission-aware financials |
| Production/performance management | `/admin/commerce?tab=performances` | Production cards; per-show Manage and Add Performance drawers; reviewed pricing |
| Scheduling | `/admin/commerce?tab=schedule` | Compact month view, month navigation and Add Performances action; recurrence/batch editor in Advanced scheduling |
| Discounts & promo codes | `/admin/commerce?tab=promotions` | **Box Office → Discounts & promo codes**; create, review, disable promotions with optional Advanced restrictions |
| Orders & refunds | `/admin/commerce?tab=orders` | Combined purchase-linked performance orders; zero-dollar promo orders identified as **Promo / no payment** |
| Customer storefront | `/tickets` | Test-gated performance selection and seat/admission inventory |
| Combined cart | `/tickets/cart` | Multi-performance ticket totals, scoped promo entry and discount display |
| Checkout and confirmation | `/tickets/checkout`, `/tickets/confirmation` | One purchaser/contact form; Stripe test Checkout for payable orders, provider-free completion for valid $0 promo orders |
| Public productions | `/productions`, `/productions/view?id=...` | Independent **View tickets** links where the published production has an upcoming scheduled performance with sales open |

Commerce uses a public-home-inspired storefront header with organization branding, Home, Productions, Tickets, My account and a visible cart indicator. Member and admin navigation permissions remain unchanged.

## Production/schedule interactions

- Closing a Commerce production drawer after a successful POST refreshes the underlying workspace so status changes such as **On sale** are visible; unsuccessful edits do not silently update the page.
- **Add Performance** accepts venue, layout, times, prices, initial **Sales closed / On sale**, fees, and taxes in one reviewed creation flow. These are persisted transactionally for each showtime. The old per-performance fixed-order discount remains under **Advanced**; it is not a promo code.
- Scheduling shows a compact calendar with month controls; the more complex recurrence, exceptions and bulk showtime editor is collapsed by default. Selecting a calendar date opens the advanced editor without creating inventory until review/apply.
- Public production links follow publication, lifecycle, showtime, and sales-readiness gates. Their existence does **not** grant anonymous access to restricted customer checkout.
- Immutable order-price snapshots and historically recorded showtimes remain preserved. Existing venue-local DST gap/overlap controls remain in effect.

## Promo-code rules

Implementation: `src/CommercePromoService.php`, `src/CommerceExperience.php`, `src/CommerceShoppingCheckoutService.php`; schema migrations **121** and **122**.

- One code per cart; uppercase normalization; 3–48 letters/numbers/hyphens/underscores.
- Scope can be **all productions** or a selected production. For multi-show carts, only eligible performances receive the reduction. Percentage and fixed-amount reductions are computed on **eligible ticket subtotal before tax**, excluding fees; allocation is deterministic across performances.
- Options: fixed-dollar or percentage off, expiry date, maximum total redemptions (**1 = single use**, **0 = unlimited**), optional minimum eligible ticket subtotal, optional per-signed-in-account limit (**0 = unlimited**), and optional percentage discount cap (**0 = none**). The discount cap applies only to percentage codes.
- Code entry and total are visible on the cart; creation and deactivation require Commerce `configure` permission. Previously used claims are not deleted by disabling a code.
- Capacity and per-account checks include **reserved** and **redeemed** claims. A claim is created inside the locked checkout transaction, redeemed on successful fulfillment and released only on an authoritative unpaid cancellation. An unresolved payment may therefore temporarily consume a use; do not manually clear claims without reconciled evidence.
- **No stacking with the legacy performance-order discount** on an eligible performance. That older field was moved behind Advanced in the production editor and should ordinarily remain zero.
- Current implementation has no anonymous promo redemption, no coupon stacking, no gift certificates/store credit, and no general live-provider activation. Account limits are based on account ID rather than a guarantee that the same person cannot register a second account.

## $0 promo-funded checkout

If the server recomputes an exactly zero payable total **including fees and taxes** and a valid promo is selected:

1. The customer enters purchaser name and an approved test email in the same checkout screen.
2. `shopping_free_checkout` calls `CommerceShoppingCheckoutService::startFree`; no Stripe session, PaymentIntent or charge is created.
3. Existing cart, performance, seat and eligibility locks are checked. The completed purchase uses the normal purchase/order/ticket/snapshot/QR and consolidated delivery ledger, with a zero total and zero captured amount.
4. The promo claim is marked redeemed when ticket issuance succeeds. Normal scanner/check-in semantics apply; a complimentary price does not free up the seat.
5. Repeating the same purchase is idempotent. An interrupted free fulfillment can be resumed by the owner from the original confirmation using `shopping_free_recover`, not by creating a second cart.
6. The confirmation says **Free order confirmed** and Box Office identifies **Promo / no payment**, not Stripe or captured payment.

A 100%-off code does **not** automatically make every cart free: the promo applies to ticket prices; remaining fees/taxes or noneligible performances still require a payable checkout. Stripe test Checkout remains the path for positive payable totals. Free checkout is still constrained by the existing restricted pilot policy, so it is not a general public issuance route.

## Stripe test Checkout and protected recovery

- The combined purchase snapshots current cart prices, tax, fees, discounts and purchaser identity before redirecting to Stripe. Checkout uses **test mode** only; results and fulfillment depend on authoritative provider response and signed webhooks, not a browser redirect.
- The Checkout session expiration is given a longer creation margin. A specifically classified, previously rejected Stripe request can use a **deterministic recovery idempotency key**; do not rotate keys freely after uncertain network responses. Reuse the existing purchase and reconcile with Stripe records.
- Paid tickets remain held through unresolved payment states. Refunds, cancellations, delivery and scanning maintain existing permission and financial audit protections.
- Promo details and savings appear in the confirmation and consolidated receipt, where applicable. Queueing email is not evidence that the message reached an inbox.

## Business timezone contract

**Single CTSMD operating zone: `APP_TIMEZONE=America/New_York`**, displayed as **Eastern Time** with correct **EST/EDT** abbreviation for a specific date. The central `src/AppClock.php` owns business-local date/time conversion and DST-aware end-of-day calculations. `src/Database.php` resolves the default PHP/business clock through it.

- Business calendar entry and new promotion expiration dates are **Eastern local**, inclusive through 11:59:59 PM on the selected date; their exact instant is stored as UTC.
- Venue-specific IANA timezones take precedence for a performance; `COMMERCE_VENUE_TIMEZONE` is the legacy default/fallback and should remain aligned with `APP_TIMEZONE` unless there is a documented reason otherwise.
- The server OS and persistence/integration clocks **may stay UTC**. Database/integration `*_utc` fields remain UTC. Do not globally reinterpret existing stored timestamps or rewrite historical showtime snapshots.
- Avoid hard-coded `EST` for summertime and avoid showing raw UTC on staff/customer forms. Distinguish local wall dates from UTC instants in APIs, reports and queries.
- This establishes a shared helper and corrects Commerce promo expiration. It is **not yet a complete audit of older timestamp displays across CTSMD**; that remaining audit is tracked in [TODO.md](TODO.md).

See [scheduling and DST details](CONNECT_COMMERCE_SCHEDULING.md).

## Required additive migrations and hosted release boundary

In addition to previously reviewed Commerce prerequisites **101–120**, these two new migrations are required **in order** for promotions:

1. `database/migrations/121_commerce_promo_codes.sql`: codes, cart selection, reservation/redemption claims.
2. `database/migrations/122_commerce_promo_restrictions.sql`: minimum eligible spend, per-account redemption limits, percentage cap and claim lookup index.

Migrations 121 and 122 are **not optional for the current promo manager**, even though the UI gracefully reports that tables are missing. Do not assume merely uploading SQL over FTP runs it. Migration 121 references existing shopping purchase/cart tables from 114–119.

**Before a hosted import:** confirm installed definitions and unapplied statements, inspect 120/other Stripe compatibility dependencies, take and restore-test a protected backup, review FK/index/trigger prerequisites and MySQL DDL auto-commit behavior, then apply only approved missing statements in numeric order. Never rerun a partially imported non-idempotent script wholesale. Upload a coherent matched revision rather than mixing PHP/JS with old database code. Keep normal production/public payment activation off. No credentials or private purchaser data belong in a runbook, issue, commit or test fixture.

See [Commerce pilot runbook](CONNECT_COMMERCE_PHASE3_PILOT.md) and [RC1 release/recovery procedure](RC1_RELEASE_RUNBOOK.md) for release controls.

## Verification record and remaining acceptance

**Source evidence verified as of `2f1dfb2`:**

- [RC1 isolated acceptance](https://github.com/arwentz01/CTSMD/actions/runs/41576124381): **success**. Includes isolated schema bootstrapping, Commerce PHP/MySQL/HTTP/concurrency regression and browser acceptance.
- [PHP safety checks](https://github.com/arwentz01/CTSMD/actions/runs/41576124361): **success**, including `tests/app_clock.php` timezone/DST checks.
- `tests/commerce_promos.php` verifies production-scoped and all-show codes, limits, claim state, single-use concurrency, frozen payment totals, $0 ticket issue and replay, delivery, disabled-code enforcement and account permissions.
- `tests/commerce_productions_browser.mjs` checks admin creation/disable and responsive screens. `tests/commerce_shopping_checkout.php` and `tests/commerce_shopping_cart_http.php` preserve existing combined-cart and provider behavior.

**Not established:** that the **hosted** database has migrations 121/122 or earlier shopping schema; complete live-host Stripe test purchase and return; recipient inbox delivery; genuine user/device/accessibility acceptance; real payment, public checkout, merchant approval, or production-release authorization. Preserve the release gates and evidence requirements in [TODO.md](TODO.md). Do not interpret a green CI check as permission to deploy.

**Implementation milestones:** combined-cart Stripe/session recovery `68ceaa3`; storefront header `80155a5`; drawer/scheduling revisions through November 2; public production ticket links `c004bcf`; promo/claim foundations `4cbb1fe` and `26cef3c`; zero-dollar work `1ba4070` and follow-ups; central Eastern clock `7b8f1aa`, `f86676c`. Exact source state is defined by the branch/commit, not these historical milestones alone.
