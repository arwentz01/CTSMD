# Connect Commerce Phase 1

Optional, simulated-only Commerce is owned by `commerce_organizations`. Its key identifies the installation's configured Commerce owner; it is not a claim that Connect's identity, production, family, storage or communication systems are tenant-isolated.

## Activation and authorization

Both `COMMERCE_SANDBOX_ENABLED=1` and `APP_ENV=local` or `rc1-test` are required. Hosted production remains inaccessible even if the flag is accidentally enabled. Database capabilities default off independently: ticketing, merchandise, education. Merchandise and education are future boundaries, not implemented sales flows. No public Get Tickets link, provider secret, credit-card field or notification worker is introduced.

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

Do not run migration 101 against production during Phase 1. Test on a new disposable loopback schema or an explicitly designated synthetic schema only. Runtime Commerce stays off if schema/configuration is absent. Rollback means disable the sandbox flag and retain Commerce records; do not drop transactional history as a rollback shortcut. Back up and review the migration under the existing release process before any future deployment.

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
