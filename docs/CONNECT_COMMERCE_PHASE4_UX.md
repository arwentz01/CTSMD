# Commerce Phase 4 implementation

> **Current-source update, November 2, 2026:** This page records the original Phase 4 UX milestone and its historical test evidence. Later changes include the home-inspired customer ticket header, show-specific ticket links on public production pages, automatic workspace refresh after saved drawers close, a compact monthly scheduling page, sales/fee/tax configuration at Add Performance, and a dedicated **Discounts & promo codes** destination. Multi-performance cart, Stripe **test** checkout, promotion restrictions, $0 provider-free completion and central Eastern Time are documented in [Current Commerce implementation](CONNECT_COMMERCE_CURRENT.md). This historical report does not establish a hosted rollout.

Implemented on `audit/board-pilot-readiness`. No branch creation, deployment, schema migration, service changes, live payment activation or public-sales activation. Shared CTSMD application navigation is retained.

## Before and after

| Before | Implemented experience |
| --- | --- |
| Ten peer navigation destinations mixed setup and daily operations | Four local workspaces: Overview, Productions & Performances, Box Office, Settings. Only the current workspace exposes contextual tools. Mobile tools use a disclosure. |
| Overview was a private test-purchase catalogue | Operational view: paid totals per currency, orders awaiting reconciliation, upcoming showtimes, sold/available counts, readiness context and permission-aware sell/manage/check-in actions. |
| Layouts, timezone forms and creation controls competed for attention | Layout versions are shown first, grouped by venue and space. Creation uses a native drawer; timezone edits use compact disclosures. Published layouts retain their immutable/clone workflow. |
| Scheduling began with a blank production choice and potentially unrelated calendar month | Schedule opens the next upcoming production and month. Exact showtimes remain selectable; recurring templates, exclusions and DST controls are grouped in a disclosure. Every creation still requires reviewed dates. |
| Price edits required JSON for ordinary changes | Familiar decimal money fields update the existing integer-cents payload. Advanced JSON remains available for zones/categories. Sales, check-in, fees and availability remain performance-specific. |
| Seat blocking was a checkbox list | Shared keyboard-operable visual map and synchronized list. Scope selection exposes relevant controls for selected shows, date range or full production. Original all-or-nothing preview/apply validation remains authoritative. The map is a labelled reference layout, not a claim that every show has identical availability. |
| Counter sale was one long vertical form with all map controls exposed | Desktop map and purchase panes; compact seat list, ticket quantities, totals and customer details. Tablet/mobile stack the panes. Pan controls are disclosed; cash and external-terminal fields follow the selected tender. |
| Orders displayed references and required exact reference lookup | Search by purchaser, email or partial reference; status filter; purchaser, performance, status, amount and payment-method columns. Management opens in a native detail drawer, with ordinary direct URLs as fallback. |
| Check-in feedback competed with explanatory copy | Performance-first scanner interface, automatic input focus, larger admission/rejection feedback, retained manual lookup and camera fallback. Valid, previously used and invalid codes retain distinct server responses. |
| Configuration had little provider context | Settings groups venues/spaces/timezones and configuration/access. Provider availability and test-checkout status are visible without displaying secrets or introducing activation controls. |
| Storefront lacked purchase-stage orientation and shared compact working patterns | Production/showtime discovery, purchase progress, compact typography, map/purchase panes, accessible seat list, ticket-type quantities, exact fee/tax/discount preview, authoritative checkout totals, expiry warnings and ticket delivery state. Paid checkout advances to the tickets stage. |

## Validation

All tests used synthetic fixtures in the existing disposable loopback `ctsmd_rc1_commerce3_ci_20261031` schema, with real login and CSRF. No hosted environment was changed.

- **31 Commerce PHP/MySQL/HTTP/concurrency suites passed.** Includes hosted access, permission boundaries, inventory, complimentary issuance, payments, Stripe test adapters, delivery, order/ticket changes, admission, scheduling and bulk allocation. [Results](evidence/commerce-phase4/regression-results.json).
- **Final HTTP rerun:** 42 Commerce/security, 18 planning, 34 Phase 2 workflow and 22 hosted permission checks passed after the final UI changes. [Results](evidence/commerce-phase4/final-http-results.json).
- **59 staff browser assertions passed.** Twelve staff screens at 1440, 768, 390 and 320px have no document overflow, missing form labels or application alerts. Includes keyboard map/list synchronization, scope controls, decimal-to-cents editing, venue drawer dismissal, real synthetic counter settlement, order drawer, valid admission, duplicate rejection and invalid-code rejection. No JavaScript exceptions. [Results](evidence/commerce-phase4/admin-results.json).
- **34 storefront browser assertions passed.** Reserved seating and general admission at four widths, keyboard seats, mixed categories, exact totals, failed-hold field/idempotency recovery, simulation confirmation, one QR per ticket and queued-not-sent delivery. [Results](evidence/commerce-phase4/storefront-results.json).
- Changed PHP lint, JavaScript syntax, existing geometry checks, 12 Apache camera-policy cases and `git diff --check` passed.

Browser test entry points are `tests/commerce_phase4_browser.mjs` and `tests/commerce_phase3_browser.mjs`. Use the existing guarded `tests/fixtures/commerce-browser.php` helper and loopback router. Both browser suites accept `CTSMD_PLAYWRIGHT_MODULE`, `CTSMD_CHROMIUM_EXECUTABLE`, `CTSMD_COMMERCE_FIXTURE`, `CTSMD_COMMERCE_URL` and `CTSMD_COMMERCE_OUTPUT`. Use a fresh fixture for each storefront purchase run; seats intentionally remain sold after a successful purchase.

Screenshots are native Chromium captures of synthetic data, not mockups. Reviewed desktop, tablet and mobile captures during implementation; representative files are committed below. Full-page captures may show the existing fixed shared mobile navigation midway through the image.

## Screenshots

- [Overview desktop](evidence/commerce-phase4/overview-1440.png), [mobile](evidence/commerce-phase4/overview-390.png)
- [Venues](evidence/commerce-phase4/venues-1440.png), [calendar](evidence/commerce-phase4/schedule-1440.png)
- [Seat blocking desktop](evidence/commerce-phase4/blocks-1440.png), [mobile](evidence/commerce-phase4/blocks-390.png)
- [Box Office](evidence/commerce-phase4/sales-1440.png), [orders](evidence/commerce-phase4/orders-1440.png), [check-in](evidence/commerce-phase4/checkin-1440.png), [settings](evidence/commerce-phase4/configure-1440.png)
- [Storefront](evidence/commerce-phase4/storefront-discovery-1440.png), [mobile reserved seats](evidence/commerce-phase4/storefront-reserved-390.png), [mobile general admission](evidence/commerce-phase4/storefront-admission-390.png)

## Remaining limitations

- Adding or renaming pricing zones/categories still uses advanced JSON; ordinary existing prices use money inputs.
- Order results show the latest 100 matches; narrow the search rather than paging through an unbounded financial history. Wide financial tables scroll internally on small screens.
- Multi-show allocation uses one reference map and the union seat list. Different layout versions can produce conflicts; server review explains them and prevents partial application.
- Drawer submissions follow normal server redirects. JavaScript-disabled operation retains direct order pages, venue disclosures and seat lists.
- Camera availability is browser-dependent; manual/keyboard scanning remains supported. Admission is online-only.
- Browser emulation verifies responsive layouts, not physical tablet ergonomics or assistive-technology certification. No human theatre-staff usability study was performed.
- Public sales and live payments remain unavailable. Provider credentials remain server-managed; delivery is not claimed successful merely because it was queued.

## Milestones

- `6cee628`: workspace navigation, operational overview, order discovery and shared visual foundation.
- `3eeae6b`: visual allocation, price editing, counter-sale panes, purchase progress and browser regression coverage.
- Final verification milestone: responsive refinements, calendar defaults, evidence and this report (see branch commit history).
