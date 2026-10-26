# Executive Summary

Overall readiness:

- **Board demo: 92%**
- **Limited pilot: 86%** (latest owner-reported acceptance assessment, October 21; remaining evidence listed below).
- **Board recommendation: CONDITIONAL GO** after the repeatable staging fixture is loaded and rehearsed.
- **Pilot recommendation: NO-GO** until OS-presented Web Push and notification-click deep linking and the remaining mobile/keyboard checks are closed. Isolated email delivery/cron execution and private-file recovery are technically accepted. Normal email cron activation and production backup ownership, retention, RPO and RTO still require explicit operational decisions. The guardian-reactivation safeguard is deployed and runtime-verified.

Authenticated browser testing was completed on October 20, 2026 against https://ctsmd.me using fake demo accounts. The exercised personas were:

- Andrew Wentz: Administrator + Production Staff + Moderator + Safeguarding + Member
- Jamie Carter: Guardian/Parent
- Emma Carter: Student
- Alex Morgan: Parent + Volunteer

Those October 20 browser fixtures used combined roles. The October 21 disposable-environment matrix below separately proves least-privilege boundaries with pure fake personas. No real-user mutation or outbound communication was performed. Opening existing conversations and channels updated read cursors for the Guardian and Student accounts.

The original clean-browser ERR_TIMED_OUT root cause is **not established**. It was not reproduced in the connected authenticated browser. Retained-session navigation was generally 0.2 to 0.6 seconds. Earlier clean anonymous testing did record three requests that exceeded 20 seconds before successful retry. Runtime schema work remains a plausible latency contributor, but evidence is insufficient to call it the cause or solved.

## October 21, 2026: Web Push acceptance and latest readiness (owner-reported)

**Latest decision baseline:** Board preview **92% CONDITIONAL GO**; limited pilot **86% NO-GO**. These are readiness assessments, not calculated test coverage. This subsection supersedes older percentages as the current pilot decision and preserves earlier sections as chronological evidence.

Owner reports completing isolated Web Push transport acceptance and cleanup:

- Disposable fake push request returned **FCM HTTP 201** (provider accepted request). The application's delivery record was **sent** and `last_success_at` was recorded. **This does not prove OS notification display or user navigation on click.**
- All **47 existing production queue entries** were left untouched.
- Fake test account was disabled; fake Web Push subscriptions, push queue/delivery rows, mobile tokens and native push subscriptions were deleted. Cleanup query returned zero remaining fake records.
- The administrator's unintentionally revoked browser subscription was restored and verified active.
- Community loaded successfully after testing. The previously observed `pg.id` error was historical, not an active runtime failure.
- Owner reports `git diff --check` passed, relevant PHP lint passed, and **no commit was created** for the local acceptance work.
- This evidence was conveyed from the local working copy. GitHub cannot independently corroborate the local evidence artifacts until their contents are synchronized; do not claim OS delivery or pilot approval.

### Remaining mandatory pilot acceptance gates

1. **Web Push user experience:** On supported devices, capture visible OS notification receipt, foreground/background behavior, notification-click deep link to the right authorized destination, and unsubscribe/permission-revocation behavior. Verify delivery-time authorization and preference controls remain effective. Provider acceptance alone is insufficient.
2. **Mobile/keyboard acceptance:** Exercise keyboard-only navigation, modal focus containment/return, long tables, small-screen wrapping, form completion, and essential Guardian/Student/Volunteer/Staff interactions. Record device/browser versions and evidence of defects and repairs.
3. **Operational launch gate:** Define incident owner/escalation, production secrets/credential rotation status, safe backup ownership/retention/RPO/RTO, queue monitoring thresholds, and a supported pilot cohort. Decide how to disposition the seven pre-existing live email rows before enabling normal queue/reminder cron. A web-first pilot may explicitly exclude native push; native APNs/FCM delivery is **not proven** and must not be marketed as ready.

### Next execution order and stop conditions

- **First:** OS Web Push display and deep-link acceptance, with real-device evidence.
- **Second:** device, keyboard and long-table acceptance; resolve release-blocking defects only.
- **Finally:** one evidence review, owner sign-off, and updated readiness assessment. **No pilot GO without each in-scope release gate being closed with repeatable evidence.**

**Scope discipline:** Security, safeguarding, delivery operations and release acceptance only. No feature additions, reporting, or discretionary UI overhaul. Preserve historical/audit records and production queues. Every test account or artifact must have a documented cleanup and post-cleanup verification. Never print tokens, credentials, private content or real family data into public logs or this document.

---

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

- **CLOSED October 21, CTSMD scope:** the unsafe legacy PHP artifact was quarantined outside `public_html`; its original path remains absent and its public URL returns 403. A CTSMD-only sweep found no similar PHP artifact and confirmed PHP execution is denied under public assets. No CTSMD dependency was identified and no independent evidence currently establishes CTSMD compromise. Sibling-project cleanup is deferred, out of scope, and is not a CTSMD blocker.
- **CLOSED October 21:** the approved V1 guardian-revocation rule is deployed and runtime-verified on Bluehost. The existing 062 columns were preserved, only the revised trigger section was applied, the obsolete insert trigger was removed, both synchronization triggers matched the revised no-widening behavior, and the runtime schema guard passed.
- Web and mobile sends invoke the same transaction-aware safeguarding policy. The deployed 36-check database suite passed with its transaction rolled back, covering role changes, disabled accounts, all-guardian participation, Student/Student prohibition, fixed historical cutoffs, attachment/unread boundaries, push eligibility, reactivation into a new safe thread, and repeated revocation.
- Disposable fake-account bearer-token tests confirmed the old thread returned only the five messages through its cutoff, later content stayed hidden, old-thread sends were rejected, a new safe thread could include the reactivated guardian, and the disabled token received HTTP 401. All four fake users, both test conversations, token rows, state files, and response files were removed; an independent query returned zero remaining `@safeguarding.test` users.
- **CLOSED October 21:** an explicitly authorized Gmail inbox received one harmless acceptance email. The isolated production row was accepted by SMTP on its first attempt, recorded one delivery-log entry, and was not resent by a repeat invocation. A temporary serialized cron scoped only to that already-sent row ran twice with zero work and was removed. Rows `id` 1-7 retained their exact before-and-after fingerprint and remain pending; normal cron stays commented until an administrator decides how to handle them.
- **PARTIAL October 21:** push retry/reclaim/dedupe and delivery-time audience/preference rechecks are deployed and regression-tested. A disposable browser subscription accepted an isolated remote push through FCM with HTTP 201 and recorded `last_success_at`; observable OS presentation and notification-click deep-link behavior remain to be proven on a supported device.
- **PARTIAL October 21, mobile/keyboard:** an authenticated production sweep at 390 x 844 covered 16 routes across home, production, schedule, Production Day, readiness, groups, people, casting, notices, operations, accounts, email, messages, forms and volunteer readiness. Every route retained a 390-pixel document width; production links extending beyond the viewport remained inside the intentionally scrollable section navigation. The shared mobile drawer was found to lack focus entry, Tab containment, Escape handling state and focus return. Commit `fa14524396309f779bd3c79cfe4a8befdd31747d` adds those controls and `aria-expanded` state, but deployment/runtime verification and dedicated Guardian, Student and Volunteer keyboard passes remain open.
- **CLOSED October 21:** dedicated Staff, Moderator, Safeguarding, Guardian, Student, Volunteer, and Administrator fixtures passed the sensitive-route least-privilege matrix. A navigation defect for pure Moderator/Safeguarding accounts was fixed without broadening route authorization.
- **CLOSED October 21:** nine CSRF mutation categories left the disposable database fingerprint unchanged; forged-ID POSTs from a Guardian returned 403 on nine sensitive routes. The matched database backup restored and booted on MySQL 8.0.44 in one second. Private-file restore remains unproven because the matched backup contained zero stored-file records and zero private binaries.
- **CLOSED October 21, technical recovery:** a harmless 61-byte text attachment and its database metadata were captured as matched logical-database and private-storage snapshots, then restored into a second disposable MySQL schema and private path. The source, restored file and database metadata shared SHA-256 `df6aebcf962e7d20f81ad16864d8e6dc69024b712b15c839b94576566102894e`; byte size matched; Student and current Guardian attachment access passed; an unrelated adult was denied. Restore execution took six seconds on the local target. Both schemas and the temporary storage tree created by this run were removed and verified absent. Production backup ownership, retention and accepted RPO/RTO remain policy decisions.
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

Bluehost already contained the 062 columns and an older trigger revision, so the non-idempotent column migration was not rerun. After a current compressed database backup was verified, only the revised trigger section was applied. The obsolete insert trigger is absent, the relationship and account synchronization triggers preserve historical cutoffs, the runtime schema guard passed, and the expanded safeguarding suite passed 36 rolled-back checks against the deployed MySQL database.

# Known Limitations To Disclose

- Current demo data mixes expired August Matilda/volunteer operations with current October Urinetown schedule data.
- Guardian Family Hub does not expose a distinct linked Student profile link even though Student profile/history surfaces exist elsewhere.
- Volunteer signup, cancellation and approval could not be exercised because there is no future fake shift.
- Staff/Admin demo accounts are over-privileged combined-role fixtures.
- Moderator and Safeguarding navigation is coupled to the Operations experience; dedicated-role navigation remains unproven.
- Native push subscription storage now exists in the deployed schema, but no APNs/FCM delivery adapter was found or proven.
- No real inbox receipt, observable device push presentation, notification-click deep link, or Bluehost cron log was observed. FCM accepted one isolated fake-account Web Push with HTTP 201, and safeguarding push audience exclusion was verified through the deployed policy/database suite.
- A page loading is not treated as end-to-end workflow proof.

## Notification delivery evidence

| Channel | Synchronous behavior | Scheduled/configuration requirement |
|---|---|---|
| In-app | Durable notifications, read timestamps, message/channel cursors and unread navigation counts work in browser. | No cron required for reads. Producers vary by workflow. |
| Email | Application queues durable outbound rows and records attempts/status. One isolated production acceptance row was sent once through SMTP and confirmed received in Gmail. | bin/process-email-queue.php drains delivery; bin/queue-email-reminders.php generates reminders. Seven pre-existing due rows remain untouched and normal cron remains commented pending administrator disposition. |
| Web Push | Subscription/preferences and queue/log paths exist. Expired 404/410 endpoints are retired. A disposable fake-account delivery was accepted by FCM with HTTP 201 and recorded as sent. | Retry, stale processing reclaim, event deduplication and delivery-time account/preference/safeguarding revalidation are deployed and regression-tested. OS presentation and notification-click deep linking remain unproven. |
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
- [x] Pure Production Staff account without Admin/Moderator/Safeguarding
- [x] Active production: Urinetown with current schedule
- [x] Linked Guardian/Student household
- [x] Guardian-completable form assignment
- [x] Student-actionable form assigned through the fake Guardian/Student household
- [x] Future fake volunteer opportunity plus approval-gated example
- [x] Community channels/posts
- [x] Message conversations
- [x] Notifications
- [x] Published cast and Theatre History
- [x] Readiness checklist
- [x] Archived productions

001_demo.sql remains destructive and must never be imported into production. 003_board_demo_supplement.sql is staging-only, repeatable, uses relative dates, and contains no password changes or broad reset. It creates pure fake Guardian, Student, Volunteer, Production Staff, Moderator, Safeguarding, and Administrator personas plus the current demo schedule, shifts, form, published cast/playbill, readiness item, and notification.

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

1. **BLOCKER:** complete the host-side incident response. The revised migration 062 trigger behavior and guardian reactivation rule are deployed and runtime-verified; this does not establish that the Bluehost account is clean.
2. **BEFORE BOARD:** deploy the contained Student permission/form fixes and create a repeatable current-date rehearsal fixture with a pure Staff account and future volunteer shift.
3. **BEFORE PILOT:** harden delivery operations with queue retry/recovery, serialized cron monitoring, real inbox/device proof, rate limiting and a tested backup restore.

## October 21 CTSMD closure evidence

### Email worker and cron

The exact Bluehost queue and reminder commands are documented in `docs/EMAIL_DELIVERY.md` and installed as commented crontab entries. Lock and log directories exist outside `public_html` with mode `0700`. They remain disabled because production has six queued non-test recipients (`fake=0`); no cron log was created and the queue remained at six, proving no accidental run occurred.

On a disposable database containing only fake mail test data:

- a stale `sending` row older than ten minutes was reclaimed, claimed once, delivered through the log transport, marked `sent`, and given a `sent` delivery-log row;
- the mail log grew by 190 bytes;
- incomplete SMTP configuration produced three failed attempts, three delivery-log failures, and terminal queue state `failed`;
- no outbound SMTP message was sent.

Real SMTP acceptance, inbox receipt, duplicate-send observation, and cron enablement remain blocked pending an approved recipient.

### Least-privilege and negative matrix

Pure fake personas were tested through authenticated local HTTP against the restored MySQL copy. Expected positive access returned 200 and every listed negative returned 403:

| Persona | Positive sensitive routes | Denied sensitive routes |
|---|---|---|
| Production Staff | people, registrations, channels, moderation, production/people, volunteer shifts | accounts, safeguarding, email |
| Moderator | moderation | accounts, people, registrations, channels, safeguarding, email, production/people, volunteer shifts |
| Safeguarding | safeguarding | accounts, people, registrations, channels, moderation, email, production/people, volunteer shifts |
| Guardian | production member view | all nine management routes |
| Student | production member view | all nine management routes |
| Volunteer | production workspace with no selected membership data | all nine management routes |
| Administrator | all listed sensitive routes | none |

Pure Moderator and Safeguarding navigation now exposes Operations and only their authorized destination. Nine invalid-CSRF attempts covered account, role, family relationship, production membership, schedule, form review, volunteer approval, moderation, and safeguarding mutations; the before/after database fingerprint was identical. A Guardian's forged-ID POSTs to accounts, people, registrations, channels, moderation, safeguarding, email, production people, and volunteer shifts all returned 403.

### Restore rehearsal

- owner: CTSMD technical owner;
- backup: `/home1/wentzmdc/ctsmd-backups/20261021-061901` (database and code captured October 21, 2026);
- local isolated target: MySQL 8.0.44 plus `/tmp/ctsmd-restore-rehearsal-20261021-2/app`;
- procedure: verify hashes, create a new disposable schema, import the gzip SQL dump, extract the code archive to a new isolated directory, replace `.env` with local-only settings, point `STORAGE_PATH` at the isolated tree, boot PHP, and probe representative routes/records;
- result: 84 base tables, 25 triggers, 16 users, three family relationships, seven productions, 29 production memberships, six forms, and nine assignments restored; root/login returned 200 and protected family, production, form, and resource routes required authentication;
- actual database/code restore duration: one second on the local target;
- private-file limitation: zero `stored_files` rows and zero private binaries existed in the matched backup, so resource/private-file content recovery was not demonstrated;
- RPO: latest verified manual pre-deployment backup; RTO evidence: one-second data/code extraction plus configuration and verification, but no production RTO is accepted until private-file recovery and a hosted rehearsal pass;
- retention: not yet formally approved; do not delete the matched Bluehost backup until the owner sets policy.

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
- the earlier reactivation ambiguity is resolved by product decision: a revoked participant record remains historical permanently, and future communication uses a current-safe conversation. The revised implementation passed disposable-clone MySQL execution and was subsequently deployed and runtime-verified on Bluehost.
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
- no other PHP file was found under deployed CTSMD `assets`, `public`, or `storage`; CTSMD cleanup is closed and sibling scope is deferred;
- five one-hour disposable bearer tokens were created for fake active personas and an inactive/pending fake fixture;
- `/api/mobile/me`, `/messages`, `/message-thread`, `/channels`, and `/channel` returned expected data for active fake accounts; the inactive fixture received HTTP 401;
- safeguarded thread access succeeded for the fake Staff, Student, and Guardian participants; unrelated fake Volunteer access received HTTP 404; direct adult thread access succeeded for its fake adult participants;
- `/api/mobile/message-send` rejected an unauthorized safeguarded send and rejected an empty request with HTTP 400. No message or push subscription was created;
- all five tokens were revoked, a reused token then received HTTP 401, all five temporary token rows were deleted, and plaintext response/token files were removed;
- no deployed former-guardian row existed for a non-mutating endpoint check. Former-guardian and role-change semantics are covered by the rolled-back database suites, including the revised 36-check local MySQL run; no production relationship, role, cutoff, or reactivation state was altered.

Bluehost migration 062 and guardian-reactivation verification on October 21, 2026:

- focused source commit `921a03d242d86a6a205f7dac292f522ef939d780` records the permanent guardian-revocation boundary implementation;
- a verified pre-change backup was written outside the document root at `/home1/wentzmdc/ctsmd-db-backups/ctsmd-pre-062-trigger-upgrade-20261020-195122.sql.gz` (45,802 bytes, mode `0600`, SHA-256 `74361217e794a667707599438b07329f35b84f175b4a38d351e3760c95b00b50`);
- because both 062 columns already existed, the non-idempotent migration was not rerun; only its revised trigger section was applied;
- `trg_family_conversation_guardian_insert` is absent, while `trg_family_conversation_guardian_update` and `trg_user_disable_operational_cleanup` are present and do not clear or widen historical cutoffs;
- the deployed runtime schema guard passed, all 126 deployed PHP files linted cleanly, lifecycle passed 21 checks, push-claim passed, and safeguarding passed 36 real-database checks with rollback;
- fake Guardian A could read the old thread only through message 44, could not see five later messages or send to the historical thread, and retained the same cutoff after reactivation and a second revocation; Guardian B remained active;
- the reactivated fake guardian could participate in and send fake content to a new safe thread; mobile list/thread/send behavior agreed with the shared web policy;
- attachment, unread and push-audience boundaries, role-change re-evaluation, disabled Guardian/Student denial, and Student/Student prohibition passed in the deployed 36-check suite;
- the disabled bearer token returned HTTP 401. Cleanup removed four fake users, two fake conversations and their token rows; an independent database count returned zero remaining `@safeguarding.test` users, and temporary state/response files were removed;
- no temporary native push subscription was created or required for safeguarding cleanup. The `mobile_push_subscriptions` table was subsequently deployed during operational hardening, but native delivery remains outside this build and is still a pilot delivery blocker.

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

Security/safeguarding closure work added a shared policy, migration 062, web/mobile access filtering, push recipient revalidation, a real database integration test, and the host incident runbook. Full local and deployed PHP lint passed; lifecycle and push-claim tests passed locally and on Bluehost; the revised reactivation implementation passed 36 checks on both a disposable MySQL 8.0.44 clone and the deployed Bluehost database. Focused deployed mobile tests confirmed cutoff enforcement, historical-thread send denial, new-safe-thread operation, disabled-token denial, and complete fake-data cleanup. The code-level safeguarding runtime blocker is closed. Pilot sign-off remains blocked by observable Web Push presentation/deep-link acceptance, remaining device/mobile usability acceptance, and unresolved operational ownership decisions.

### October 21 operational hardening evidence

Commit `601383e53b8d2bad1480ec3af1f968c23fa2d11f` adds durable database-backed authentication throttling for web/mobile login, forgot/reset, join and email verification, plus push retry, stale-claim reclaim, event deduplication and delivery-time recipient/preference revalidation. Migrations 063 and 064 and the existing mobile push-subscription migration were applied after a verified code/database backup. All 12 deployed file hashes matched reviewed source. Deployed validation passed 139-file PHP lint, runtime schema guard, the 21-check lifecycle test, push-claim test, push-recovery test and rate-limit test; root and login returned HTTP 200.

One active mobile bearer token was revoked and zero remain. Exact email/reminder cron commands are installed as comments and intentionally disabled. A read-only preflight found seven due queued rows (`id` 1-7), no recognized test recipient, no delivery history, no stale claim, and complete SMTP/TLS configuration. Commit `db76cb6dda9b33b2f9ae56f455207e373ec3e94f` added an optional queue-ID scope without changing the normal cron interface. Deployed source hashes matched the reviewed files and both PHP files linted cleanly. An explicitly authorized Gmail inbox then received isolated row `id` 8; SMTP and the delivery log recorded one successful attempt, a repeat invocation processed zero rows, and the recipient confirmed receipt. A temporary serialized cron scoped only to the already-sent row ran twice with zero work and was removed. The seven pre-existing rows retained their exact SHA-256 fingerprint and normal cron remains commented pending administrator disposition.

Web Push acceptance used disposable account `push.acceptance@example.test` only. The browser granted permission, registered an active FCM subscription, and the deployed encryption/delivery implementation sent isolated queue item 248 without claiming any of the 47 pre-existing queued rows. FCM returned HTTP 201, the delivery log recorded `sent`, and the subscription recorded `last_success_at`. The browser's service-worker diagnostic separately confirmed that Chrome could retain an active notification, but the remote notification's macOS banner and notification-click deep link could not be independently observed. During cleanup, a reopened automation tab used the Administrator browser profile rather than the disposable session and revoked that profile's browser subscription; it was immediately resubscribed and the UI again showed permission granted, active subscription, active service worker, and `fcm.googleapis.com`. The disposable account was disabled and its Web Push subscriptions, queue/delivery rows, native push subscriptions, and mobile bearer tokens were removed; all four disposable credential/queue counts returned zero. The original browser-side disposable subscription could not be revisited after its tab closed, but server-side removal prevents further CTSMD delivery to it.

Board readiness is **92%** and limited-pilot readiness is **86% NO-GO**: CTSMD shell cleanup, matched database/private-file restore, negative matrix, pure-persona matrix, repeatable demo fixture, isolated production email/cron acceptance, and remote FCM acceptance are closed. Observable Web Push presentation/deep-link acceptance and remaining mobile/keyboard acceptance stay open. Normal email cron activation awaits administrator disposition of seven pre-existing live rows; production backup ownership, retention and accepted RPO/RTO also remain undecided.

The latest mobile source fix is not counted as deployed acceptance. The cPanel uploader could not read local files because the connected Chrome extension lacks file-URL access, and the local browser database is missing migrations 062-067 plus the mobile token tables. Production deployment and a repeat keyboard pass are therefore required before closing the mobile gate.

### Playbill publication source status

The Playbill management and public digital-publication source is implemented on the audit branch with migration 068. It adds reusable Student profile media, production-specific approved cast snapshots, sponsor tiers/artwork/placement, edition snapshots, explicit publish/archive/unpublish state, stable unauthenticated public URLs, policy-checked public assets, SVG QR downloads, printable QR signage, responsive section navigation, sharing metadata, and configured-only audience actions. Public pages default to `noindex` pending an explicit Student-biography indexing policy.

This work is not deployed and does not change the Board or limited-pilot percentages. Migration 068, fresh-session public-route acceptance, consent withdrawal checks, QR/print checks, and production upload-limit verification remain required before this feature is accepted for production use.

## October 21 mobile integration regression coverage — source only

The Floot native app now has authenticated adapters for form detail/submission and volunteer signup, coordinator approval requests/withdrawal, and pre-start signup cancellation. The CTSMD backend branch uses the same guarded web service methods for these operations. CTSMD's volunteer cancellation was tightened to lock the shift/signup and reject after-start, checked-in, completed, nonexistent and wrong-user cancellations.

- Added `tests/pilot_volunteer_cancellation.php`, an isolated PDO-double test covering permitted cancellation, started shifts, checked-in/completed states, wrong user, absent signup/shift, invalid ID, transaction commit and rollback. **Written but not executed against the PHP repository in this session.**
- Floot mock-based form, signup, approval and cancellation tests passed in its test environment in preceding build sessions. Those tests do **not** establish live CTSMD deployment, real database triggers, workflow completion or device behavior.
- Mandatory next steps: run the PHP isolated test, PHP lint, and a migrated staging transaction-backed acceptance suite; verify form audit/review paths, signup capacity/eligibility/race behavior, approval workflow and cancellation rollback; then separately complete authenticated end-to-end app tests on iOS/Android.
- `src/MobileApi.php`, `src/FormExperience.php`, `src/VolunteerExperience.php` (and any related changed backend files) must be deployed as a coherent tested release before exercising the new mobile APIs. Production records and notification queues must remain untouched in test runs.

**Pilot remains NO-GO.** This coverage does not change the existing OS push, keyboard/device or operational closure gates.

## October 25 late pickup workflow — source only

Migration 084 and the associated source add auditable, production-scoped late pickup recording and staff review. The authoritative release is the schedule item's `ends_at`; arrival time and attendance Late status remain separate. Production settings control the occurrence threshold and grace period. Threshold crossing creates an internal review and versioned guardian notice draft only. Approval and explicit send are separate actions, current guardians are resolved at send time, and the mail worker rechecks the active relationship immediately before delivery.

Approved family-visible notices are returned through the existing mobile Action Center without internal notes or occurrence history. Full staff editing remains web-first. No fee or financial charge is assessed; template language treats repercussions as dependent on actual CTSMD policy.

All PHP files linted cleanly and `tests/late_pickup_workflow.php` passed 12 source-contract checks. Migration 084 and transactional MySQL acceptance were not run because the local database is unavailable. The wider suite retains an unrelated volunteer cancellation rollback failure and unavailable safeguarding database test. Expo typecheck retains three unrelated existing errors in `app/theatre/[id].tsx`, `ProductionHero.tsx`, and `StageBackdrop.tsx`. This source work is not deployed and does not change readiness percentages.

## October 25 manual production participants — source only

Migration 069 and the associated source add no-login cast, crew, creative, and other production participants without creating `users`, roles, family relationships, memberships, bearer tokens, or notification recipients. Reusable person profiles are separated from production credits, private notes are excluded from published queries, and name/biography/headshot each have explicit publication approval boundaries. Approved data now participates in casting snapshots shared by web/mobile, production directories, and Playbill company pages. Staff may selectively transfer approved fields to an existing active Student; the manual credit is retired but retained for audit.

Local PHP lint, the manual identity-boundary test, Playbill publication tests, and pagination tests pass. Migration 069 and transactional MySQL acceptance have not been run against a deployed or disposable database. This source work does not change Board or pilot percentages and does not close any existing operational blocker.

