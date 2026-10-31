# Connect Commerce Phase 2 — Box Office and storefront preview

Implementation on `audit/board-pilot-readiness`, October 31, 2026. Application milestones: `10d0b10` (staff tender), `5e41364` (general admission), `98035b2` (integrated staff/storefront, ticket changes and verification). No deployment, production migration, real payment, audience email or production-account test was performed.

## Entry points

- Staff: Operations → Commerce → **Box Office sales**, or `/admin/commerce?tab=sales`.
- Customer layout: `/tickets`; `/tickets/show?production=ID`; `/tickets/select?performance=ID`; `/tickets/checkout?id=ID`; `/tickets/confirmation?id=ID`; `/tickets/order?token=CAPABILITY`.
- `/tickets` is a restricted preview, not an activated public ticket shop. Purchasers need no new Connect identity; a signed-in authorized preview operator supplies synthetic guest details. Ordinary visitors cannot perform simulated purchases.
- Existing public site, external ticket links and Playbill links are preserved. The preview does not insert ticket CTAs into the public website.

## Box Office workflow

Select a performance, choose seats or admission quantities, specify ticket-type quantities, and hold the complete selection. Optional identity defaults to `Walk-in guest` and a synthetic `.invalid` capture address. Only synthetic recipients are accepted in this build.

The order review shows the original price snapshot, discount, tax, fee, total and server hold deadline. Authorized managers can apply a reasoned discount before settlement; sellers cannot override prices. The tax rate is captured when the hold is created. Historical orders lacking that snapshot must be voided and recreated to change discounts; no tax rate is guessed.

Cash received is entered in normal currency units. The page previews change; the server calculates change independently and requires sufficient tender plus explicit receipt confirmation. External-terminal recording requires provider/reference and confirmation, never card details. Both settle the existing `held → paid` order through the same performance lock and ticket fulfillment as the simulator. `commerce_box_office_sales` stores staff evidence separately: **not API verified**. No provider attempt is fabricated. Simulated payment remains available for testing.

Completed tickets can be printed or saved through the browser's Print / Save as PDF control. Order search supports customer name/email, full reference, internal order number, seat description and performance filter in the search service. The staff sales screen filters search to the selected performance, or searches all when none is selected. Unpaid reservations can be voided; uncertain provider payments must be reconciled. House holds/releases use the existing performance inventory controls.

Full refunds require the refund capability. For staff tender, a separate confirmation records that cash was returned or a terminal refund was completed outside Connect; Connect does not transfer or verify money. Admitted tickets must first have admissions reversed. Financial records, original tender and audit history remain.

Reissue rotates the unpredictable QR token; a stale form cannot rotate it repeatedly. The old token hash is retained in audit history and the old code stops admitting. Relocation is limited to an available seat in the **same performance and pricing zone**, on a valid, unadmitted paid ticket. It revokes the original ticket, appends a zero-price replacement line and ticket, releases the old seat, and preserves the original financial snapshot. It does not support price-changing or cross-performance exchanges. Return-to-previous-seat history collisions are rejected rather than overwriting lines.

Cashier totals group cash/external-terminal orders by cashier, method and currency, retaining original confirmed totals and refunded/retained values. Simulated-provider and complimentary orders are not misrepresented as cashier receipts.

## Storefront publication and authorization

A performance must be explicitly approved under its Commerce performance settings **and** its existing production announcement must be published. Closed sales remain visible as closed; archived/completed productions are excluded from discovery. Both direct selection and the initial payment POST enforce current publication approval. Pending payment reconciliation and paid ticket retrieval remain available independently of later discovery removal.

The customer view retains circular/angled seats, stage and interpreter landmarks, accessibility descriptions, seat-list alternative, consolidated quantities, pan/zoom and atomic holds. Discovery shows artwork when available, title, date, venue, price range and current available capacity. No private production/family data is included.

General admission is selected under a performance's seating-mode settings before any order history exists. Existing layout inventory positions define the capacity by price zone; they act as admission entitlements, not assigned seats. The customer sees quantities and general-admission descriptions. Under the existing performance lock, the server selects eligible inventory and reserves the whole quantity atomically. House/blocked/held/sold positions reduce capacity. There is no second capacity ledger. Capacity/layout and mode cannot be changed by this UI after an order exists; create a new performance for a different configuration.

Active adults with explicit Commerce grants can use hosted staff tools according to their capabilities. Application administrators retain setup access. `seller` can sell/search/print but cannot configure prices, discount, refund or reverse admissions. `door` can verify admission only. Ordinary production volunteers receive nothing automatically. `storefront_tester` explicitly enables customer preview without staff operations; the older local `tester` grant remains insufficient on hosted sites. Grant changes and account deactivation are enforced against existing sessions.

## Delivery boundary

Confirmation provides printable tickets and can prepare a secure 24-hour retrieval link. Only a SHA-256 token hash is stored; the raw random capability contains no identity or sequential order ID. Retrieval requires the capability and an authorized preview account while public sales are disabled. Expired/invalid links fail without purchaser details. Current ticket state is read on retrieval; refunded/revoked tickets show no valid QR.

`CommerceDeliveryService` is a preparation boundary returning a structured capture payload (escaped when rendered) and retrieval path. It has no queue/send operation and cannot send to real recipients. Repeating preparation makes a new capture/link, not a real email resend. Production email transport, signed public origin, deliverability and resend limits require separate approval and integration before real audience delivery.

## Migration and FTP preflight

For an installation already using Phase 1 migrations 101/102:

1. Back up the hosted database and record the currently deployed Git revision.
2. Review and apply **103, 104, 105, 106**, in numeric order, through the hosting database tool during a reviewed maintenance window. No runtime migration runner is introduced.
3. Upload the matching repository files, including new `src/Commerce*Service.php`, `front.php`, updated CSS/JS and the updated access/UI services. FTP alone does not install schema.
4. Schema readiness checks fail to setup instructions if the new tables or mode/tax snapshot columns are absent. `CREATE TABLE IF NOT EXISTS` does not validate a partially incompatible existing table; review partial-import metadata before retrying. Column ALTERs must not be rerun blindly.
5. Preserve `COMMERCE_PUBLIC_SALES_ENABLED=0`. `COMMERCE_STOREFRONT_PREVIEW_ENABLED=0` pauses customer preview independently. Existing Commerce sandbox gates still apply.
6. Initialize/configure only the intended installation owner, use synthetic data/accounts, approve only intended published preview productions and verify ordinary-visitor denial.

Migration 104 extends the Commerce grant enum and adds default-unapproved publication records and hashed order links. Migration 105 adds default-reserved seating mode. Migration 106 adds a nullable tax-rate snapshot without backfilling historical financial data. No Connect family, casting or production schema is changed.

Rollback: stop preview/Commerce actions using their existing flags; keep additive tables and history; restore the prior application release only after evaluating its ability to understand newly settled staff orders. Do not delete tender records, ticket history or downgrade the enum while grants use it. A database restore requires the approved backup/restore runbook and reconciliation of transactions after the backup. No rollback or restore was executed here.

## Verification and acceptance boundaries

Automated PHP/MySQL/HTTP results and rendered evidence are recorded alongside this document. Tests use only the disposable loopback schema `ctsmd_rc1_commerce_20261030`, real authentication/CSRF and synthetic fixtures. Separate PHP processes compete for the same reserved seat and final general-admission entitlement: one completed sale, one rejection, one ticket. Existing payment failures, hold expiry, cancellation, refund, duplicate admission and safeguarding boundaries remain covered by the regression suites.

Rendered local checks cover desktop discovery, mobile discovery/checkout, tablet staff selection and a completed cash sale. Mobile 390px and tablet 1024px checks found no document-wide horizontal overflow on the checked screens. These are browser viewport checks, not actual-device or full assistive-technology certification. See `evidence/connect-commerce-phase2/`.

### Remaining hosted pilot blockers and required evidence

- **Hosted release not deployed or verified.** Needs matching files/schema preflight, synthetic staff and tester journeys on ctsmd.me, ordinary visitor denial, and host-specific security/cache/rewrite verification. The instruction not to deploy prevents completing this acceptance milestone in this run.
- **Physical device/door hardware unverified.** Needs actual iOS/Android camera permission/denial scans, keyboard-wedge hardware, physical ticket/QR print readability, and screen-reader checks. Browser Print / Save as PDF controls exist; physical printer and OS PDF dialogs were not tested.
- **No real provider or financial reconciliation.** `COMMERCE_PUBLIC_SALES_ENABLED` is an independent activation request; even `1` cannot open public sales while the only implemented provider is simulated. Needs real provider adapter, authenticated webhooks, card-present integration if desired, refund reconciliation, credentials, timeout recovery and operational signoff.
- **No live email delivery or anonymous paid-order recovery.** Needs approved infrastructure integration, public origin, retention/privacy, resend rate limits, secure support recovery and end-to-end delivery acceptance. Preview retrieval links require an authorized account intentionally.
- **Public website activation integration remains gated.** Before enabling real sales, add the approved internal-versus-external ticketing choice to eligible production cards/details while retaining external ticketing and Playbill links. No automatic replacement is implemented or enabled by this preview.
- **Exchange limits.** Same-performance, same-zone no-cost relocation is implemented; cross-performance or price-changing exchanges and partial refunds need a separately reviewed financial workflow.

Milestones 1–4 have functional local implementations, with the specific limits above. Milestone 5 (hosted pilot acceptance) remains open. Do not equate a local screenshot, passing bundle, staff attestation or simulator event with live money, email, hardware or hosted readiness.

## Recorded local test results

[Machine-readable evidence](evidence/connect-commerce-phase2/local-verification.json) records all 18 Commerce suites passing, plus all 18 PHP safety suites, syntax/geometry checks, 12 real Apache camera-policy cases and 30 RC1 tooling contracts. Hosted HTTP behavior passed 22 checks in both production and copied-local configuration modes against disposable loopback data. This is local execution of CI-covered checks, not a claim that GitHub Actions completed.

| New or expanded suite | Result |
| --- | --- |
| Box Office tender, discounts and authorization | 27 checks |
| General admission | 11 checks |
| Ticket reissue/relocation/refund/void | 15 checks |
| Storefront and staff real HTTP | 28 checks |
| Hosted real HTTP | 22 checks in each environment mode |
| Cross-channel concurrency | Reserved seat and final GA entitlement: one sale/one rejection; valid admission, duplicate rejection, reversal, refund and release |

Rendered evidence: [desktop storefront](evidence/connect-commerce-phase2/storefront-desktop.png), [mobile storefront](evidence/connect-commerce-phase2/storefront-mobile.png), [desktop selector](evidence/connect-commerce-phase2/selector-desktop.png), [mobile selector](evidence/connect-commerce-phase2/selector-mobile.png), [mobile checkout](evidence/connect-commerce-phase2/checkout-mobile.png), [tablet Box Office](evidence/connect-commerce-phase2/box-office-tablet.png), [tablet cash confirmation](evidence/connect-commerce-phase2/cash-confirmation-tablet.png). All pictured accounts and content are synthetic.
