# Executive Summary

Overall readiness:

- **Board demo: 82%**
- **Limited pilot: 50%**
- **Board recommendation: CONDITIONAL GO** after the BEFORE BOARD items below are closed and rehearsed.
- **Pilot recommendation: NO-GO** until host-side incident response is complete, the locally validated guardian-reactivation implementation is deployed and runtime-verified, and the remaining delivery and least-privilege checks are closed.

Authenticated browser testing was completed on October 20, 2026 against https://ctsmd.me using fake demo accounts. The exercised personas were:

- Andrew Wentz: Administrator + Production Staff + Moderator + Safeguarding + Member
- Jamie Carter: Guardian/Parent
- Emma Carter: Student
- Alex Morgan: Parent + Volunteer

These are combined-role fixtures, so they prove the listed positive workflows but do not prove least-privilege boundaries for a pure Production Staff or pure Volunteer account. No destructive action, outbound post/message, form submission, attendance save, account change, or volunteer signup was performed. Opening existing conversations and channels updated read cursors for the Guardian and Student accounts.

The original clean-browser ERR_TIMED_OUT root cause is **not established**. It was not reproduced in the connected authenticated browser. Retained-session navigation was generally 0.2 to 0.6 seconds. Earlier clean anonymous testing did record three requests that exceeded 20 seconds before successful retry. Runtime schema work remains a plausible latency contributor, but evidence is insufficient to call it the cause or solved.

# Must Fix Before Board Demo

| Priority | Item | Closure |
|---|---|---|
| BEFORE BOARD | Deploy and runtime-verify the Student household authorization fix. Student access to /family-hub and /family/manage was a confirmed server-side permission leak. | CLOSED October 20: deployed direct-route checks denied fake Student Emma at both routes. |
| BEFORE BOARD | Deploy and verify the dashboard form-count fix. Emma's home advertised a Guardian-completed form that her /forms page correctly did not expose. | Repository query aligned to the actionable assignee; deployment verification required. |
| BEFORE BOARD | Rehearse one current production, not Matilda's expired August data. | Urinetown has an Oct. 21 rehearsal and a working attendance path. Select Urinetown before the Board demo. |
| BEFORE BOARD | Add one future fake volunteer shift with eligibility and approval examples. | Alex has complete readiness/history but no live opportunity, so signup/cancel/approval remains DATA/SEED ISSUE. |
| BEFORE BOARD | Prepare a pure Production Staff account. | Current staff evidence comes from Administrator/Moderator/Safeguarding combined accounts. |
| BEFORE BOARD | Repeat /archive with the Student after deployment. | /archive failed once during Emma's session but works for Administrator and the handler/service exist. Treat as unconfirmed persona-specific runtime failure until retested. |
| BEFORE BOARD | Perform a true clean-profile startup capture. | Capture first navigation, redirects, timing and HAR/server logs without retained cookies. Do not claim the timeout fixed. |
| POLISH | Improve small mobile tap targets and visibly style the desktop Sign out button. | No page-wide mobile overflow was found at 390 × 844. |

# Must Fix Before Pilot

- **BLOCKER:** complete hosting incident response for the confirmed PHP web shell formerly found at assets/images/accesson.php. Follow [HOST_INCIDENT_RESPONSE.md](HOST_INCIDENT_RESPONSE.md): preserve evidence, rotate credentials, invalidate sessions/tokens, inspect sibling sites/backups/logs, and clean-deploy reviewed source. Repository inspection cannot establish that Bluehost is clean.
- An earlier 062-shaped Bluehost schema and the previous 17-check safeguarding suite were observed successfully. The revised migration source implementing the approved reactivation rule has not been deployed and those earlier results do not validate the revision.
- **BLOCKER pending deployment:** the approved V1 rule is implemented and passed 36 checks on a disposable MySQL 8.0.44 clone: a guardian who becomes inactive retains only the historical conversation content available through their revocation cutoff. Reactivation does not reopen that historical thread or reveal messages sent during the inactive period. Future safeguarded communication uses a current safe conversation.
- Web and mobile sends invoke the same transaction-aware safeguarding policy. The deployed database suite passed role-change, disabled-account, all-guardian, and Student/Student checks. Disposable-token endpoint tests separately confirmed active fake-persona access, disabled-account denial, thread IDOR denial, and mobile send-policy denial without creating a message.
- **BEFORE PILOT:** prove email cron processing and real inbox receipt; six messages are currently queued with no attempts.
- **BEFORE PILOT:** close push retry/reclaim/dedupe and audience recheck gaps, then prove receipt on supported devices.
- **BEFORE PILOT:** test dedicated Moderator-only, Safeguarding-only, Staff-only, Volunteer-only, and least-privilege negative routes.
- **BEFORE PILOT:** run database-backed lifecycle tests, restore rehearsal, rate-limit validation, CSRF negative tests, and direct-object authorization tests.
- **BEFORE PILOT:** complete keyboard focus containment/return and long-table testing on mobile.

# Demo-Ready Workflows

| Persona / workflow | Classification | Authenticated evidence |
|---|---|---|
| Guardian: login, home, Family Hub, linked child, family calendar, assigned form, notifications | READY WITH POLISH ISSUE | Jamie showed Emma, two active productions, an assigned Seussical form, calendar, resources, messages and notifications. Form rendering was opened without submission. No distinct linked-child profile link is presented from Family Hub. |
| Student: home, My Theatre, calendar, cast, Theatre History, resources, Community, messages | READY WITH POLISH ISSUE | Emma showed published cast, two verified credits, acting résumé, schedule, attendance, Community and safeguarded messages. Deployed direct-route checks deny Student access to both household routes. Dashboard form-count deployment verification remains open. |
| Volunteer: readiness, credentials, training, service history | READY | Alex showed 4/4 current readiness, verified training, active verification and 12.5 verified hours. |
| Volunteer: opportunity, signup/cancel, approval | DATA/SEED ISSUE | No current opportunity or approval-gated shift exists. Seeded shifts ended in August. |
| Production Staff: working production, workspace, schedule, Production Day, attendance, groups, casting, readiness, notices | READY WITH POLISH ISSUE | Combined-role staff accounts loaded all routes. Urinetown Oct. 21 shows one rehearsal, one expected attendee and a working Take attendance route. No attendance save was made. |
| Administrator: accounts, people, registrations, channels, moderation, safeguarding, email operations | READY WITH POLISH ISSUE | All routes loaded with populated demo data. Moderation queue was empty; safeguarding showed one resolved case; email showed six queued and zero attempted. |
| Archives | READY WITH POLISH ISSUE | Administrator archive displayed three archived productions. Student retest remains required due to one earlier response failure. |
| Fresh-browser startup | RUNTIME FAILURE, UNRESOLVED | Timeout was not reproduced in the retained authenticated browser; earlier clean anonymous requests exceeded 20 seconds before retry. |

Routes actually loaded across authenticated sessions include:

/app, /account, /my-theatre/, /family-hub, /family/manage, /calendar, /channels, /channels/view, /messages, /messages/thread, /forms, /forms/view, /resources, /notifications, /cast, /theatre-history, /volunteer-readiness, /volunteer/training, /volunteer/history, /volunteer/verifications, /volunteer-shifts, /volunteer/approvals, /production, /schedule, /attendance, /attendance/take, /production/day, /production/readiness, /production/groups, /production/people, /production/casting, /production/notices, /staff, /archive, /admin/operations, /admin/accounts, /people, /admin/registrations, /admin/channels, /admin/moderation/terms, /admin/moderation/queue, /admin/forms, /admin/email, /admin/volunteer-shifts, /safeguarding, /safeguarding/cases, and /safeguarding/audit.

Actions actually exercised:

- selected Urinetown as the working production;
- opened current and historical production surfaces;
- opened channel and message threads, exercising read-state changes;
- followed calendar, production schedule, attendance, casting, readiness, notices and archive paths;
- opened Guardian form rendering without submitting;
- opened volunteer approval review and attendance controls without changing records;
- directly navigated to sensitive routes from non-privileged accounts.

## Security and safeguarding closure evidence

The repository implementation now follows the existing documented rule: every active, available guardian relationship is included in a Staff/Student safeguarded conversation. It no longer relies on only the primary guardian.

Migration `062_safeguarded_conversation_revocation.sql` adds non-destructive participant access state:

- `access_revoked_at` stops live participation;
- `history_through_message_id` fixes the last message a former guardian may read;
- family-relationship and account-status triggers synchronize guardian access;
- existing history and audit rows remain intact.

`SafeguardedConversationPolicy` is now authoritative for both web and mobile sends. It derives current Student roles at send time, upgrades a direct conversation after a Student role is added, requires current CTSMD staff and every active available guardian, rejects Student/Student direct messaging, and rejects disabled or history-only participants.

Message lists, threads, search, attachments, unread counts, and read cursors honor the historical cutoff. New message push creation excludes revoked participants, and push delivery revalidates access so an already-queued message push is suppressed after revocation. No conversation-message email producer or separate in-app message-notification producer exists in the inspected repository; therefore no equivalent queued email or in-app payload was found to revoke. This is not a claim that general delivery queues are production-ready.

An earlier 062-shaped schema was observed on Bluehost: its runtime schema guard passed and the previous `tests/safeguarded_conversation.php` passed 17 rolled-back checks. The revised migration and expanded reactivation test have not been deployed. They passed 36 rolled-back checks after migration 062 was applied to a disposable clone of the local database on MySQL 8.0.44.

# Known Limitations To Disclose

- Current demo data mixes expired August Matilda/volunteer operations with current October Urinetown schedule data.
- Guardian Family Hub does not expose a distinct linked Student profile link even though Student profile/history surfaces exist elsewhere.
- Volunteer signup, cancellation and approval could not be exercised because there is no future fake shift.
- Staff/Admin demo accounts are over-privileged combined-role fixtures.
- Moderator and Safeguarding navigation is coupled to the Operations experience; dedicated-role navigation remains unproven.
- Native push has storage/API support but no proven APNs/FCM delivery adapter.
- No real inbox receipt, device push receipt, Bluehost cron log or backup restore was observed. An earlier 062-shaped schema was runtime-verified, but the revised migration has not been deployed and no migration execution transcript was captured.
- A page loading is not treated as end-to-end workflow proof.

## Notification delivery evidence

| Channel | Synchronous behavior | Scheduled/configuration requirement |
|---|---|---|
| In-app | Durable notifications, read timestamps, message/channel cursors and unread navigation counts work in browser. | No cron required for reads. Producers vary by workflow. |
| Email | Application queues durable outbound rows and records attempts/status. Production UI reports SMTP. | bin/process-email-queue.php drains delivery; bin/queue-email-reminders.php generates reminders. Six rows were QUEUED, none attempted. |
| Web Push | Subscription/preferences and queue/log paths exist. Expired 404/410 endpoints are retired. | bin/process-push-queue.php requires VAPID, OpenSSL and cURL. Failed items are not automatically retried; interrupted processing reclaim is incomplete. |
| Native Push | Mobile subscription data can be stored. | No proven APNs/FCM consumer. Not production-ready. |

# Production Configuration Checklist

- [ ] Complete web-shell incident response and credential rotation before real-family data.
- [ ] APP_ENV=production, APP_DEBUG=false, APP_URL=https://ctsmd.me, APP_TIMEZONE=America/New_York.
- [ ] Configure least-privilege DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD and DB_CHARSET=utf8mb4.
- [ ] Use PHP 8.3 with PDO MySQL, mbstring, fileinfo, OpenSSL and cURL.
- [ ] display_errors=Off, log_errors=On, and private logs outside the public document root.
- [ ] Verify HTTPS redirect, Secure/HttpOnly/SameSite cookies, strict sessions and proxy handling.
- [ ] Verify Apache deny rules for environment, Git, source, storage, database, docs, bin and executable uploads.
- [ ] Keep STORAGE_PATH outside the document root; align upload/post limits; verify MIME allowlist and permissions.
- [ ] Configure SMTP sender, host, port, encryption, username and password; verify SPF/DKIM/DMARC and real receipt.
- [ ] Configure PUSH_VAPID_PUBLIC_KEY, PUSH_VAPID_PRIVATE_KEY_B64 and PUSH_VAPID_SUBJECT.
- [ ] Install serialized Bluehost cron jobs for email queue, reminders and, only after gaps close, push.
- [ ] Monitor oldest queued age, failed/stuck rows, worker exits and actual receipt.
- [ ] Test encrypted database/file backup and restore with documented RPO/RTO and owners.
- [ ] Validate CSRF failures, rate limits and broader direct-object authorization. Core mobile bearer endpoints received focused fake-account coverage on October 21.

Recommended cron cadence, using verified absolute Bluehost paths:

- Every 5 minutes: process-email-queue.php 50
- Hourly: queue-email-reminders.php
- Every minute only after push closure: process-push-queue.php 25

# Persona / Permission Matrix

Y = confirmed positive runtime access. D = confirmed direct-url denial. C = conditional/own data. S = source evidence only. Combined roles reduce confidence for Staff/Moderator/Safeguarding boundaries.

| Area | Guardian | Student | Volunteer | Staff | Moderator | Safeguarding | Admin |
|---|---:|---:|---:|---:|---:|---:|---:|
| Home | Y | Y | Y | Y | Y | Y | Y |
| Family | Y | D after pending deploy | C | C | C | C | Y |
| Calendar | Y | Y | C | Y | C | C | Y |
| Community | Y | Y | Y | Y | Y | Y | Y |
| Messages | Y | Y | Y | Y | Y | Y | Y |
| Forms | Y | Y, no actionable form | C | Y | C | C | Y |
| Resources | Y | Y | Y | Y | C | C | Y |
| Theatre History | C | Y | C | C | C | C | Y |
| Volunteer | C | C | Y | Y | C | C | Y |
| Production member view | Y | Y | Y empty | Y | C | C | Y |
| Production people/day writes | D | D | D | Y combined | S | S | Y |
| People | D | D | D | Y combined | D | D | Y |
| Accounts | D | D | D | S | D | D | Y |
| Registrations | D | D | D | Y combined | D | D | Y |
| Moderation | D | D | D | Y combined | Y combined | D | Y |
| Safeguarding | D | D | D | S | D | Y combined | Y |
| Email operations | D | D | D | S | D | D | Y |
| Archives | C | retest | C | Y | C | C | Y |

Representative server-side denials were verified for Guardian, Student and Parent+Volunteer accounts at /admin/accounts, /people, /admin/registrations, /admin/channels, /admin/moderation/terms, /safeguarding, /admin/email, /production/people and /production/day. These returned Restricted/403-style responses rather than merely hiding navigation.

No critical web route remains known to rely only on hidden navigation after the Student household fix. Focused mobile testing confirmed denial for a fake user requesting an unrelated safeguarded thread; broader direct PHP and mobile IDOR coverage remains required.

# Demo Data Checklist

- [x] Administrator: Andrew/Maya fake accounts
- [x] Guardian: Jamie Carter
- [x] Student: Emma Carter with login
- [x] Volunteer: Alex Morgan with readiness, credentials and history
- [ ] Pure Production Staff account without Admin/Moderator/Safeguarding
- [x] Active production: Urinetown with current schedule
- [x] Linked Guardian/Student household
- [x] Guardian-completable form assignment
- [ ] Student-actionable form if the Board needs to demonstrate Student forms
- [ ] Future fake volunteer opportunity plus approval-gated example
- [x] Community channels/posts
- [x] Message conversations
- [x] Notifications
- [x] Published cast and Theatre History
- [x] Readiness checklist
- [x] Archived productions

001_demo.sql remains destructive and must never be imported into production. 003_board_demo_supplement.sql is staging-only and contains no password changes or destructive reset.

# Board Questions / Decisions

- Which production and exact cohort will enter the pilot, and who owns support and incident escalation?
- Will Students receive independent logins, or remain guardian-managed for the pilot?
- What historical conversation access should a removed guardian retain, and who can authorize an exception?
- Should Production Staff authority be organization-wide or assigned per production?
- Is Web Push optional for the pilot, with in-app and verified email as supported channels?
- What retention, consent, backup and breach-notification rules apply to Student and safeguarding records?
- Which outcomes should be reviewed after the pilot?

Existing data can answer future reporting questions without building a reporting suite:

- attendance and repeated absence patterns;
- volunteer coverage, eligibility and verified service hours;
- forms missing, completed, rejected or awaiting review;
- registration status and conversion to production participation;
- production checklist/readiness gaps;
- schedule audience participation, noting that a call is not proof of attendance;
- communication/moderation activity within defined privacy boundaries;
- training and credential expirations.

# Recommended Next Builds

1. **BLOCKER:** complete the host-side incident response, then deploy and runtime-verify the revised migration 062. The approved reactivation rule passed the expanded safeguarding suite on a disposable local MySQL clone; production remains unchanged by this revision.
2. **BEFORE BOARD:** deploy the contained Student permission/form fixes and create a repeatable current-date rehearsal fixture with a pure Staff account and future volunteer shift.
3. **BEFORE PILOT:** harden delivery operations with queue retry/recovery, serialized cron monitoring, real inbox/device proof, rate limiting and a tested backup restore.

## Validation Record

Static closure validation executed October 20, 2026 at audited head `f4c7e2b072cb653b03da565caa0e2259001fa181`, followed by two focused syntax repairs:

- changed-file PHP lint passed for the ten PHP files changed since `ce0f3c7d0825274165fe9547ec3a0a376dc63a07`;
- the first full-repository lint found literal `\\n` source corruption in `FamilyDashboardExperience.php`; fixed in commit `dbc971e`;
- the second full-repository lint found the same corruption in `PlatformOnboardingExperience.php`; fixed in commit `8a224f1`;
- the final full-repository lint passed every PHP file, including all three test scripts;
- `git diff --check` passed with no output;
- `tests/pilot_lifecycle.php` output: `PASS: 21 lifecycle checks (stubbed database; not SQL integration).`;
- `tests/pilot_push_claim.php` output: `PASS: losing push claim performs no delivery (stubbed database).`;
- local `tests/safeguarded_conversation.php` could not connect to MySQL, but the same deployed test later passed 17 checks against Bluehost MySQL with its transaction rolled back;
- migration 062 source inspection found no target-table 1093 self-reference: cutoff subqueries read `messages` while updating `conversation_participants`;
- migration 062 is a one-time migration, not idempotent: rerunning its unguarded `ALTER TABLE ... ADD COLUMN` will fail after first application;
- the earlier reactivation ambiguity is resolved by product decision: a revoked participant record remains historical permanently, and future communication uses a current-safe conversation. The revised implementation passed disposable-clone MySQL execution and remains undeployed.
- local MySQL 8.0.44 validation cloned the unmigrated `ctsmd` database into a disposable schema, applied revised migration 062, and passed 36 safeguarding checks with test data rolled back; the disposable schema was then removed;
- the expanded suite covers messages 1-5 before revocation, messages 6-10 during revocation, fixed cutoff preservation, old-thread send denial, new-thread participation, exact-set reuse, attachment and unread boundaries, push eligibility, account re-enable, and revoke/reactivate/revoke repetition;
- migration inspection found no target-table 1093 self-reference: participant updates read cutoff values only from `messages`; runtime relationship insertion/reactivation and account re-enable no longer widen old conversations; trigger names are dropped before creation, while the migration remains intentionally one-time because its column `ALTER TABLE` is not idempotent;
- MySQL validation also found and fixed an unrelated MySQL 8 `DISTINCT`/`ORDER BY` incompatibility in `eligibleGuardians()`; the family relationship uniqueness constraint makes `DISTINCT` unnecessary.

Bluehost emergency validation later on October 20, 2026:

- `https://ctsmd.me/login` returned HTTP 500 because the deployed copies of `FamilyDashboardExperience.php` and `PlatformOnboardingExperience.php` contained literal `\\n` tokens from the same source defects fixed in `dbc971e` and `8a224f1`;
- the two malformed tokens were replaced directly in `/home1/wentzmdc/public_html/ctsmd`; both files then passed `php -l`;
- a full deployed-folder PHP lint completed with `lint_exit=0 files=127 errors=0`;
- `https://ctsmd.me/login` was reloaded and rendered the CTSMD Connect login screen successfully;
- Bluehost deployment does not use Git; copied `.git` metadata is corrupt and was not used or modified as part of the repair.
- migration 062 columns and both synchronization triggers were present; the runtime schema guard passed;
- `tests/safeguarded_conversation.php` passed 17 real-database checks with the transaction rolled back;
- `tests/pilot_lifecycle.php` passed 21 stubbed lifecycle checks and `tests/pilot_push_claim.php` passed;
- deployed Student direct-route checks denied fake Student Emma access to `/family-hub` and `/family/manage`;
- clean anonymous timing probes completed without timeout: `/login` in 0.246 seconds, authenticated-entry redirects in 0.324-0.406 seconds, and one cold `/` request in 3.943 seconds;
- sensitive source, environment, log, bin, test, and documentation URL probes returned HTTP 403; `/health` returned only the expected minimal JSON.

Bluehost containment and mobile API validation on October 21, 2026:

- the confirmed 212-byte `accesson.php` web shell was moved unchanged outside `public_html` to `/home1/wentzmdc/ctsmd-incident-quarantine/20261021T013441Z/accesson.php` with an adjacent metadata manifest; its SHA-256 is `5dd77e8d7d97b876026800b045c80e448fdef19f5549068b65dd4e2178d52a65`;
- the original filesystem path is absent, the quarantine is mode `0700` with files mode `0600`, and the former public URL returns HTTP 403;
- no other PHP file was found under deployed `assets`, `storage`, or `uploads`; this does not close the account-wide incident;
- five one-hour disposable bearer tokens were created for fake active personas and an inactive/pending fake fixture;
- `/api/mobile/me`, `/messages`, `/message-thread`, `/channels`, and `/channel` returned expected data for active fake accounts; the inactive fixture received HTTP 401;
- safeguarded thread access succeeded for the fake Staff, Student, and Guardian participants; unrelated fake Volunteer access received HTTP 404; direct adult thread access succeeded for its fake adult participants;
- `/api/mobile/message-send` rejected an unauthorized safeguarded send and rejected an empty request with HTTP 400. No message or push subscription was created;
- all five tokens were revoked, a reused token then received HTTP 401, all five temporary token rows were deleted, and plaintext response/token files were removed;
- no deployed former-guardian row existed for a non-mutating endpoint check. Former-guardian and role-change semantics are covered by the rolled-back database suites, including the revised 36-check local MySQL run; no production relationship, role, cutoff, or reactivation state was altered.

Previously completed on the audit branch:

- PHP 8.3 lint passed for all 124 PHP files.
- tests/pilot_lifecycle.php passed 21 cases.
- tests/pilot_push_claim.php passed.
- git diff --check passed.
- Literal asset-reference and manifest inspection passed.
- Schema guard could not connect to a database: SQLSTATE[HY000] [2002] Connection refused.

Authenticated browser evidence completed October 20, 2026:

- four demo identities covering Guardian, Student, Parent+Volunteer, Administrator, Production Staff, Moderator and Safeguarding;
- desktop routes and representative workflow details exercised;
- 390 × 844 checks on /app, /family-hub, /calendar, /channels, /messages, /forms, /volunteer-readiness, /production and /admin/operations;
- no page-wide horizontal overflow found; horizontal section navigation remains scrollable;
- retained authenticated route timings generally 0.2 to 0.6 seconds;
- no genuine new-profile HAR was available;
- no production mutation or outbound communication was performed.

Security/safeguarding closure work added a shared policy, migration 062, web/mobile access filtering, push recipient revalidation, a real database integration test, and the host incident runbook. Full local PHP lint and `git diff --check` passed; lifecycle and push-claim tests passed locally and on Bluehost; an earlier schema guard and 17-check safeguarding suite passed against Bluehost MySQL. The revised reactivation implementation passed 36 checks after migration on a disposable local MySQL 8.0.44 clone. Focused deployed mobile checks passed without creating content. Pilot sign-off remains blocked by the open host incident, deployment/runtime verification of the revision, delivery operations, and the remaining least-privilege/negative test plan.
