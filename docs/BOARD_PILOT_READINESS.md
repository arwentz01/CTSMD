# Executive Summary

Overall readiness:

- **Board demo: 74%**
- **Limited pilot: 42%**
- **Board recommendation: CONDITIONAL GO** after the BEFORE BOARD items below are closed and rehearsed.
- **Pilot recommendation: NO-GO** until host-side incident response is complete and the two repository safeguarding remediations pass executable database-backed validation.

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
| BEFORE BOARD | Deploy and runtime-verify the Student household authorization fix. Student access to /family-hub and /family/manage was a confirmed server-side permission leak. | Repository fix added on audit branch; production deployment and Student negative test still required. |
| BEFORE BOARD | Deploy and verify the dashboard form-count fix. Emma's home advertised a Guardian-completed form that her /forms page correctly did not expose. | Repository query aligned to the actionable assignee; deployment verification required. |
| BEFORE BOARD | Rehearse one current production, not Matilda's expired August data. | Urinetown has an Oct. 21 rehearsal and a working attendance path. Select Urinetown before the Board demo. |
| BEFORE BOARD | Add one future fake volunteer shift with eligibility and approval examples. | Alex has complete readiness/history but no live opportunity, so signup/cancel/approval remains DATA/SEED ISSUE. |
| BEFORE BOARD | Prepare a pure Production Staff account. | Current staff evidence comes from Administrator/Moderator/Safeguarding combined accounts. |
| BEFORE BOARD | Repeat /archive with the Student after deployment. | /archive failed once during Emma's session but works for Administrator and the handler/service exist. Treat as unconfirmed persona-specific runtime failure until retested. |
| BEFORE BOARD | Perform a true clean-profile startup capture. | Capture first navigation, redirects, timing and HAR/server logs without retained cookies. Do not claim the timeout fixed. |
| POLISH | Improve small mobile tap targets and visibly style the desktop Sign out button. | No page-wide mobile overflow was found at 390 × 844. |

# Must Fix Before Pilot

- **BLOCKER:** complete hosting incident response for the confirmed PHP web shell formerly found at assets/images/accesson.php. Follow [HOST_INCIDENT_RESPONSE.md](HOST_INCIDENT_RESPONSE.md): preserve evidence, rotate credentials, invalidate sessions/tokens, inspect sibling sites/backups/logs, and clean-deploy reviewed source. Repository inspection cannot establish that Bluehost is clean.
- **BLOCKER pending executable validation:** migration 062 and the shared safeguarding policy now revoke a former guardian from future messages while retaining history through a fixed message ID. The database-backed regression test has been added but could not be executed because no local runner or CI workflow was available.
- **BLOCKER policy decision required:** migration 062 currently clears `access_revoked_at` and `history_through_message_id` when a guardian relationship is reactivated or a disabled guardian account is re-enabled. That would expose messages sent during the revoked period. The intended reactivation history policy is not defined, so deployment validation stopped rather than inventing access semantics.
- **BLOCKER pending executable validation:** web and mobile message sends now invoke the same transaction-aware safeguarding policy. Role changes, disabled accounts, all-guardian coverage, and Student/Student prohibition are covered by the new database test, but deployment and execution remain required.
- **BEFORE PILOT:** prove email cron processing and real inbox receipt; six messages are currently queued with no attempts.
- **BEFORE PILOT:** close push retry/reclaim/dedupe and audience recheck gaps, then prove receipt on supported devices.
- **BEFORE PILOT:** test dedicated Moderator-only, Safeguarding-only, Staff-only, Volunteer-only, and least-privilege negative routes.
- **BEFORE PILOT:** run database-backed lifecycle tests, restore rehearsal, rate-limit validation, CSRF negative tests, and direct-object authorization tests.
- **BEFORE PILOT:** complete keyboard focus containment/return and long-table testing on mobile.

# Demo-Ready Workflows

| Persona / workflow | Classification | Authenticated evidence |
|---|---|---|
| Guardian: login, home, Family Hub, linked child, family calendar, assigned form, notifications | READY WITH POLISH ISSUE | Jamie showed Emma, two active productions, an assigned Seussical form, calendar, resources, messages and notifications. Form rendering was opened without submission. No distinct linked-child profile link is presented from Family Hub. |
| Student: home, My Theatre, calendar, cast, Theatre History, resources, Community, messages | READY WITH POLISH ISSUE | Emma showed published cast, two verified credits, acting résumé, schedule, attendance, Community and safeguarded messages. Household permission and misleading form count are fixed on the audit branch but not yet deployed. |
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

These are source and migration findings only until migration 062 and `tests/safeguarded_conversation.php` run successfully against MySQL.

# Known Limitations To Disclose

- Current demo data mixes expired August Matilda/volunteer operations with current October Urinetown schedule data.
- Guardian Family Hub does not expose a distinct linked Student profile link even though Student profile/history surfaces exist elsewhere.
- Volunteer signup, cancellation and approval could not be exercised because there is no future fake shift.
- Staff/Admin demo accounts are over-privileged combined-role fixtures.
- Moderator and Safeguarding navigation is coupled to the Operations experience; dedicated-role navigation remains unproven.
- Native push has storage/API support but no proven APNs/FCM delivery adapter.
- No real inbox receipt, device push receipt, Bluehost cron log, backup restore or database migration run was observed.
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
- [ ] Validate CSRF failures, rate limits, unauthorized POSTs, direct IDs and mobile bearer endpoints.

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

No critical web route remains known to rely only on hidden navigation after the Student household fix. Direct PHP endpoints and the mobile API still need independent IDOR tests.

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

1. **BLOCKER:** complete the host-side incident response, then deploy migration 062 and execute the safeguarding regression suite. The former-guardian and mobile-parity fixes remain blocked from sign-off only by executable validation.
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
- `tests/safeguarded_conversation.php` did not execute its assertions: `SQLSTATE[HY000] [2002] Connection refused` from `Database::connect()`;
- migration 062 source inspection found no target-table 1093 self-reference: cutoff subqueries read `messages` while updating `conversation_participants`;
- migration 062 is a one-time migration, not idempotent: rerunning its unguarded `ALTER TABLE ... ADD COLUMN` will fail after first application;
- runtime migration, schema/trigger, former-guardian, mobile parity, Student regression, containment, and timeout validation were not executed in this pass;
- validation stopped because reactivation currently clears the fixed history cutoff, while the intended visibility of messages sent during revocation is unresolved.

Bluehost emergency validation later on October 20, 2026:

- `https://ctsmd.me/login` returned HTTP 500 because the deployed copies of `FamilyDashboardExperience.php` and `PlatformOnboardingExperience.php` contained literal `\\n` tokens from the same source defects fixed in `dbc971e` and `8a224f1`;
- the two malformed tokens were replaced directly in `/home1/wentzmdc/public_html/ctsmd`; both files then passed `php -l`;
- a full deployed-folder PHP lint completed with `lint_exit=0 files=127 errors=0`;
- `https://ctsmd.me/login` was reloaded and rendered the CTSMD Connect login screen successfully;
- Bluehost deployment does not use Git; copied `.git` metadata is corrupt and was not used or modified as part of the repair.

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

Security/safeguarding closure work added a shared policy, migration 062, web/mobile access filtering, push recipient revalidation, a real database integration test, and the host incident runbook. The local execution environment remained unavailable and the branch has no GitHub Actions workflow run, so PHP lint, the repository test suite, the new safeguarding test, schema/migration execution, and git diff --check have **not been run against this closure build**. The prior audit results above do not validate these new commits. Pilot sign-off must remain blocked until those commands pass against MySQL and the migration is runtime-verified.
