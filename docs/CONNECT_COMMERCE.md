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
