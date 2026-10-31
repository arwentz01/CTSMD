# Connect Commerce Phase 1

Optional, simulated-only Commerce is owned by `commerce_organizations`. Its key identifies the installation's configured Commerce owner; it is not a claim that Connect's identity, production, family, storage or communication systems are tenant-isolated.

## Activation and authorization

Hosted deployments now expose a simulated Commerce preview to active application Administrators by default, following the request to work on ctsmd.me. Non-administrators cannot enter the hosted preview, regardless of local Commerce grants. Set `COMMERCE_HOSTED_SANDBOX_ENABLED=0` to pause hosted actions; the administrator entry remains visible with a paused notice. Local/rc1-test environments still require `COMMERCE_SANDBOX_ENABLED=1`. Database capabilities default off independently: ticketing, merchandise, education. Merchandise and education are future boundaries, not implemented sales flows. No public Get Tickets link, provider secret, credit-card field or notification worker is introduced.

The existing application Administrator can bootstrap/manage only `COMMERCE_ORGANIZATION_KEY` (default primary). Additional Commerce ownership is explicit. Organization-scoped grants define administrator, box_office, seller, door, reporter, tester. Production membership, volunteer assignment and creative credits grant no Commerce permission. Every service operation must check the actual organization and capability; opaque IDs never substitute for authorization. `tester` is only permission to use the gated sandbox as a simulated guest purchaser; the purchaser does not need a Connect account, but the sandbox operator does.

## Data model and boundaries

Migration 101 is additive. Venues have multiple spaces; spaces have versioned JSON layout documents. Layout documents contain ordered sections/rows and arbitrary-position items (seats, stage, aisles, spaces); wheelchair/companion/restriction/zone attributes belong to seats. Published layouts are immutable at the database boundary; new drafts clone versions. Performances reference published versions and existing production IDs; inventory snapshots isolate every performance. Existing production lifecycle remains owned by Connect.

Orders and lines retain integer minor-unit pricing, explicit currency, ticket category, seat identity and labels. Price configuration changes affect new orders only. Purchaser information stays in scoped Commerce orders, never creates an auth user or family identity. Payment attempts/events, tickets and audit rows retain the transaction history. Refunds revoke tickets and explicitly release saleable inventory; paid orders/lines are retained. No card data is accepted or stored.

Catalog boundary: ticket lines are implemented; merchandise and paid programs will need their own catalog/stock/enrollment adapters. Tax/fees/discounts are explicit calculations, not a legal tax determination. Phase 1 is restricted to synthetic `.invalid` purchaser email addresses. Receipt fulfillment is a gated HTML preview, not queued outbound mail; future email delivery must use existing Connect infrastructure with explicit provider/delivery configuration.

## State machines

Order: draft → held → payment_pending → paid; held → canceled on expiration/cancellation; payment_pending → failed/canceled only on terminal provider evidence; payment_pending → exception on unresolved timeout; exception → paid/failed/canceled only on reconciliation. paid → refunded through authorized audited full refund. No browser GET/redirect changes payment state. Paid fulfillment is idempotent.

Inventory: available/released → held → sold. Administrative blocked/house/complimentary allocation cannot be acquired by checkout. A held seat may return to available only after safe hold expiration or a terminal failed/canceled provider result; pending/unknown external payment protects it even after checkout's original deadline. Accessible characteristics never change inventory state.

Hold creation locks one performance before reading/releasing/allocating inventory, giving all-or-nothing selection and a stable lock order. Unique performance/seat keys, order/seat tickets and payment-event keys backstop service idempotency. A replayed hold idempotency key must have the same actor, ownership token and normalized request; refreshing never extends its expiration. Browser abandonment alone cannot extend a hold. Expiration is enforced by server operations, not a browser timer or background worker.

Provider contract: `CommercePaymentProvider::inspect` returns pending, succeeded, failed, canceled or unknown. Only the simulated adapter is configured. Delayed success remains pending until the server's ready time; timeout requires reconciliation and never automatically releases seats. Real providers will additionally need authenticated webhook ingestion, provider request idempotency, independent reconciliation and recovery from transport ambiguity before live payments are considered.

## Future unrelated-organization hosting

Commerce-owned foreign keys and queries are organization-scoped. The existing `productions` and `users` are not globally tenant-scoped: unrelated theatres must not share this installation yet. Production ownership, identity isolation, merchant secrets, legal/tax configuration, storage, mail, reporting, support access and cross-system audit isolation require a separate reviewed multi-organization project. No branding option confers tenant security.

## Migration / rollback

The original Phase 1 work did not run hosted migrations. Hosted administrator-preview activation is now requested; migration 101 and 102 still require a backup and reviewed import through the hosting database tool. FTP uploads do not execute SQL. No production migration has been executed by this coding session. Missing Commerce tables or immutable-layout triggers show an administrator setup page; there is no automatic runtime schema installation. Rollback means disable the sandbox flag and retain Commerce records; do not drop transactional history as a rollback shortcut. Back up and review the migration under the existing release process before any future deployment.

## Milestone 2 — venue designer

`/admin/commerce?tab=venues` and `tab=layout&id=…` are authenticated, local-only routes, with the same Commerce service authorization on every POST and a separate CSRF token. Configure the sandbox flag and owner key only on an isolated synthetic database. Neither routing nor migration enables the module.

The editor supports arbitrary coordinates, drag placement, keyboard seat selection/arrow movement, numeric position fields, sections/rows with editable names and order, bulk seat generation, stage/aisle/non-seat items, price zones, wheelchair positions, companion relationships and view/access descriptions. Save drafts before publishing. Published versions cannot be updated or deleted, including direct database writes; clone to edit the next version. Draft saves reject stale content hashes. No existing production workflow changes.

Verification: PHP syntax, JS syntax, 12 MySQL venue checks (including immutable published versions, stale save, malformed coordinates, companion integrity, duplicate row seat labels, permissions and owner scoping). Real local browser: synthetic administrator login, generated eight seats, saved/reloaded and used keyboard selection/position editing. Further responsive and end-to-end verification follows with checkout.

## Milestone 3 — inventory and holds

Each performance links the existing production and an immutable published layout. Inventory stores section/row/seat descriptions as snapshots. Creating or configuring performances, allocating seats and accepting new holds respects the production lifecycle; ticket history never changes the production. Because the existing production model has no tenant ownership, only the configured installation owner may link Connect productions. Unrelated organizations are explicitly rejected instead of inferring ownership.

Prices are zone/category maps in integer minor units. Fees are per order, discounts capped at subtotal, and tax rounded half-up on discounted ticket subtotal (fees excluded). This sandbox policy is explicit, not a claim of jurisdictional tax compliance. Prices and totals are frozen at hold creation. Performance configuration affects future orders only.

Every inventory mutation locks its performance row first, then relevant orders. All-or-nothing holds serialize across separate connections, use unguessable ownership tokens stored as hashes, and have actor-scoped idempotency keys with request hashes. Retries do not extend expiry. Abandoned held orders expire lazily on availability/hold/allocation operations; payment-pending and exception orders are excluded. No worker is activated. House, blocked and complimentary allocations are unavailable online. Complimentary allocation reserves inventory; admission issuance comes only through the order/ticket lifecycle.

Verification: 18 MySQL inventory checks plus a two-process/two-connection race with exactly one winner and one rejection. Covered atomic partial failure, blocked/house seats, expiry/reacquisition, ownership, price tampering, performance isolation, historical prices/layouts and archived-production creation denial. PHP syntax passes.

## Milestone 4 — restricted customer selection and review

The Overview launches authorized previews; production title, configured performance time, venue, layout orientation, pricing zones, inventory labels, non-color state symbols, pan/zoom, map keyboard toggles and a separate native-checkbox seat list are available. Accessible filtering includes wheelchair and companion seats without preventing purchase. Existing public poster delivery is reused only when the production already has published artwork; unpublished assets are not exposed. Empty-artwork performances use their actual title and layout without invented imagery.

Guest purchaser data belongs to the order, not a new Connect identity. Sandbox access still requires an active authorized testing account. The server accepts only synthetic `.invalid` purchaser addresses and never queues mail. Local selection is submitted atomically; full itemized review uses immutable server-calculated values. Checkout ownership secrets stay in the authenticated session. Browser-provided categories select existing server prices; no browser-supplied amount is trusted.

Browser verification: created/published eight-seat layout, created performance, opened sales, selected a Student category, submitted synthetic guest details and verified USD 12.00 server review. Keyboard Enter toggled a map seat and updated the list/summary. Desktop 1366, tablet 820 and mobile 390 CSS-pixel checks found no document-level horizontal overflow in selection/review. Automated backend tests from milestone 3 remain the inventory authority; these browser checks do not establish full screen-reader or physical-touch conformance.

## Milestone 5 — payment simulation and fulfillment

The provider interface accepts server-stored attempts and returns business outcomes. The only adapter is simulated. Its attempt is immutable through customer retries; no browser return URL or GET parameter can settle payment. A POST begins the stored scenario, and an authorized POST refresh obtains the adapter's authoritative result. Delayed scenarios remain pending for 15 seconds. Replaying a completed outcome does not create more tickets/events. The explicit expired-checkout control shortens a still-held sandbox deadline and invokes the same server expiration path; it never releases payment-pending inventory.

Timeouts become `exception`, retaining seats indefinitely until reviewed. Migration 102 adds an owner-scoped, one-result-per-attempt simulator-evidence ledger. Only the Commerce administrator can record definitive synthetic timeout evidence with a reason; conflicting evidence is rejected. The adapter reads that stored evidence during reconciliation. This is local test tooling, not a webhook, real-provider override or authorization to settle an uncertain real charge.

State transitions: `held → payment_pending → paid | failed | canceled | exception`; `exception → paid | failed | canceled` requires definitive provider evidence. `held → canceled` covers expiration/explicit cancellation. `paid → refunded` is full-order simulated refund only, invalidates tickets, releases the corresponding sold inventory and retains order/payment/audit history. Refunds reject checked-in orders until admissions are reversed. A retry after terminal failure creates a new hold/order; existing payment attempts are not replaced. Draft is reserved for future catalog assembly; current ticket checkout creates the full held order atomically.

Fulfillment issues one 256-bit unpredictable QR token per order seat. QR payload is `CC1:<random token>` with no purchaser identity or sequential ID. Receipt capture is escaped, authenticated and `.invalid`-only; it has no SMTP/queue/send path. Production title/artwork stays linked to the existing production; performance/layout/order line snapshots preserve seat identity.

Verification: 32 MySQL payment/receipt assertions covering all outcomes, late success, browser abandonment, duplicate fulfillment/events, owner checks, protected pending/exception inventory, explicit timeout evidence, expired checkout, refunds, safe receipt capture and QR uniqueness. Real browser: a synthetic guest purchase transitioned from held to paid and rendered one QR ticket plus a clearly unsent receipt preview.

## Milestone 6 — box office and final verification

Box Office selects a performance, verifies `CC1:` QR/manual input, supports keyboard-wedge Enter submission, and shows current valid/admitted totals. Manual full-order-reference lookup returns admission details only; door authority cannot read purchaser contacts or financial reports. Online camera scanning uses native `BarcodeDetector` and requests a camera only after Start QR camera. Unsupported/denied cameras retain manual entry. A captured QR fills the field; a deliberate Verify & admit action requests authoritative server validation.

Check-in locks the performance and ticket and conditionally admits only a valid paid ticket once. Wrong-performance, revoked, refunded, unpaid, closed-check-in and closed-production admission requests are rejected. Reversal needs a distinct permission and reason and retains previous admission/actor in the audit history. Revocation retains sold inventory; a separate full refund releases it safely. No offline state is accepted or synchronized.

Orders provides explicit reporting access, server result inspection, full simulated refunds and ticket revocation. Only the Commerce administrator can resolve uncertain simulator evidence. Existing Operations receives one gated workspace entry. Authorized Commerce users who have no existing Operations access receive one sandbox navigation entry; individual tools remain in compact secondary navigation. Missing optional schema hides navigation safely. Commerce access, including local administrator bootstrap, requires an active adult-by-birthdate non-Student account. Grants can still be revoked after a recipient becomes ineligible. Disabling an account or revoking its grant immediately denies subsequent requests.

### Verification evidence

All runs were local; no production account, schema, payment, deployment or delivery worker was used.

| Check | Result |
| --- | --- |
| Commerce foundation / venue / inventory suites | 10 / 12 / 18 assertions passed |
| Separate-process seat race | Exactly one hold and one rejection |
| Payment / receipt suite | 34 assertions passed |
| Tickets / authorization suite | 20 assertions passed, including simultaneous two-process scans |
| Authenticated Commerce HTTP suite | 23 assertions passed, including CSRF, forged totals, GET-paid forgery, malformed prices, ownership and session grant revocation |
| Fresh schema bootstrap | Entire source schema and migrations, including 101/102, passed on a newly created disposable schema |
| RC1 acceptance | 173 assertions passed, seven personas and two productions, on the fresh schema |
| Existing safeguarding | 36 assertions passed |
| Existing adult / people / My Theatre / Playbill suites | Migration 9; adult MySQL 83; adult concurrency 16; production people 56; My Theatre 30 + HTTP 131; Playbill 20 + HTTP 46 passed |
| Existing PHP safety workflow checks | All locally executed PHP safety tests passed; source/test PHP lint, front.php lint and all 14 browser-JS syntax commands passed |
| Responsive browser checks | Designer, selector, checkout and check-in at 1366×900, 820×1180 and 390×844; no document overflow, unnamed enabled form controls or buttons smaller than 24 CSS pixels in these samples |
| Keyboard interactions | Editor seat selection/position save; map Enter selection; category changes; guest submission; scanner Enter; duplicate rejection; order lookup and reversal verified in a real local browser |
| Browser warnings/errors | None recorded in the sampled Commerce session |

[Machine-readable automated summary](evidence/connect-commerce-phase1/automated.json) · [responsive measurements](evidence/connect-commerce-phase1/responsive.json).

Rendered text/background contrast samples calculated from browser-computed colors: body 13.43:1, serif heading 12.11:1, sandbox banner 7.87:1, button 10.04:1, navigation link 9.39:1. These meet AA text contrast thresholds for these samples. This is not a blanket WCAG certification: full assistive-technology testing, map target exceptions at small scales, real touch/zoom, and all application-shell states still need device/user verification.

Screenshot evidence (all synthetic):

| Screen | Desktop | Tablet | Mobile |
| --- | --- | --- | --- |
| Published designer preview | [Image](evidence/connect-commerce-phase1/designer-desktop.png) | [Image](evidence/connect-commerce-phase1/designer-tablet.png) | [Image](evidence/connect-commerce-phase1/designer-mobile.png) |
| Seat selector | [Image](evidence/connect-commerce-phase1/selector-desktop.png) | [Image](evidence/connect-commerce-phase1/selector-tablet.png) | [Image](evidence/connect-commerce-phase1/selector-mobile.png) |
| Confirmed checkout | [Image](evidence/connect-commerce-phase1/checkout-desktop.png) | [Image](evidence/connect-commerce-phase1/checkout-tablet.png) | [Image](evidence/connect-commerce-phase1/checkout-mobile.png) |
| Box office | [Image](evidence/connect-commerce-phase1/checkin-desktop.png) | [Image](evidence/connect-commerce-phase1/checkin-tablet.png) | [Image](evidence/connect-commerce-phase1/checkin-mobile.png) |

The first broad run encountered two environment issues: the older disposable schema lacked the already-committed external Playbill table, and the forward-migration test could not locate MAMP's MySQL client. Repeating on a freshly bootstrapped schema with `RC1_MYSQL_BIN` explicitly set passed both. No existing populated schema was reset. Two previously stale safety fixtures were updated to reflect reviewed upstream public-poster/external-Playbill changes: production-management contract hashes and complete public-render fixture data/assertions. Those application sources were not modified by Commerce. Upcoming, current and empty public render states remain covered, with publication gates retained.

Remote CI has the seven Commerce commands added to isolated MySQL acceptance; the safety workflow includes Commerce JS/assets. Local results are evidence of execution, not a claim that a particular remote Actions run has completed.

### Run the isolated sandbox

Use a **new, explicitly disposable** `ctsmd_rc1_*` loopback database with the existing RC1 bootstrap recipe. `tests/rc1_bootstrap.php` refuses nonempty databases. Never point it at MAMP's normal application database or production. Configure `DB_USERNAME`, `DB_PASSWORD`, and `RC1_MYSQL_BIN` for your local installation; do not put credentials in Git.

After the isolated schema is ready:

```sh
APP_ENV=rc1-test DB_HOST=127.0.0.1 DB_PORT=8889 DB_DATABASE=ctsmd_rc1_your_sandbox \
  php bin/commerce-sandbox-fixture.php --create-synthetic
```

This CLI-only command creates synthetic personas, a published accessible layout and two open test performances. It prints private synthetic credentials and an organization key; it does not migrate, deploy, send mail or accept payment. Keep its output private. To serve it, pass the same isolated database variables plus the printed key to a loopback-only development server:

```sh
APP_ENV=rc1-test COMMERCE_SANDBOX_ENABLED=1 COMMERCE_ORGANIZATION_KEY=commerce_PRINTED_KEY \
  DB_HOST=127.0.0.1 DB_PORT=8889 DB_DATABASE=ctsmd_rc1_your_sandbox \
  php -S 127.0.0.1:8787 -t . tests/fixtures/commerce-router.php
```

Use your installed PHP/MySQL paths where needed. Sign in with a printed synthetic account, then open `/admin/commerce`. The environment key must match the printed key exactly. The restricted testing account represents sandbox authorization; enter a separate synthetic guest name and `.invalid` email at checkout. The fixture is intentionally optional and isolated; normal migrations and deployment never create it or turn ticketing on.

To start from scratch within that synthetic owner, use Venues & seating → create venue/space → generate/edit seats → save → publish → Performances → select existing synthetic production and layout → configure/open sales → Overview → choose seats → guest review → simulator → Box Office. Test timeout reconciliation through administrator Orders, and use full-order-reference lookup at the door. Stop the local server after testing and retire disposable accounts using the existing RC1 fixture cleanup conventions; do not run delivery workers.

### Remaining work before public or real-payment activation

1. Real provider onboarding and merchant-account ownership, secret management, provider request idempotency, authenticated/replayed webhook handling, independent scheduled reconciliation and transport-failure recovery. No live adapter exists.
2. Operator-reviewed deployment/migration preflight, backup/restore and load/deadlock recovery exercises on staging. Per-performance serialization favors correctness; it has not been load-certified for a large on-sale.
3. Legal/operational tax, fee, refund and accessible-seating policies; jurisdictional calculation adapters, financial reporting/settlement and partial refund/exchange requirements. Current refund simulation is full-order only.
4. Public guest entry, secure receipt recovery/delivery links, abuse/rate limits, real customer privacy/retention policy, and approved email fulfillment through Connect's existing queue. Current guest flow requires an authorized sandbox operator and never sends mail.
5. Actual phone camera QR scans, camera-denied testing on supported devices, physical USB/Bluetooth scanners, VoiceOver/TalkBack and other assistive technology. Manual/wedge-style text input was tested; physical hardware and camera delivery were not.
6. No reliable offline scanning or multi-device offline synchronization. Admission always requires the server.
7. Full unrelated-organization ownership/isolation across identities, production, artwork/storage, messages and administration. Branding and Commerce owner keys do not create a tenant-isolated deployment.
8. Merchandise stock/catalog and education enrollment adapters are future work despite independent configuration flags. Exchanges and bulk guest sales are deferred; complimentary allocation and admission issuance are implemented and audited.

None of these remaining activation requirements is hidden behind a successful compile or simulated payment. Public ticket links and existing external ticketing/Playbill behavior remain unchanged.

## Ticket-type quantity refinement

Seat selection now uses one **Ticket types** block instead of category dropdowns beside every seat. For example, select four seats and enter two Adult and two Student tickets. Category names and prices come from performance configuration; they are not hardcoded to these examples. The block shows assigned tickets versus selected seats, then itemized totals once counts match. Multiple price zones have separate quantity groups inside the same block, so an inexpensive-zone ticket cannot be applied to a premium-zone seat. Unselected groups reset to zero; a zone with only one ticket type follows its selected seat count automatically.

The server independently validates whole-number quantities, existing zones/categories and an exact seat count per zone. It assigns categories to seats in deterministic key order inside the existing performance-locked hold transaction, then prices/holds the complete selection using the existing inventory rules. Retry payload order does not change the assignment or extend the hold; changing quantities with the same idempotency key is rejected. Order review and digital tickets retain per-seat ticket types for admission.

Verification: 16 dedicated quantity assertions cover mixed types, mismatches, malformed/negative/oversized counts, forged categories/zones, price-zone isolation, unavailable-seat atomic failure and retries. The authenticated HTTP suite now has 25 checks and verifies the new form protocol through checkout/payment. Real desktop/mobile browser verification selected four seats, confirmed Continue was disabled with only two ticket types assigned, then entered two Adult plus two Student tickets and completed server order review at USD 68.31 (including configured discount/tax/fee). No per-seat category dropdowns remain. This is a local synthetic test, not deployment.

Latest screenshots: [desktop quantity block](evidence/connect-commerce-phase1/ticket-quantities-desktop.png) · [mobile quantity block](evidence/connect-commerce-phase1/ticket-quantities-mobile.png). Earlier selector screenshots above record the original milestone; these images show the revised purchase flow.

## Angled rows, circular seats and accessibility landmarks

The venue editor now generates rows with a starting X/Y position, seat spacing and an angle from −180° to 180°. Zero is horizontal; positive angles slope down to the right. The same angle field can rotate an existing row about its center. Generation/rotation reject arrangements outside the supported canvas instead of clipping seats or partially moving the row. Coordinates remain arbitrary and may include fractional values; there is no rectangular-grid constraint.

Add **Stage** or **ASL interpreter position** using Item type → Add detail. Select the marker, drag it or edit X/Y, adjust marker width/height and add a description. A stage indicator is required when publishing a new version. ASL markers are optional and should be added only for the relevant interpreted performance layout. Both are non-saleable landmarks, independently positioned from seats. Descriptions also appear as ordinary text beside the customer map. Layout marker position indicates an authored location; it does not guarantee an unobstructed view or confirm interpreter scheduling by itself.

Marker type, size and description live in the existing versioned layout JSON; no schema migration is needed. For a different venue or show arrangement, clone a layout, reposition its stage/interpreter markers, publish the new version and select it for a new performance. Published versions and existing performance seat/ticket references remain immutable. Existing layout documents without dimensions use compatible marker defaults.

Both maps use circular seats. The editor retains visible seat numbers for placement; the customer map reveals section, row, seat number, availability, price zone and accessibility notes on hover or keyboard focus. Focus uses a circular outline, and Escape dismisses the tooltip. Section/row captions remain visible. Availability still has non-color symbols, and the accessible seat list and selected-seat text summary remain available for touch and assistive-technology users. Selecting a circle still feeds the shared ticket-type quantity block.

Verification: Node geometry checks covered positive/negative angles, a 90-degree rigid rotation, unchanged source coordinates, rejected out-of-bounds operations and fitting enlarged landmarks. Eight MySQL landmark checks covered marker save/reload, fractional seat coordinates, malformed dimensions/type, non-saleable markers, required stage indicators and immutable layout history. Real local browser checks saved/reloaded an ASL marker moved from X=300 to X=280 and resized from width 160 to 180; rotated a row; generated/saved four seats at −25°; moved the ASL marker with ArrowRight and with a pointer drag, then confirmed the dragged X=325/Y=179 position after reload; and verified keyboard tooltip, Escape dismissal, circular focus and selection-to-quantity continuation. This also found and fixed an existing inspector issue: text/numeric edits now synchronize on input, without waiting for blur or resetting the active field. A 390-pixel mobile check retained the accessible descriptions and had no document overflow. No browser warnings/errors were recorded in the sampled session; physical touch/assistive-technology testing remains separate.

Latest synthetic previews: [editor with movable landmarks](evidence/connect-commerce-phase1/angled-editor-desktop.png) · [desktop angled seating with seat details](evidence/connect-commerce-phase1/angled-seating-desktop.png) · [mobile map](evidence/connect-commerce-phase1/angled-seating-mobile.png). The three-block arrangement demonstrates these tools; it is not a production seating plan or a promise about an actual interpreted show.


## Hosted administrator preview and FTP deployment

The user’s workflow is Git pull locally followed by FTP to ctsmd.me. This revision removes the local-only visibility restriction for the hosted administrator preview. No local server or development flag is needed on the hosted installation. Public visitors, members, students, volunteers and non-administrator local testers remain denied. Existing active application administrators can use their existing account without adding a date of birth; no new identity or role assignment is required.

1. FTP the updated application files, including `front.php`, `src/AppNavigation.php`, `src/OperationsHubExperience.php`, all `src/Commerce*.php` files, `assets/css/commerce.css`, `assets/js/commerce.js` and `assets/js/commerce-geometry.js`. Keep the server’s `.env` and storage intact. The Commerce assets have a new cache revision so previously cached local-only styling/scripts do not hide the update.
2. Sign in as an application Administrator and open **Operations → Commerce sandbox**, or `/admin/commerce` directly. This entry remains visible if Commerce tables are missing. Missing/partial setup shows explicit instructions rather than a missing navigation item or blank 404.
3. If the setup page requests the Commerce schema, back up the hosted database, review/import `database/migrations/101_connect_commerce.sql` followed by `102_commerce_simulated_reconciliation.sql` in the hosting database tool. Uploading the SQL files alone does not apply them. These are additive Commerce tables/triggers; they do not modify account/production/family schemas. Do not blindly repeat a partially completed import: migration 101’s immutable-layout triggers must be reviewed if already present.
4. Refresh `/admin/commerce`, click **Initialize Commerce sandbox**, then enable **Reserved-seat ticketing** in **Configuration**. This creates the Commerce owner/settings and enables simulated workflows; it does not activate public sales, a card processor, email delivery or notification workers.
5. Build a venue/layout and link a test performance to the appropriate existing production. Purchaser email remains `.invalid` only. Do not create duplicate Connect accounts or run the local persona fixture CLI on production.

The separate hosted switch defaults on only for this administrator preview. An explicit `COMMERCE_HOSTED_SANDBOX_ENABLED=0` pauses commands; the setup entry stays visible. Local grants remain useful only on the isolated local sandbox. Existing external ticketing links and public production pages remain unchanged. Rollback is the hosted pause switch plus retaining Commerce history, not deleting tables.

Verification: 10 hosted access assertions and 12 hosted HTTP assertions run in each of production and copied-local environment modes against synthetic loopback data. Non-loopback site hostnames use hosted administrator restrictions even if a local .env was copied by FTP. They cover real sign-in, administrator navigation before owner initialization, denial of members/volunteers/local testers, initialization CSRF, hosted configuration, kill-switch behavior, disabled administrators and existing administrator accounts without birthdates. The local Commerce HTTP suite now includes hosted gating assertions. Remote ctsmd.me visibility still requires the FTP upload and, if absent, the two reviewed database imports; this repository change is not evidence of an actual FTP deployment.


## Phase 1 acceptance recheck — October 31, 2026

The reattached Phase 1 request was byte-for-byte identical to the original brief. Existing milestones were rechecked rather than rebuilt. Later approved quantity selection, angled circles, ASL/stage landmarks and administrator-only hosted visibility remain in place. [Acceptance matrix and limitations](CONNECT_COMMERCE_PHASE1_ACCEPTANCE.md).

The recheck found the default Apache Permissions-Policy prevented camera access even on Box Office. The application `.htaccess` now retains the default camera/microphone/geolocation denial and overrides **camera only** to `(self)` for the exact `/admin/commerce?tab=checkin` page (including an application subdirectory and normal query ordering). It matches the original request line so front-controller rewrites cannot accidentally defeat the rule. Login, other Commerce tabs, lookalike routes and other pages keep camera denied. This policy only permits requesting a camera; the existing authenticated administrator gate, user-initiated Start QR camera action, browser permission prompt and manual-entry fallback still apply. Microphone/geolocation remain denied everywhere.

`tests/commerce_camera_policy.py` starts a separate Apache process on an ephemeral loopback port with a temporary static fixture and the actual `.htaccess`; it does not operate the user's MAMP server or production. Twelve real HTTP cases passed, with exactly one expected policy header per response. A corresponding isolated Apache CI job is added. Actual phone/scanner hardware and any additional hosting/proxy policy still require deployed verification. FTP must include the updated `.htaccess` to apply this fix; this session did not deploy or run production migrations.


## Complimentary admission issuance

The Complimentary workspace issues actual zero-price sandbox QR tickets to a synthetic recipient. Local Commerce administrators and box-office managers have the distinct `complimentary` capability; sellers, door staff, reporters and testers do not. Hosted preview remains limited to application administrators regardless of local grants. A reason, synthetic `.invalid` recipient, valid form session and CSRF token are required.

The service locks the performance, validates the entire 1–12-seat selection and allows only available, released or complimentary-allocated inventory. It does not override house holds, blocks, sold seats, active holds or uncertain-payment inventory. Issuance may occur with online sales closed, but not on a closed/archived production or with ticketing disabled. A retry with the same request/ownership token returns the original order/tickets; changed payloads are rejected.

No payment adapter is called and no payment attempt is invented. Ticket lines have `product_type=complimentary_ticket`; every monetary amount, including fees and tax, is zero. The existing `paid` order state represents settled fulfillment of a zero-value order, while the user interface explicitly says **Complimentary tickets issued / No payment was processed**. Payment and complimentary settlement share the same transactional inventory validation and QR fulfillment service. The reason/seat selection/zero-value settlement is audited once. Existing schema 101/102 is sufficient; no migration was added.

Complimentary tickets use normal online check-in, duplicate-scan rejection, revocation and reversal. The Orders cancellation action uses the protected full-order reversal lifecycle with zero financial value: checked-in tickets require admission reversal first; cancellation invalidates the old tickets, releases only that order’s sold seats and preserves history. Receipt capture remains unsent. Completed orders no longer display an expired hold warning.

Verification: 28 complimentary-service/authorization/concurrency assertions and 37 real HTTP assertions passed, plus payment settlement regressions. Actual competing processes tested two staff issuers and a purchase-hold versus complimentary-issuance race, with exactly one winner. Real hosted-mode browser testing issued two tickets, displayed zero totals/no payment and two QR codes, and checked the 390-pixel mobile layout without overflow. [Desktop confirmation](evidence/connect-commerce-phase1/complimentary-desktop.png) · [mobile confirmation](evidence/connect-commerce-phase1/complimentary-mobile.png). These were isolated synthetic tests, not ctsmd.me transactions.


## Neighboring-seat suggestions

**Find seats together** is now available above the customer seat map. Choose 1–12 seats and an optional pricing zone; the server suggests a group and preselects it locally. Review the map, accessibility notes and ticket-type quantities, then continue through the unchanged atomic hold flow. Suggestions never create orders or reserve inventory. If no matching group exists, the page offers fewer seats, another zone or manual selection. A failed checkout does not silently replace the selection with a new recommendation.

The basic server algorithm uses a single row and zone, actual seat coordinates, section/row display order and proximity to the row center. Rotated/vertical rows use their dominant geometric axis. It keeps unavailable seat positions in the sequence, excludes protected inventory, avoids large gaps, and refuses to connect across authored aisle/non-seat-space rectangles. Wheelchair and companion seats remain eligible. This is a conservative geometric suggestion, not a guarantee about sightlines or actual physical adjacency in an inaccurate venue plan; operators must verify published layouts. It does not prioritize a more expensive zone or claim globally optimal seats.

The recommendation service reuses safe expiration of abandoned held orders, while payment-pending/exception orders remain protected. Availability can change after a suggestion; checkout revalidates the complete selection under the performance lock. Closed sales yield no suggestion; closed productions remain read-only in the customer interface. There is no new schema or public route.

Verification: 20 MySQL recommendation checks cover deterministic grouping, no hold creation, competing availability, stale suggestions, protected allocations, invalid requests/ownership, zones, angled and vertical layouts, explicit aisles, large unmarked gaps, accessible seats, expired-order cleanup and lifecycle restrictions. The real HTTP/security suite now has 42 assertions including preselection, recoverable invalid counts and read-only archived production controls. A real hosted-mode browser selected a suggested pair, entered two Adult tickets and reached server order review at USD 42.87 with the configured discount/tax/fee. Mobile width 390 had no page overflow. [Desktop suggestion](evidence/connect-commerce-phase1/seats-together-desktop.png) · [mobile suggestion](evidence/connect-commerce-phase1/seats-together-mobile.png).
