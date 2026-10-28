# CTSMD Connect RC1 — manifest, preflight and recovery

October 28, 2026. Repository `arwentz01/CTSMD`, branch `audit/board-pilot-readiness`. **Preparation only; not deployment approval.** Current decision remains NO-GO. [TODO.md](TODO.md) is the only active work tracker; [RC1 evidence](RC1_ACCEPTANCE.md) records dated results and signoff requirements.

## 1. Candidate identity and payload

From a clean reviewed checkout, run `php bin/rc1-manifest.php` and save stdout to an access-controlled evidence directory **outside the checkout/document root**. It records the exact Git revision, branch and SHA-256/size of tracked blobs. It fails on a dirty/unsupported tree and never packages, copies or deploys anything. Preserve the downloaded CI artifact and its hash with the release ticket; a manifest is not signed approval. Authenticate its origin through the reviewed commit/CI run before trusting it.

| Manifest category | Handling |
|---|---|
| `web_runtime` | Coherent versioned web code/assets, including protected `src` and `.htaccess`; not a claim every file should be HTTP-readable |
| `operator_only_not_public` | CLI tools, including existing workers; retain in a denied/private operator location, never activate them as part of copying code |
| `migration_review_only` | Exact filename/hash inventory for DBA review, **never auto-import** |
| `native_source_not_distributable` | Expo source reference only, not a signed app or device acceptance |
| `evidence_not_public` | Docs/tests/workflows/design explorations: do not publish under web root |
| Excluded | `.env*`, Git metadata, dependencies, logs, storage/private files, base/reset/demo SQL; no secrets or real data in release artifacts |

Manifest files must be copied as one coherent revision, not a mixture of individual experiences and APIs. Retain the prior verified revision, PHP/config requirements and asset hashes. Do not use copied/corrupt hosting `.git` metadata as deployment evidence. Do not delete unmanaged host files automatically: an unexpected executable is an incident stop condition, not permission to remove evidence.

## 2. Preflight: read-only, explicit environment

The tool's `--target` is an **evidence label, not a connection selector**. For staging/production the loaded `APP_ENV` must match that label. Independently confirm the intended host/database/config with the release owner before running. Use a dedicated metadata/read-only DB principal; CLI configuration must not be in web URLs, command arguments, public logs or the manifest. No hosting connection was made in this RC1 implementation.

Run from the trusted full candidate in a private operator directory:

```sh
php bin/rc1-preflight.php --target=staging --manifest=/private/evidence/rc1-manifest.json
```

To compare an already-staged web-only payload against the same trusted manifest, add `--web-root=/absolute/staged/web-root`. Runtime, operator and migration files in the full trusted candidate are always checked; with this option the additional web root requires only web-runtime hashes. Schema declarations always come from that hash-checked tool checkout, not the staged web directory. Substitute `--target=production` only during an explicitly approved, correctly configured read-only hosting inspection. Staging/production labels without a manifest fail preflight.

The CLI starts a read-only transaction, reads information_schema, rolls back, and emits only object names, issue labels, counts, source hashes and review metadata. It never calls RuntimeSchemaGuard, applies migrations, scans real records, emits connection details, touches read cursors, or invokes workers. Exit 0 = checked schema/file contracts match, **not release GO**; 2 = mismatch/review blocker; 1 = inspection/configuration failure. `release_authorized` stays false. Missing metadata privileges must fail closed, not be bypassed.

Checks cover all source-declared tables/columns, latest trigger timing/event/table/body hash, obsolete trigger absence, InnoDB, and selected critical nullable types. Lexical trigger hashes ignore comments/whitespace; they are not a SQL semantic equivalence proof. The JSON explicitly lists omissions: all index/FK/type/default definitions, data backfills, permission grants, provider configuration and migration history still need operator review. Existing `bin/check-schema.php` remains a smaller 079–086 check; runtime schema version 84 is insufficient RC1 evidence.

## 3. Production/staging migration decision process

1. Record candidate revision, environment, operator, DBA/reviewer, verified matched DB/private-file backup, server version and trigger privileges. Obtain a maintenance/change approval before **any** production write; this runbook is not that approval.
2. Run the read-only preflight and preserve JSON/hash privately. Export `SHOW CREATE TABLE`/trigger definitions and an approved schema-only comparison privately; do not put credentials, definers or record content in public CI.
3. Review **every exact migration filename/hash** in `migration_review`. There is no authoritative applied-migration checksum ledger. Object presence cannot prove backfills or the right trigger revision. Missing/partial/present-but-drifted are distinct states; never use a max migration number to infer completion. Two different `069_*` files and two date-named mobile migrations must not be conflated.
4. Approve a per-file plan: current evidence, statements still required, data effects, ordering, expected assertions, backup and recovery point, reviewer and approval. Treat DROP/CREATE trigger replacement and DML backfills as changes even when tables exist. Do not rerun all migrations or base/demo seeds on an established database.
5. **062 special case:** current canonical base already defines revocation columns/index; blindly applying 062 fails with duplicate column. Existing hosting previously needed only revised trigger statements. Inspect exact columns/index and trigger bodies, preserve historical cutoffs, and review any statement-level reconciliation separately. The hash-pinned adjustment in the empty test bootstrap is **not** a production upgrade runner.
6. **086** is additive/nullable but not rerunnable; inspect both complete column groups. MySQL DDL implicitly commits. After a partial failure, stop and inspect actual state before any resume. **087–092** are outside the old guard and must be reviewed explicitly, even for parked features whose code may reference their tables. No automatic backfill, drop-column rollback or schema downgrade.
7. Rehearse the reviewed plan on a sanitized/restored isolated clone. Re-run preflight, role tests, counts/hashes and history/cutoff checks. Actual staging schema reconciliation and real-family production testing require separate approval.

## 4. Repeatable local/CI acceptance

Requirements: PHP 8.3 with PDO MySQL, mbstring, DOM, fileinfo, OpenSSL/cURL; MySQL 8 and native mysql CLI. CI creates an ephemeral MySQL service and empty `ctsmd_rc1_ci`. For local runs, create a **new empty** `ctsmd_rc1_<unique>` schema on loopback with a local test principal. No script drops/resets a database. Supply `APP_ENV=rc1-test`, `DB_HOST=127.0.0.1`, explicit `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` through private environment configuration; optional `RC1_MYSQL_BIN` points at the local client. Do not place secrets in shared command histories.

```sh
php tests/rc1_tooling.php
php tests/rc1_bootstrap.php
php bin/rc1-preflight.php --target=local --manifest=/private/evidence/rc1-manifest.json
php tests/rc1_acceptance.php
php tests/safeguarded_conversation.php
```

Bootstrap refuses a nonempty schema, non-loopback host, wrong database prefix or missing test mode. It imports reviewed source schema/migrations with mysql's delimiter support, no demo seeds. A pinned 062 fresh-base adjustment omits only the duplicate initial ALTER; it preserves the backfill and revised triggers and refuses changed source hash. On any SQL failure it stops, retaining the partial isolated schema for diagnosis; start a **new** schema after review, not a blind resume. This is not a reusable hosted migration tool.

Acceptance generates random fake credentials and eight accounts (seven requested personas plus a second Student) over two productions. It launches its own loopback-only PHP server; target URL is not configurable. It uses real login, cookies, bearer sessions and genuine CSRF fields, not an authentication bypass. Fixtures and records remain only in the guarded test schema. Finally it stops the server, disables fake accounts and revokes their bearer tokens, preserving fixture history. Repeated acceptance runs append independent fixtures; bootstrap is intentionally not repeatable on a populated schema. No outbound/cron worker runs; tests assert zero delivery attempts. Retained fake state is not proof of a production cleanup procedure.

CI uploads revision-bound manifest, read-only preflight and acceptance JSON (14-day CI evidence retention, not student-data retention policy). Also run existing web safety and Expo checks. A passing local PHP server test does not verify Apache deny rules, TLS/cookies behind a proxy, physical devices, OS notification delivery or browser accessibility. Expand the manual matrix in RC1 evidence before signoff.

## 5. Stop conditions and deployment order — approval required

Stop for missing/drifted schema/trigger, unknown file hash/unmanaged executable, unexpected real recipients, absent verified backup/restore, safeguarding or history regression, failed CI, wrong environment, partial migration, unapproved credentials/retention or unresolved in-scope device/privacy gate. Preserve evidence; do not “fix” by skipping guards or purging records.

After an explicit future deployment approval: record worker/cron state and queue fingerprints without running them; maintenance/read-only window; matched backup; approved migration plan rehearsed and applied by authorized operator; verify postconditions; publish coherent web revision; private preflight/hash check; anonymous/auth/sensitive-path smoke; approved fake-account persona tests; owner reviews evidence. Keep notification workers/reminders/lifecycle automation disabled unless separately authorized. Web-first scope must explicitly exclude unaccepted native functionality and still close all in-scope safeguarding and operational gates.

## 6. Rollback versus restore

**Code rollback:** if a code-only fault occurs and DBA confirms the previous version is compatible with the current additive schema, pause writes and retain queues, logs and incident evidence; repoint/copy the verified prior coherent web artifact and assets, preserve `.env`/private storage, invalidate PHP OPcache through the hosting-supported procedure, verify hashes and read-only preflight, then approved smoke tests. Never reinstate a prior version that widens guardian cutoffs or lacks mandatory authorization enforcement. No automatic rollback command is supplied.

**Schema/data incident:** do not run inverse migrations, drop new columns, re-enable guardians, reset cutoffs, truncate queues, or blindly replay backups. A backup restore may lose legitimate post-backup writes and resurrect revoked sessions/subscriptions. Escalate to the incident/DB owner; preserve current DB/files/queue state for reconciliation; agree the recovery point, accepted loss and authorization state before proceeding.

## 7. Matched restore rehearsal and recovery

1. Name incident commander, DBA, storage owner and safeguarding approver; approve RPO/RTO and retention. Record snapshot UTC, code revision, schema/trigger inventory, encrypted DB dump and private-storage backup, encryption-key custody and SHA-256 manifest outside the document root. Capture a consistent DB/files point with writes quiesced or a reviewed snapshot strategy; transactional DB dump alone does not synchronize binaries.
2. Verify encryption access, archive hashes and safe archive member paths **before extraction**. Reject absolute paths, traversal, symlinks outside the destination and unexpected executables. Do not import an untrusted dump as a privileged user.
3. Restore into a **new isolated schema and private directory**, never over the only live copy. Keep network egress and all workers/cron disabled; use test configuration, no production SMTP/push credentials, scrubbed logs, no public access. Review trigger definers/privileges for the target rather than globally disabling constraints.
4. Restore matched DB, code and private binaries, set least-privilege ownership, and compare metadata/file sizes/SHA-256. Exercise a harmless test private attachment through Student/current Guardian access and unrelated/former-Guardian denial. Check preserved messages, cutoffs, audit events, cast/history, submitted forms, archived production records, two-production membership and permissions.
5. Verify invalidated credentials cannot reappear after restore. Reconcile changes since snapshot using incident evidence and safeguarding approval; conservatively revoke restored bearer/browser/reset/invitation/device credentials as the approved recovery plan requires. Do not send restored queued messages or widen historical access. Reconcile queue IDs/status/deduplication with confirmed deliveries before any separately approved activation.
6. Record elapsed restore + validation time, actual restore point/loss, tested bytes/counts/hashes, route results, remaining defects and approvers. Historical 1s/6s local restore observations are not a hosted RTO commitment. Only after independent verification and explicit cutover approval may the recovered environment replace live service. Keep the original quarantined snapshot until retention/incident owners authorize disposal.

## 8. Signoff evidence envelope

Required: candidate/manifest hash and coherent payload verification; CI run URLs; dated staging/production read-only schema reports and reviewed migration plan; named backup/restore owners, retention and accepted RPO/RTO with a new matched restore rehearsal; seven-persona/two-production results and manual device/browser matrix; safeguarding/cutoff/history evidence; privacy/security approvals; queue disposition and proof workers stayed disabled; native scope decision. Track every unresolved gate in TODO.md. No script in this change grants release authorization.
