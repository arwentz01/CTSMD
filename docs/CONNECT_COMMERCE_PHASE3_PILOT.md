# Connect Commerce Phase 3 — hosted pilot runbook

Scope: [issue #4](https://github.com/arwentz01/CTSMD/issues/4), existing `audit/board-pilot-readiness`, baseline `e9cf460`. This is a **test-only release**. Implementation, fixture tests and release preparation do not authorize deployment, hosted SQL, live payments, public checkout, notification workers or audience email. The current deployment method is a local Git pull followed by FTP; FTP does not apply SQL.

## Release contents and compatibility

Milestones 1–3: `9bc13ea` (Stripe test provider), `5d17882` (delivery outbox), `c973be9` (customer checkout). Milestone 4 is `3285081`, followed by `3eaaee7` for deterministic deferred-script browser verification. It adds this playbook, read-only Commerce preflight, a revision-bound manifest wrapper, encrypted SMTP fixture, end-to-end acceptance and repeatable browser coverage. See [implementation details](CONNECT_COMMERCE_PHASE3.md) and [local results](evidence/commerce-phase3/verification.json).

The existing simulator, performance-first inventory locks, immutable seating snapshots, Box Office tender, complimentary tickets, discounts, reserved/general admission, explicit scheduling/DST, multi-show blocks, QR tokens and check-in remain the underlying services. There is no new inventory, order, identity or notification system. Existing external production ticket links and Playbill pathways were not changed. Commerce's configured merchant owner is not whole-platform tenant isolation.

Required server capabilities: PHP 8.3 with PDO MySQL, mbstring, DOM, OpenSSL and cURL; MySQL 8 with InnoDB, enforced CHECKs, composite FKs and trigger privileges for the **reviewed migration operator**. Use a separate read-only metadata/data account for preflight where possible. HTTPS and a valid public certificate are required for hosted Checkout and webhooks. No Composer/Stripe SDK install is required: the adapter uses the fixed Stripe HTTPS API with certificate verification. No card data enters Connect.

## Database preflight and migration plan

1. Preserve a verified encrypted database backup, trigger definitions, environment backup and previous web file set. Record UTC backup/release boundaries. Prove restoration into a separate disposable database before a hosted pilot. A downloaded dump alone is not restore evidence.
2. Inventory the hosted schema with the existing RC1 preflight and the new Commerce preflight. Read-only reports are metadata, flags and aggregate counts; they exclude account names, purchaser addresses, ticket/link tokens and provider secrets. `--target` labels evidence; it **does not select a server**. Use a privately configured connection and matching `APP_ENV=staging` or `production`. Staging/production inspection requires a trusted manifest. No tool here runs SQL migrations.
3. Review the installed versions of 101–106. Migration **107** is a prerequisite: venue/performance IANA timezone, explicit UTC offset and showtime slot identities must be reviewed for legacy performances. Do not infer showtimes from production ranges or guess an ambiguous/missing DST time. Archived history stays intact and unsaleable.
4. Review **108_commerce_stripe_test.sql**: inspect `SHOW CREATE TABLE commerce_payment_attempts` first. Its original unnamed CHECK is expected to be `commerce_payment_attempts_chk_1`; a host with a different/partially applied definition needs an operator-reviewed equivalent. The old simulated-only CHECK is replaced by an enforced simulated/Stripe-test allowlist. New remote IDs, immutable quote/request facts, monotonic collected/returned counters, webhook/refund records, `refund_pending` and organization activation default zero are added. Review unique provider IDs and composite foreign keys. Never broaden the CHECK to a live provider for this pilot.
5. Review **109_commerce_ticket_delivery.sql**: widens the existing queue recipient field, extends its category/private statuses and log outcomes, adds UTC link expiry, and links delivery/event metadata to the existing queue/orders. There is no audience backfill, automatic worker or historical message send. Existing links with null UTC expiry retain their old local-time check until expiry. Queue/outbox bodies contain private retrieval capabilities and require restricted storage/backups.
6. At an explicitly authorized maintenance window, pause new Commerce operations and mail workers, wait for in-flight local transactions, and apply only reviewed unapplied migrations in order. MySQL DDL auto-commits: a failed multi-statement import may be partial. Inspect actual definitions; **do not rerun the whole file blindly, drop tables or reset demo data**. Keep all public/payment/email activation flags off.
7. Verify InnoDB, complete columns/enums, unique boundaries, provider CHECK, FKs and immutable-layout triggers. Run post-import read-only preflight and inspect its notes. Review unresolved provider/refund states privately; do not “fix” them by deleting rows, expiring protected holds or changing order states by hand.

From a clean checkout, prepare a private manifest:

```sh
php bin/commerce-manifest.php > /PRIVATE/PATH/commerce-release.json
php bin/commerce-preflight.php --target=local --manifest=/PRIVATE/PATH/commerce-release.json
```

The wrapper reuses the RC1 file hashing/classification engine. It requires a clean checkout, records the exact Git revision, identifies 101–109 and marks deployment/SQL/public/audience authorization false. `.env`, storage, dependencies and reset/demo SQL are excluded. Only `web_runtime` is an FTP web payload; migration files are manual review material, `bin/` is operator-only, and evidence/tests/native source are not web payloads. Compare the received web files with the trusted manifest using the existing `rc1-preflight.php --web-root=...` path. Do not put private manifests, dumps, credentials or CLI workers into the public document root.

`commerce-preflight.php` starts a database-enforced **read-only transaction**, always rolls it back and makes no provider calls. Exit 0 means its file/schema/aggregate contracts pass, **not** launch approval. Exit 2 reports mismatched contracts/invariants; exit 1 is a safe configuration/inspection failure. It detects missing delivery quarantine states, remote-ID uniqueness, live-capable provider CHECK drift, duplicate valid entitlements, unprotected sold inventory and Commerce jobs exposed to the general worker. It flags unresolved showtime slots for active production lifecycles; archived legacy schedule normalization remains a separate migration review. Historical payment exceptions, pending refunds, delivery failures and old links are review notes, not deleted history. It cannot prove all backfills, all FK semantics, provider connectivity, tax correctness or inbox delivery. Review those independently.

## Safe file release order — operator procedure only

After approval and verified backups: pause the hosted Commerce preview and new Checkout creation; stop mail jobs; keep a compatible path available to reconcile already-started payments. Apply reviewed schema before code, then upload the complete revision's runtime services/assets before switching the front controller entry points. Avoid serving mixed PHP versions; use maintenance/atomic directory switching if hosting supports it. Clear PHP opcode cache through the hosting mechanism, then compare file hashes. Browser asset versions changed for this release.

Do not overwrite the hosted `.env` with local defaults. Set `APP_ENV=production` on the real host, use real hosted database settings, and keep the admin-only preview/publication/grant boundaries. Use a private release archive rather than uploading `.git`, tests, docs, `bin/`, schema/reset SQL or dependency directories. Restore preview only after post-upload read-only preflight and authorized smoke checks. This task has performed none of those hosted steps.

## Configuration and Stripe destination

All credentials are environment-only; never put their values in screenshots, issues, commands stored in shell history or reports.

| Setting | Pilot purpose / default boundary |
| --- | --- |
| `COMMERCE_ORGANIZATION_KEY` | Existing installation merchant owner; must match its Commerce records. |
| `COMMERCE_HOSTED_SANDBOX_ENABLED` | Existing hosted preview; set 0 to pause UI actions. Explicit staff/tester grants remain independently enforced. |
| `COMMERCE_STOREFRONT_PREVIEW_ENABLED` | Restricted customer layout, not anonymous sales. |
| `COMMERCE_STRIPE_TEST_ENABLED=0` | Adapter opt-in; rejects live key prefixes. Keep available for unresolved test payments once pilot begins. |
| `COMMERCE_STRIPE_CHECKOUT_ENABLED=0` | Separate new-session creation gate. Hosted Stripe creation requires application administrator authority. |
| `COMMERCE_STRIPE_TEST_SECRET_KEY` | Test/restricted test key only, with required account, Checkout, PaymentIntent and refund permissions. |
| `COMMERCE_STRIPE_ACCOUNT_ID` | Expected organization merchant account; adapter verifies `/v1/account`. |
| `COMMERCE_STRIPE_TEST_WEBHOOK_SECRET` | This test destination's `whsec_` signing secret. |
| `COMMERCE_PUBLIC_ORIGIN` | Trusted HTTPS origin, no credentials/path; never inferred from request Host. |
| `APP_BASE_PATH`, `COMMERCE_PUBLIC_BASE_PATH` | Matching installation path if hosted below a subdirectory; empty at domain root. |
| `COMMERCE_PAYMENT_WORKER_ENABLED=0` | Opt-in bounded reconciliation worker; no cron installed. |
| `COMMERCE_PUBLIC_SALES_ENABLED=0` | Independent public activation request; leave off. |
| `COMMERCE_PUBLIC_SALES_APPROVED=0` | Separate approval gate; leave off. Organization `public_sales_enabled` also defaults 0. |

Test-only adapters **cannot** satisfy `verified_live_provider`, even if all public request/approval/organization flags are set. Module enablement, preview, provider configuration, public approval and organization activation remain separate. Live keys are rejected, not silently switched into another configuration.

Create a **test-mode snapshot-event** destination at `https://HOST/commerce/webhooks/stripe` (include installation base path). Subscribe to:

- `checkout.session.completed`, `checkout.session.expired`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`
- `payment_intent.succeeded`, `payment_intent.canceled`
- `refund.created`, `refund.updated`, `refund.failed`, `charge.refunded`

The configured API version is `2026-10-28.endive`. Use the actual destination's signing secret; a Stripe CLI forwarding secret belongs only to that forwarding endpoint. A signed test event queues normalized metadata; a worker or authorized refresh retrieves authoritative API facts before fulfillment. A browser success URL is never proof of payment. Keep reconciliation credentials when closing new checkout. Unknown/partial/late results and pending refunds retain inventory until safe evidence resolves them. Never release pending seats just because a worker is down.

Approved payment worker command: `php bin/process-commerce-payments.php`. Schedule it only after hosted approval, with a single supervised cadence initially; monitor queued age, failed/manual events, exceptions and pending refunds. Worker batches are bounded. Duplicate workers/events are safe for ticket issuance, but do not use parallel workers as a substitute for monitoring or network failure recovery. Worker installation/activation has not been performed.

## Ticket email pilot

Default: `COMMERCE_MAIL_DRIVER=capture`, `COMMERCE_MAIL_WORKER_ENABLED=0`, `COMMERCE_MAIL_ENABLED=0`, `COMMERCE_MAIL_APPROVED=0`. No general mail worker can claim Commerce jobs, including during mixed-version FTP updates. In capture mode the dedicated worker records `captured`, never `sent`.

For a separately approved inbox pilot, configure the existing `MAIL_DRIVER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION=tls|ssl`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. The Commerce sender explicitly requires TLS and uses the SMTP path directly; PHP `mail()` and log fallbacks cannot send Commerce jobs. Configure provider-verified From domain, SPF/DKIM/DMARC and sandbox limits with the chosen provider. The body uses escaped branded multipart text/HTML, quoted-printable transfer encoding and bounded SMTP lines/responses.

An exact `COMMERCE_MAIL_TEST_RECIPIENTS` list and `COMMERCE_MAIL_APPROVED=1` permit only those approved test inboxes as synthetic purchaser addresses; `.invalid` remains accepted by default. Sending additionally requires `COMMERCE_MAIL_ENABLED=1`, driver `smtp`, and the dedicated worker flag. Start with capture; approve one controlled inbox only after inspecting the captured confirmation. Do not add audience lists. Command after approval: `php bin/process-commerce-deliveries.php`. No mail cron was installed or enabled here.

Confirm links/one QR per valid ticket, performance/venue/timezone, seats/types, itemized total, printing/PDF, 24-hour expiry and refund revocation. Sandbox links require an authorized tester account as well as the random capability; **anonymous audience email retrieval is not activated**. Failed email does not invalidate tickets. Resend is owned/authorized, idempotent and rate limited (confirmation plus two additional requests/hour). Expired queued links suppress and need a new authorized delivery request. SMTP is at least once: a crash after provider acceptance can cause a duplicate; stable Message-ID helps investigate, not an exactly-once guarantee.

For bounce/inbox status, configure an approved provider/DSN bridge to the signed normalized `/commerce/webhooks/mail` contract described in the implementation document. Authenticate the provider upstream; never forward arbitrary unsigned audience payloads. Preserve the random delivery reference from the message header, sign with a separate environment secret and send only normalized ID/reference/type. Bounces/complaints affect delivery state, not financial/ticket state. Without that bridge, `sent` means SMTP acceptance only. No external SMTP account, inbox placement, DSN bridge or real audience message was verified here.

## Acceptance and evidence

Run destructive/concurrency fixtures **only** against the guarded `APP_ENV=rc1-test`, `DB_HOST=127.0.0.1`, disposable `ctsmd_rc1_*` schema. Never run `rc1_bootstrap.php` on a populated schema or a host. Fresh bootstrap refuses nonempty databases. Fixture cleanup disables its synthetic accounts and archives fixture productions while retaining orders, tickets and audit history.

The CI workflow `.github/workflows/rc1-acceptance.yml` is the repeatable suite list: fresh schema, RC1 tooling/file preflight, persona/safeguarding regressions, Commerce services/real HTTP/process races, SMTP TLS fixture and browser acceptance. The browser job uses pinned Playwright 1.56.1 installed only into a temporary test directory, real login (no `/dev/identity`), private fixture credentials, loopback-only URL, capture/no-provider flags, screenshots and JSON results. It refuses hosted URLs. It retires fixture users and stops its server on exit. Do not upload its private fixture JSON or server/auth logs as artifacts.

To repeat the browser job locally, follow that workflow's environment/fixture/server commands using a disposable database. `tests/commerce_phase3_browser.mjs` covers 12 responsive pages at 1440/768/390/320px, labels, keyboard map control, mixed ticket quantities, rejected-field recovery, exact totals, simulator confirmation, one QR per ticket and queued-not-sent status. In this development session, equivalent local CUA browser interactions and responsive measurements were executed; the separate Playwright script's local execution is not claimed. Its actual CI execution passed 34 checks on `3eaaee7`; see the run links below.

| Area | Executed local evidence |
| --- | --- |
| Fresh migration chain | Base + all migrations through 109 bootstrapped into a new disposable MySQL schema, no demo reset/worker. |
| Commerce regression | 31 PHP suites passed, including 36 Stripe checks, 31 delivery checks, seven mail HTTP checks, seven actual loopback TLS SMTP checks, 24 Phase 3 end-to-end/preflight checks and eight guest policy checks. |
| Concurrency | Separate processes: simultaneous holds; Stripe duplicate settlement; Stripe vs Box Office; mixed reserved/GA channels; bulk blocks vs purchase; concurrent scheduling and duplicate scanner admission; delivery worker claiming. |
| HTTP/security | CSRF/ownership/role boundaries, forged redirects, signed/forged/replayed/stale/live provider events, revoked/minor/disabled grants; copied-local hosted configuration separately tested. |
| Provider failure states | Unknown creation, retry identity/window, expiry, amount mismatch, partial capture, late unpublished success, refunds pending/failed/retried/completed, reordered events. Synthetic API fixtures, not a Stripe network pass. |
| Purchase through admission | Signed test event → authoritative fixture inquiry → shared fulfillment → captured outbox → secure link → QR → one admission → reversal → verified fixture refund → revoked retrieval/QR, history retained. |
| Browser | Real sign-in; reserved wheelchair/companion mixed types, rejected-email recovery, GA quote/hold, mobile confirmation, map keyboard Space, Escape tooltip, skip-link focus; 12 width/surface combinations without horizontal overflow, unlabeled form controls or missing primary heading. |
| Measured accessibility | 390px inputs 16px, inspected seat-list target 63px, confirmation buttons 44px; sampled text/control contrast 8.17–15.45:1; 768px skip link focuses the seat-list heading. These are targeted checks, not full WCAG certification. |
| Safety/tooling | 18 web safety suites, PHP lint across source/bin/tests, JS syntax/geometry, 12 actual Apache camera-policy cases and 30 RC1 tooling contracts. |

The M1 and M2 commits passed remote RC1/safety CI. M3's RC1 run found an obsolete literal receipt-copy assertion; M4 corrected it while retaining the security checks. The first M4 browser run checked focus before the deferred script ran; `3eaaee7` waits for the actual focus condition. **Final implementation CI at `3eaaee7` passed:** [RC1 including all backend, safeguarding, Commerce and 34 Playwright checks](https://github.com/arwentz01/CTSMD/actions/runs/41233229572), [PHP safety and Apache camera-policy jobs](https://github.com/arwentz01/CTSMD/actions/runs/41233205982). Actual job conclusions and logs were reviewed. The subsequent evidence-only closeout changes documentation only. Do not label an empty connector response a CI pass; its run listing filters PR events and omits branch push runs.

For an authorized hosted pilot, record operator/date/revision and private evidence references for these steps. Leave unchecked until actually executed:

- [ ] Trusted file hashes, migration/trigger/FK review, read-only preflight and restored-backup rehearsal pass on the hosted-equivalent environment.
- [ ] Anonymous visitor remains blocked; active admin can use restricted Stripe test; ungranted volunteer, student, revoked/disabled users cannot gain Commerce staff authority. Explicit preview/staff grants retain their existing scope.
- [ ] Only published/approved explicit future performances appear; closed/cancelled/unpublished/past showtimes reject POST sales across web/Box Office. Multiple shows/day, DST and irregular schedule remain correct.
- [ ] Actual Stripe test-card success, decline, cancellation, abandoned/expired session and network uncertainty reconcile to matching Dashboard/session/PI/capture totals. Never use a real card.
- [ ] Duplicate/reordered test-destination deliveries and worker retry issue one ticket set; no released pending inventory or duplicate seat sale under controlled synthetic concurrency.
- [ ] Actual Stripe test full refund reaches provider-confirmed success; pending/failed retries preserve protection; admission reversal is required, and old QRs stop admitting.
- [ ] Capture inspected, then one specifically approved inbox receives a test confirmation; From authentication, inbox/spam, link expiry, QR and print/PDF verified. No audience delivery.
- [ ] Signed bridge bounce/complaint and failure/retry monitoring demonstrated, or explicitly retain capture-only delivery with that blocker.
- [ ] Physical iOS Safari and Android Chrome complete the flow, including zoom/text size/back navigation and test-only Stripe return/session continuity. Screen-reader review completed.
- [ ] Physical camera/wedge scanner: correct performance, duplicate/wrong/refunded/relocated code rejection and authorized reversal; permissions denied fallback. No offline admission claim.
- [ ] Final remote CI green and recorded; separate rollout/public approval decision documented. Public/live gates remain off for this test release.

## Rollback and restore

Prefer a **forward repair with Commerce paused**, retaining new schema/history, over destructive down migrations.

1. Stop new sessions (`COMMERCE_STRIPE_CHECKOUT_ENABLED=0`), keep public flags zero, pause customer preview if needed, disable Commerce mail sending/worker and isolate in-flight jobs. Preserve capture/outbox data. Keep Stripe test configuration and a compatible reconciliation path until all remote attempts/refunds are reviewed; turning off credentials does not cancel provider sessions.
2. Record private inventories of pending/exception/refund states, remote IDs/events, queue leases and the UTC incident boundary. Do not release or rewrite them. Continue/recover reconciliation on compatible code after file repair; ambiguous old creation requests require manual provider review, not a new idempotency key.
3. For file-only rollback, restore the complete previously verified **schema-compatible** runtime set, clear opcode cache and verify hashes. A pre-108/109 Commerce version is not compatible with Stripe/refund/outbox states. If reverting the wider platform to an older baseline is unavoidable, leave Commerce unavailable and workers stopped rather than serving those records through incompatible code. Do not downgrade enums or drop new tables/columns.
4. A database restore requires a separate explicit approval, a fresh forensic backup of current state, proven isolated restore and a plan to reconcile every provider operation after the backup boundary. A stale restore can lose sale/refund/delivery identities and create duplicate entitlement/payment risk. Do not restore production merely because files were rolled back. Never replay provider creation or audience mail blindly after restore.
5. In an isolated restored copy, rerun read-only preflight, migration contracts, synthetic payment/refund/queue/QR tests and history counts. Reconcile remote test objects against restored attempts before any worker resumes. Retain original financial/admission/audit records and incident evidence. Re-enable only the deliberately approved test gates after operator signoff.

## Outstanding signoff blockers

| Blocker | Required evidence / owner action |
| --- | --- |
| Hosted migrations/file parity not executed | Authorized operator backup/restore proof, reviewed 107–109 definitions/import evidence and revision hash/preflight report. |
| Stripe merchant credentials/destination unavailable to development | Correct test account/key permissions, matching account ID, actual signed test webhook receipt, Dashboard/API reconciliation and refund proof. |
| External mail/provider configuration absent | Approved From/inbox, verified TLS/SMTP, domain authentication, inbox/link test, failure monitoring and authenticated bounce bridge. Loopback TLS is not inbox proof. |
| Implementation CI resolved; release provenance still required | RC1, safety and 34 browser checks passed at `3eaaee7` (links above). Bind any hosted file set to a freshly generated clean-revision manifest and rerun checks after runtime changes. |
| Physical mobile, screen reader and scanners unverified | Named operator/device/browser matrix and synthetic results, including Stripe return, denied permissions and duplicate scans. |
| Real customer transactions intentionally unsupported | This release has only simulated/Stripe-test providers, no public activation, no anonymous audience retrieval and no audience email approval. A separately authorized live-provider/public-readiness release is required; configuring a live key is deliberately insufficient. |

No hosted deployment, hosted migration, actual Stripe charge/refund, real audience email, notification-worker activation or physical-device pass is claimed.

Final local release-tool evidence: a clean `3285081` manifest contained 920 classified files; revision-bound Commerce preflight reported zero file mismatches, schema/invariant checks passing, public sales false and no data modification. Regenerate the manifest for the final evidence-only checkout before any approved FTP preparation. Synthetic UI/CI fixtures were retired and the dedicated local browser server stopped; financial/ticket/audit history was retained.
