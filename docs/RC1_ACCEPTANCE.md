# RC1 acceptance evidence and signoff envelope

October 28, 2026 · `arwentz01/CTSMD` · `audit/board-pilot-readiness`.

**Decision: NO-GO for deployment/pilot signoff.** RC1 preparation is implemented; unresolved gates below remain. No live account, production data/schema, worker, notification delivery or deployment was exercised. [TODO.md](TODO.md) is the sole active tracker. [Release/recovery runbook](RC1_RELEASE_RUNBOOK.md) defines safe procedures, not authority to deploy. Prior [native acceptance](NATIVE_PHASE4_ACCEPTANCE.md), [pilot evidence](BOARD_PILOT_READINESS.md) and [launch assessment](PLATFORM_LAUNCH_READINESS.md) remain applicable; historical percentages are not coverage scores.

## Implemented release controls

- Clean-checkout, revision-bound manifest with file SHA-256/size and explicit web/operator/migration/native/evidence classifications. Secrets, logs, private storage and reset/demo/base SQL are excluded from deployment payload. CLI emits JSON only and does not deploy/package anything.
- Read-only staging/production preflight, explicit matching APP_ENV and trusted manifest; compares source declarations through 092 plus both date-named mobile migrations, columns, latest/obsolete trigger definitions and selected critical types. It always verifies the trusted full candidate and can additionally compare a web-only staged directory. No applied-migration inference or SQL executor.
- Restore/rollback procedure distinguishes compatible code rollback from matched DB/private-file recovery, records queue/session resurrection risks and requires independent authorization before cutover.
- Isolated MySQL/real HTTP fixtures for seven personas and two productions, with real login, valid-CSRF stale-form POSTs and saved cookie/bearer revocation. No hardcoded demo IDs or live target URL. Random fake credentials remain in memory. Cleanup disables fixture accounts and revokes tokens, verifies zero active fixture credentials and retains isolated history.
- Fixed a defect found by the positive control: legacy/native form submission omitted the assignment subject required by migration 056's trigger. It now records authorized `submitted_for_user_id` and current submitter on insert/resubmit, without changing assignee, guardian, lifecycle or structured-field authorization. The trigger was not removed or relaxed.

## Local evidence ledger

| Evidence | Result / interpretation |
|---|---|
| Source schema bootstrap | Fresh local MySQL schema, canonical base and every exact migration filename; no demo seed. Pass with pinned 062 fresh-base adjustment described below |
| New read-only schema inspection on complete fixture | 110 tables, 25 triggers, no declared-contract mismatch; not proof every index/FK/backfill/type/default is correct |
| Existing local demo inspection | **Blocked as expected**: missing 087–092 objects/columns including declared-age/support-needs, safeguarded preferences, statement requests and rehearsal video schema. Old runtime guard did not catch this. Demo was not upgraded as part of RC1 |
| RC1 tooling contracts | **21 pass**, no database/network |
| Seven-persona/two-production HTTP suite | **120 pass**, real local HTTP and migrated MySQL. Includes a second Student to distinguish linked household aggregation from cross-production leakage; repeat runs independently disable accounts/revoke tokens and count zero active fixture credentials |
| Existing safeguarding suite | **36 pass** on isolated migrated schema, transaction rolled back; never against live accounts in this work |
| Web safety / native contracts / exports | To be verified for RC1 source; prior Phase 4 evidence is not automatically a new RC1 run |
| Hosting preflight / migration / restore | **Not run**. No hosting connection, migration or new restore drill claimed |
| Physical-device, OS push, Apache/TLS/accessibility | **Not run**. Local PHP HTTP and Node mocks cannot establish these |

Fresh bootstrap initially stopped correctly at duplicate revocation columns in 062 because current `database/schema.sql` already defines both columns and their index. The test-only bootstrap pins migration SHA-256 `d85a0fbdf240a0c47e08007a729c48128226005f7169d4cdfd0d2d12c3f91c4e`, omits that initial duplicate ALTER only in a new empty guarded test schema, and retains data reconciliation and latest triggers. It refuses a changed file hash. This is **not** a hosted migration recipe or proof an existing production schema needs the same adjustment. Exact per-statement hosted reconciliation requires independent review. Two `069_*` files mean “max migration number” is also insufficient evidence.

Local schemas `ctsmd_rc1_20261028` (stopped bootstrap) and `ctsmd_rc1_20261028b` (complete fixtures) were created only on loopback MAMP. They contain synthetic data and are retained for diagnosis; no database was dropped and the existing demo/production records were not reset. Acceptance can be rerun with a new fixture suffix; bootstrap requires a new empty schema. No fixture password, token or private response body is included in evidence output.

## Role acceptance matrix — automated scope

| Persona | Positive acceptance | Negative/revocation acceptance |
|---|---|---|
| Administrator | Real web/native login, sensitive routes, production A schedule | Not used as evidence for least-privilege Staff; combined-role shortcuts avoided |
| Staff | Own staff role only; forms management, schedule, captured valid CSRF | Accounts/safeguarding denied; closed definition/toggle/builder/review POSTs leave fingerprint unchanged; saved session/bearer lose staff access after role removal |
| Guardian | Active relationships to one Student in each production; both schedules and child web forms | Native child-form detail remains 404 (known parity gap, not silently broadened) |
| Former Guardian | Login obtained while active; fixed old-thread history retained after actual relationship deactivation | Later message hidden; direct send, future schedule and child web form denied with saved credentials |
| Student | Own A schedule/native form; active B Student positive form submit provides non-inert control | Unrelated B event/form denied; completed/archived direct web/native form POSTs rejected; disabled account cookie/bearer denied |
| Caregiver | First-name-only family list and six-field scoped logistics response | General events/messages/channels/tasks/forms denied; no child web forms; saved bearer loses logistics after relationship deactivation |
| Volunteer | Active B signup and own future cancellation; history status preserved | Closed A signup denied; no unrelated B event/safeguarded thread; web/native logout replay denied |

The suite changes production A from active to completed/archived in its isolated fixtures to test stale valid-CSRF POSTs. It does **not** certify the full completion/readiness/archive workflow. Production B remains accessible and writable throughout. Form/submission/field plus audit/notification/email/push queue fingerprints remain unchanged for denied closed writes; conversation history is retained. No delivery worker runs and zero email/push delivery attempts are asserted. Staff's existing organization-wide authority is observed, not redefined as production-scoped.

## Unresolved blocking evidence for signoff

These are dated assessment findings, not a second active work queue. Owners/approvals must be assigned through TODO.md; none is inferred from a passing build.

| Gate | Blocking issue | Evidence required for RC1 signoff |
|---|---|---|
| RC1-01 | Hosted migration state and canonical/migration overlap lack a trustworthy applied-checksum ledger | Environment-bound read-only reports; exact reviewed filename/statement plan for partial/drifted state, permissions/index/FK/type/default and data effects; sanitized staging rehearsal, backup and DBA/release approval |
| RC1-02 | Safeguarding coverage is stronger but not exhaustive | Additional mixed parent/caregiver, multiple children/guardians, group/attachment/search/casting/task/notice/queue and reactivation cases, browser/native device acceptance with saved credentials; safeguarding owner signs boundaries |
| RC1-03 | Production history retention is not fully accepted | Complete/archive/People-removal tests on isolated restored clone: credits/cast/offers/forms/resources/messages/audit retained as policy requires. Existing roster removal may cascade original offer records (TODO); retention owner must resolve before real-family use |
| RC1-04 | Cross-process races not established | Concurrent close vs submit/review/assignment, last guardian, capacity and queue dispatch/revocation tests, lock/deadlock/retry evidence. Do not infer atomic safety from sequential tests |
| RC1-05 | Session/credential lifecycle gaps remain | Password reset/change/admin revocation, concurrent account switch, offline logout cleanup and restored-session replay tests; reviewed reconciliation that cannot resurrect revoked guardians or credentials |
| RC1-06 | Native device/distribution and parity gaps | Signed staging builds on physical iOS/Android; cold start, deep links, safe areas, largest text, keyboard, screen reader and cache/privacy acceptance. Explicitly include or exclude missing guardian structured forms/profile/family/caregiver/staff workflows from launch scope |
| RC1-07 | Notification/provider/worker gates remain open | Web OS display/tap and native provider decision/infrastructure, shared delivery-time reauthorization, preferences/invalid-token/receipt evidence. Owner disposition of historical live email queue; workers stay disabled until separate approval |
| RC1-08 | Recovery operations not signed off | Named backup/incident/storage owners, approved retention/RPO/RTO, new matched code/DB/private-file restore drill with hashes and permission tests, explicit queue/credential reconciliation and cutover approval |
| RC1-09 | Hosted protections and clean startup unverified for candidate | Apache deny rules, executable upload protection, private storage/log paths, secret/backup URL checks, TLS/secure cookies/proxy and clean-profile startup timing evidence; incident/credential-rotation owner confirmation |
| RC1-10 | Privacy/consent/retention decisions outstanding | Policy-owner approved privacy/terms/guardian consent, safeguarding/media/record retention and supported student login cohort; no invented legal acceptance |
| RC1-11 | Browser/device usability/accessibility not accepted | Dated device/browser versions, keyboard focus/return, mobile long tables/forms, VoiceOver/TalkBack and visual/readability findings with defects resolved |
| RC1-12 | Expo dependency advisory review unresolved | Review Phase 4's eight tooling advisories, SDK-compatible patch/exploitability decision and evidence; no blind forced downgrade or assertion bundle success resolves security |

## Manual staging acceptance envelope

Use only approved disposable accounts/restored sanitized data. For **each** persona record tester/date, environment/candidate/manifest hash, browser/device version, two production IDs, expected/actual route/status, data-state assertion and artifact hash. Repeat both allow and deny cases with direct URLs/POSTs, saved cookie and bearer; include two shows/two students, current/former guardian and caregiver on different children. Capture only synthetic content; redact secrets and identity data. Mark unexecuted cases NOT RUN, never PASS based on hidden controls or source inspection.

Before GO, review every RC1 gate with the responsible release/security/safeguarding/operations owners and attach explicit approval/scope. A web-first exclusion can narrow native scope only by recorded decision, not by silently omitting tests. No deployment authority is granted by this report or by the generated manifest.
