# Connect Commerce Phase 3

Assignment: [GitHub issue #4](https://github.com/arwentz01/CTSMD/issues/4). Work continues from `e9cf460` on `audit/board-pilot-readiness`. No deployment, hosted SQL, live charge, unrestricted public checkout or audience email is authorized by this implementation.

## Milestone 1 — Stripe test-mode integration

Stripe-hosted Checkout keeps card entry outside Connect. The provider adapter uses the fixed Stripe HTTPS API endpoint, certificate verification, bounded responses/timeouts and pinned API version `2026-10-28.endive`. No dependency or API-host override is required for FTP installations. An injected PHP transport is used only by synthetic tests. Live key prefixes and signed live-mode events are rejected. Restricted test keys are supported; configure only the required Checkout, PaymentIntent, account inquiry and refund permissions.

The installation's existing organization is the merchant. Its configured Stripe account ID is checked against `/v1/account` before API operations; this does not implement Stripe Connect or platform-wide tenant isolation. No secrets are stored in tables, URLs, reports or Git.

A Checkout request is durably saved and its order moved from `held` to `payment_pending` under the existing performance lock **before** any provider request. Retries use the same frozen request and idempotency key. The original 10-minute hold is no longer eligible for expiry once payment starts. Checkout uses a 31-minute provider expiry because Stripe requires at least 30 minutes. Unknown creation results remain protected and can recover through the same API request or a signed event arriving before the session ID was saved. Creation retries stop before Stripe's idempotency retention window can be exceeded; unresolved old attempts require operator/provider review, never blind release or a new session.

Browser redirects never settle an order. Both inquiry and signed webhook processing retrieve authoritative Checkout/PaymentIntent/charge/refund facts, validate the merchant, test mode, order/attempt binding, currency, exact integer totals and capture, then call the existing shared settlement/fulfillment service. Mismatches, partial captures and unsaleable performances protect inventory as exceptions. A verified expired session releases inventory only after any associated in-flight intent is authoritatively cancelled. Delayed/unknown provider operations cannot silently free seats.

`/commerce/webhooks/stripe` accepts bounded raw JSON with Stripe's timestamped HMAC signature and 300-second tolerance. It persists normalized event IDs, hashes, object/attempt references and retry metadata, not raw payloads containing purchaser data. Unknown/unrelated events are ignored. Duplicate or reordered events do not duplicate tickets; the latest API facts take precedence. The route deliberately uses provider authentication rather than browser login or CSRF.

Full refunds fence the order as `refund_pending` before network activity, preventing admission/reissue and protecting sold inventory. Only an authoritative completed return revokes/releases through the existing refund service. Pending/unknown returns remain protected. Definite failures restore the previous order state; a new refund key is permitted only after verified failure and explicit authorized retry. Partial captures and previously partially returned captures can be fully returned without issuing tickets; all collected amounts and refund identities are retained separately from the original order quote. Already-admitted tickets require reversal. No live financial transaction can pass this adapter.

### Schema and configuration

Migration **108** requires 101–107. It extends the existing provider attempts/order states, replaces the simulated-only provider check with a test-only allowlist, and adds durable provider webhook/refund records. Review the actual original CHECK constraint name (`commerce_payment_attempts_chk_1`) using `SHOW CREATE TABLE` before import; do not blindly repeat a partial ALTER. Migration 107's historical timezone review remains necessary.

Environment variables, disabled/empty by default:

- `COMMERCE_STRIPE_TEST_ENABLED=0` and `COMMERCE_STRIPE_CHECKOUT_ENABLED=0` are separate adapter/creation gates.
- `COMMERCE_STRIPE_TEST_SECRET_KEY`, `COMMERCE_STRIPE_TEST_WEBHOOK_SECRET`, `COMMERCE_STRIPE_ACCOUNT_ID`: environment-only credentials and expected merchant ID.
- `COMMERCE_PUBLIC_ORIGIN`: trusted HTTPS origin; no request Host header is trusted. Local synthetic fixtures may use loopback HTTP.
- `COMMERCE_PAYMENT_WORKER_ENABLED=0`: separate opt-in for `php bin/process-commerce-payments.php`.

Configure a **test-mode snapshot-event** webhook destination at `https://YOUR-HOST/commerce/webhooks/stripe`, using the installation base path where applicable. Subscribe to Checkout completion/expiry/async results, PaymentIntent success/cancellation, refund changes and charge refunds. Use the destination's own signing secret, not a CLI forwarding secret on another destination. Keep reconciliation credentials available when pausing new Checkout creation. Do not point a live-mode destination at this test-only handler.

The worker processes queued events and reconciles unresolved orders/refund intents with bounded batches and backoff. It is not installed as cron or activated here. A missing destination/worker leaves pending inventory protected; customer/staff authoritative refresh remains available. Provider facts and normalized error codes are retained without logging secrets or remote response bodies.

### Verification so far

Synthetic transport fixtures, real MySQL and real HTTP cover signed/forged/stale/live event handling, exact amounts, duplicate/reordered events, ambiguous creation, late unfulfillable success, partial capture, successful/pending/failed refunds and explicit refund retry. Separate processes verify duplicate Stripe settlement produces one ticket and Stripe versus Box Office sale produces one winner. The simulated provider and existing transactional workflows remain in the regression suite. No actual Stripe account call or hosted webhook delivery is claimed without configured credentials.

Technical references checked during implementation: [Checkout creation](https://docs.stripe.com/api/checkout/sessions/create), [session inquiry](https://docs.stripe.com/api/checkout/sessions/retrieve), [webhook verification](https://docs.stripe.com/events/manage-webhook-endpoints), [refund API](https://docs.stripe.com/api/refunds/create).
