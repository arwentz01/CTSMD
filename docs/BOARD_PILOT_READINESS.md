# Executive Summary

Overall readiness: **30% for a limited real-user pilot; 55% for a controlled Board demo.**

Audit date: 2026-10-20. Baseline: `f6580e3` on `main`. These are conservative engineering estimates of closure/verification, not test pass rates. **NO-GO for real families** until the security incident and safeguarding gaps below are closed. A fake-data staging demo is the appropriate next gate. No production writes or deliveries were performed.

Evidence levels: **static** means source inspected, not exercised with a database; **HTTP** means anonymous deployed response tested; **browser** means visible deployed page checked. No authenticated production session or Bluehost console was available. Repository code and deployed code may differ. Route classifications describe the evidence available, not blanket acceptance.

## Fresh-browser investigation

**Root cause: not established. Do not represent the timeout as fixed.**

- Independent cookie-free HTTPS GETs: `/` 200/no redirect (3.42s), `/login` 200/no redirect (3.27s), `/app` one redirect to `/login?return_to=%2Fapp` (3.56s), `/forms/view?id=1` one redirect preserving path/query (3.71s), `/production/day` one login redirect (3.36s). These totals include the audit network/proxy. Initial login HEAD had 5.62s upstream service time. No browser `ERR_TIMED_OUT` reproduced in this first pass. A subsequent anonymous HTTP batch timed out without response bytes on `/family-hub`, `/calendar`, and `/channels` at 20 seconds; subsequent routes returned normal redirects. A sequential cookie-free Family retry returned 303 in 2.70s. This confirms intermittent audit-path timeouts, not their hosting/browser root cause.
- Browser: login rendered; app and form deep link landed on login with correct return path; `/` rendered the Connect homepage. This was an unauthenticated cloud session, not a proven brand-new user-created browser profile. Clean-profile equivalence remains unverified.
- `/my-theatre` had two redirects ending at `/login` without a return path. Physical directory bypassed `front.php`. Fixed by routing through front controller and registering its experience there. Root and subdirectory wrappers share the existing production-wrapper pattern.
- `front.php` starts a session and runs `RuntimeSchemaGuard` **before** public/auth rendering and login redirection. First uncached request performs numerous `SHOW COLUMNS`, table and trigger/permission queries. Session success persists; optional APCu caches success for 300 seconds. No automatic migration/DDL is run there. This is an evidenced cold-request cost, not proof of timeout causation.
- `APP_BASE_PATH` is overwritten from `SCRIPT_NAME`; `.env` is loaded only on database connection. `.htaccess` still has MAMP-specific `FallbackResource /ctsmd/front.php`; with rewrite unavailable this is wrong for root-host deployment. Do not change Bluehost rules without validating its actual Apache configuration.
- Manifest uses relative `./app` and `./` scope. Worker has **no fetch handler**, navigation cache, or startup redirect. Worker registration is asynchronous on member/mobile pages; login/public pages do not register it. JS has mobile agenda/volunteer redirects, not a homepage fallback; no synchronous XHR found.
- To close: capture a truly fresh-profile HAR including the first HTTP/HTTPS hostname, timing and redirect responses; correlate timestamp with Bluehost access/error logs, PHP slow log and DB connection/schema-query timing. Inspect parent `.htaccess`, DNS/TLS/CDN/HTTPS routing. Compare cookie-free vs retained-cookie requests. No hosting cause was assumed.

# Must Fix Before Board Demo

| Class | Closure item |
|---|---|
| BLOCKER | `assets/images/accesson.php` is a confirmed PHP web shell: cookie-gated `eval(base64_decode(...))` and arbitrary upload copy. Removed in audit branch; history preserved. First appears in commit `6be362d`. Do not execute it to test. Clean staging must come from reviewed source. Investigate Bluehost filesystem/access logs, other sites under the account, deployment source and access credentials. Removal does not prove host recovery. |
| BEFORE BOARD | Deploy reviewed fixes to clean staging; rehearse all five personas with real login sessions. No demo account passwords were available; authenticated routes remain static-only. |
| BEFORE BOARD | Capture/reproduce original fresh-profile timeout and verify hostname/base-path routing; don't promise startup reliability from this audit's successful requests alone. |
| BEFORE BOARD | Prepare fake accounts with separate Staff and Admin authority; activate a Student login if that is the approved demo model. Publish cast snapshot; add notification/readiness data. |
| BEFORE BOARD | Run database migration/schema checks and the optional seed against isolated staging; do not run reset seed on production. |

# Must Fix Before Pilot

| Class | Closure item |
|---|---|
| BLOCKER | Hosting incident response: preserve evidence, determine entry point, replace compromised files from trusted source, rotate hosting/DB/SMTP/VAPID and relevant deployment credentials, invalidate sessions/tokens, and review potential data access. Audit cannot establish whether this file was exploited or deployed. |
| BLOCKER | Safeguarding parity: web conversation creation includes only `primaryGuardian`, despite README requiring approved guardian(s). Removed guardians remain conversation participants and can still read history/new thread content; send safety checks only require one currently linked guardian. Decide history policy, prevent former guardians receiving future child content, then test with two guardians and revocation. |
| BLOCKER | Mobile API `messageSend` checks safeguarding only when conversation type already equals `safeguarded`; a direct thread containing a newly assigned Student bypasses the web role-change check. Also lacks the web transaction/locking safety sequence. Scope the parity fix and regression test before exposing mobile messaging to students. No new native module built. |
| BEFORE PILOT | Rate-limit login, password reset, join/verification and mobile login at application or verified hosting layer. No durable application limiter found. |
| BEFORE PILOT | Notification delivery acceptance: real SMTP inbox and real iOS/Android Web Push, opt-out changes while queued, stale audience/relationship revocation, failure/recovery and deep links. Native device delivery is not implemented here. |
| BEFORE PILOT | Push worker retry/recovery and bridge concurrency/deduplication: see delivery section. Serialize cron until addressed. |
| BEFORE PILOT | Exercise every break-test below against migrated staging, including direct POSTs and ID tampering. Verify database triggers exist on Bluehost, not merely in source. |
| BEFORE PILOT | Set PHP display_errors off/log_errors on; several controllers catch RuntimeException and display its message, and PDOException inherits it. Audit fixes mobile DB error exposure only. Provoke DB failures in staging and contain any remaining SQL leakage. |
| BEFORE PILOT | Restore a matched database + private-file backup into staging and record recovery owner, retention, RPO/RTO. No tested recovery runbook found. |
| POLISH | Moderator-only and Safeguarding-only users have server-authorized endpoints but no Operations sidebar entry. Main Operations also rejects non-staff. Provide approved navigation without broadening permissions. |

## Break-test findings

All below are **static verification**, not a completed runtime break-test suite.

| Transition | Evidence / remaining check |
|---|---|
| Disable account | `Auth::currentUser` and mobile bearer lookup require active account; migration 047 cancels future signups/withdraws pending requests. Mail/push delivery suppress disabled accounts. Historical records remain. `active=0` alone does not trigger 047's account_status cleanup; test both. |
| Remove guardian | Family/Profile services use active relationship; migration 050 blocks removing last available guardian for a live Student. Conversation read membership is not revoked: BLOCKER above. |
| Expire credential | `VolunteerCoverageService` excludes expired/non-approved credentials from live slot counts; triggers 046/053 guard signup/check-in eligibility. Retains history. Test existing assignments at expiration. |
| Cancel schedule | Calendar has cancelled status; family dashboard excludes cancelled calls; migration 043 cancels stale notices. Verify feeds, attendance writes and queued delivery after cancellation. |
| Archive production | Context lists active productions; migration 060 archives channels. Future volunteer listing/signup/approval now rejects inactive productions. Approval-review POST alias is also guarded; verify direct admin roster writes independently. |
| Remove role | Auth roles/permissions are refreshed per request. `IdentityRolePolicy` prevents structurally unsafe Student/Staff changes. Web send rechecks current Staff/Student roles. Mobile parity gap above. |
| Remove production membership | `ProductionContext`/audience services require active membership for live access. Staff authority is organization-wide and intentionally still sees active shows. Verify files/forms/community and stale sessions by ID. |
| Deactivate volunteer | Coverage and signup require active profile; history retained. Existing signup rows may remain but do not fill live slots. Verify operational rosters and approval decisions. |
| Reject form | Review transition trigger 045 permits submitted→rejected; family form flow permits correction; credential trigger 044 targets submitted_for_user_id. Test prior approved credential/rejection/resubmission combinations. |
| Archive channel | Community excludes archived channels and rejects attachment access; archive uses separate permission-aware surface. Verify cached/deferred push doesn't reveal channel metadata after archive. |
| Invalid push subscription | 404/410 mark expired; invalid keys become logged failures; no active device suppresses queue item. No automatic retry/reclaim for failed/processing items. |

# Demo-Ready Workflows

No authenticated workflow is signed off READY end-to-end. **DATA/SEED ISSUE** below means credentials/linked fixtures or runtime evidence are missing; it does not mean a runtime defect was observed. Public login itself is **READY** (HTTP + browser desktop). Seed IDs must be read from staging rather than assumed in a live database.

| Persona / exact route sequence and actions | Classification | Evidence |
|---|---|---|
| Guardian: `/login` → `/app` → `/family-hub` → linked child via `/student-profile?student=<id>` → `/calendar?view=agenda` → `/forms` → `/forms/view?id=<assignment>` submit → `/notifications` mark read | DATA/SEED ISSUE | Family service checks active relationship; form service checks owner/guardian. Login/form return links tested anonymously; authenticated actions not exercised. Profile query parameter verified in source. |
| Student: `/login` → `/my-theatre` → `/calendar` → `/cast` → `/student-profile` → `/channels` → `/theatre-history` → `/forms`, `/resources` | DATA/SEED ISSUE | All handlers exist. Stock Emma account is managed with no password, so cannot demonstrate Student login until activated in staging. Cast publication missing from reset seed. |
| Volunteer: `/login` → `/volunteer-readiness?show=1` → `/volunteer/training` → `/volunteer-shifts` → `/volunteer/shift?id=<id>` signup → `/volunteer/approvals` request/status → `/volunteer/history` | DATA/SEED ISSUE | Profile/credentials/slot checks exist; low-risk archive lifecycle fix made. Seed includes ordinary and approval shifts; action rehearsal pending. |
| Staff: `/login` → Working Production POST `/production/select` → `/production` → `/schedule` or `/calendar` → `/production/day` → `/attendance/take?id=<schedule>` → `/admin/volunteer-shifts` → `/admin/forms` → `/channels` | DATA/SEED ISSUE | Production/manage and schedule/manage checks exist. Attendance item query parameter verified in source; use `/attendance` to choose item. Seed Staff currently also has safeguarding role; separate it before demo. |
| Admin: `/login` → `/admin/operations` → `/admin/accounts` (invite/disable/roles) → `/people` → `/admin/registrations` → `/admin/channels` → `/admin/moderation/terms`, `/admin/moderation/queue` → `/safeguarding` → `/admin/email`, `/notifications` | DATA/SEED ISSUE | Server permission checks exist. No delivery or administration mutation attempted on deployment. |
| `/family-hub`, `/calendar`, `/channels` anonymous later batch | RUNTIME FAILURE (transport timeout) | HTTP requests exceeded 20s with no response bytes; origin vs audit-network cause unresolved. Family retry succeeded; retained-cookie retry returned 303 in 2.88s. |
| `/my-theatre` anonymous startup | FUNCTIONAL GAP (fixed locally) | Deployed physical directory lost login return destination; unified route/wrapper fixes it. Deployment validation pending. |
| Moderator-only / Safeguarding-only navigation | PERMISSION ISSUE (navigation) | Endpoint permission exists; Operations entry hidden/rejected for non-staff. |

## Demo UI and accessibility quick pass

- Literal stylesheet/script links from PHP all resolve to repository files. `assets/css/app.css` deliberately imports `public/assets/css/app.css`; upload **both trees**. Several mobile styles are injected by JS. No missing literal asset path found.
- Deployed desktop login screenshot: intact two-column layout, readable fields/buttons. Public home and anonymous deep-link login rendered correctly. No authenticated desktop/mobile screenshot or keyboard walkthrough performed; do not label those visually validated.
- Fixed Calendar filter accessible names, missing document language on Accounts/Forms/Operations/Registrations, and explicit focus rings on shared navigation controls. Existing mobile CSS includes minimum button heights and responsive rules.
- BEFORE BOARD: inspect all requested critical routes at desktop and 390px with each persona. BEFORE PILOT: keyboard-test offscreen mobile drawer (focus containment/return), JS-built menus/disclosures, form errors and long tables. Contrast/tap targets/semantics on authenticated pages remain unverified. No WCAG certification claimed; no confirmed visual regression justifies redesign.

# Known Limitations To Disclose

## Notification and cron readiness

| Channel | What happens synchronously | What requires cron / gaps |
|---|---|---|
| In-app | Durable `app_notifications`, per-user read updates; message/channel read cursors and navigation counts calculated during requests | Schedule notice publication persists delivery records. Not every event creates an in-app notification. Notification page unread count covers only latest 30, unlike header total; disclose mismatch. |
| Email | Requests insert durable queue rows; preferences checked at enqueue; account-security bypasses ordinary preferences | `bin/process-email-queue.php`; `bin/queue-email-reminders.php` scans forms/shifts/credentials. SMTP/mail/log transports; log is NOT delivery. Delivery logs and three attempts with delay; interrupted sending reclaimed after ten minutes. Preferences are not rechecked at send. Transport acceptance is not inbox receipt. |
| Web Push | Device subscription/preferences saved and test intent queued | `bin/process-push-queue.php` bridges new messages/posts/app notifications, then delivers. VAPID + OpenSSL + cURL. Delivery status/logs, expired endpoint retirement. Fixed claim row-count check prevents two workers sending same claimed queue row; bridge still has no atomic dedupe/lock. Failed items aren't retried and processing items aren't reclaimed. Preferences/audience are not rechecked at final delivery. Bridge may miss a held post approved after cursor passes its ID. |
| Native push | `/api/mobile/push-subscription` stores subscription JSON/identity | No APNs/FCM adapter or consumer of `mobile_push_subscriptions` found. Do not demonstrate as operational. |

No real email inbox receipt, device push receipt, cron execution or Bluehost queue logs were verified. Preserve failed logs and inspect recipient/source before requeueing; do not blindly reset all failures or bridge cursors. An interrupted push may already have reached some devices, so replay can duplicate it.

# Production Configuration Checklist

- [ ] BLOCKER: complete host incident response and deploy from reviewed source. Check production `.env`, backups and other account sites for exposure; don't use real data until cleared.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://ctsmd.me`, `APP_BASE_PATH=` for root-host CLI workers, `APP_TIMEZONE=America/New_York`. Front controller overrides base from script location. `APP_TIMEZONE`/`SESSION_NAME` are documented but not applied by current bootstrap; configure PHP timezone/session name explicitly and verify CLI/web match.
- [ ] Configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET=utf8mb4`; least-privilege runtime user, separate migration authority. Apply schema + migrations in order only as appropriate; don't rerun non-idempotent ALTERs. Schema guard checks through 060, not 061/mobile tables; verify those separately if used.
- [ ] Bluehost PHP 8.x (prefer deployed 8.3), PDO MySQL, mbstring, fileinfo, OpenSSL, cURL. `display_errors=Off`, `log_errors=On`, private error log. HTTPS redirect and secure cookies confirmed at origin/proxy; HTTPS cookie was observed on deployed login. Session cookies use HttpOnly/SameSite=Lax and regenerate on login; verify strict session mode and filesystem isolation in php.ini.
- [ ] Confirm Apache `AllowOverride`, mod_rewrite and header rules; correct/remove `/ctsmd/front.php` fallback for root hosting. Test root/subfolder and trailing slashes. Deny `.env` variants, `.git`, source/storage/database/docs/bin, logs and PHP under assets. Audit added containment rules; verify them in deployed Apache.
- [ ] Private `STORAGE_PATH` outside document root; `STORAGE_MAX_UPLOAD_MB=20` (or approved limit); PHP upload/post limits aligned. Directories 0770/files 0660 with appropriate owner/group, no world write. MIME allowlist, random filenames and authenticated stream access exist; no malware-scanning acceptance evidence.
- [ ] SMTP: `MAIL_DRIVER=smtp`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`; validate TLS, SPF/DKIM/DMARC with domain owner and receive real test mail.
- [ ] Push: `PUSH_VAPID_PUBLIC_KEY`, `PUSH_VAPID_PRIVATE_KEY_B64`, `PUSH_VAPID_SUBJECT`; keep private key secret/stable after incident rotation. HTTPS and installed iOS Home Screen testing required.
- [ ] Configure Bluehost cron with verified absolute PHP executable and project paths (templates below). Keep workers serialized with host-supported locking; protect cron logs. Monitor oldest queued age, failed/stuck items, worker exit codes and actual receipt, not only queue counts.
- [ ] Enforce rate limiting; verify CSRF with invalid/missing tokens and unauthorized POSTs. Logout now requires POST+CSRF. Check direct importer and mobile bearer endpoints separately.
- [ ] Backup database and private files together, encrypt/restrict copies, test restore, record owner/retention/RPO/RTO and incident contacts.

Cron templates (replace `PHP_BIN`, `PROJECT`, `PRIVATE_LOG`; shell variables below are placeholders, not literal deployed values):

```cron
*/5 * * * * PHP_BIN PROJECT/bin/process-email-queue.php 50 >> PRIVATE_LOG/email-worker.log 2>&1
15 * * * * PHP_BIN PROJECT/bin/queue-email-reminders.php >> PRIVATE_LOG/reminders.log 2>&1
* * * * * PHP_BIN PROJECT/bin/process-push-queue.php 25 >> PRIVATE_LOG/push-worker.log 2>&1
```

Push cadence is optional until delivery gaps close. Confirm Bluehost supports the desired interval. Add host-supported locking around both workers, especially push bridge. Do not rely on web traffic to drain queues.

# Persona / Permission Matrix

Static defaults from migration 017 (+042 schedule permission), `Auth`, `AccessPolicy`, experience guards and `AppNavigation`. Roles can combine. Guardian is a member with an active family relationship, **not a separate RBAC role**. All columns assume active authenticated, approved membership; pending-account gate is additional. `C` = conditional/own records, `Y` = server-authorized, `N` = denied. `*` = authorized but no primary Operations navigation for single-purpose role. Member destinations are usually cards beneath My Theatre, not individual sidebar links.

| Area | Guardian | Student | Volunteer | Staff | Moderator | Safeguarding | Admin |
|---|---|---|---|---|---|---|---|
| Home `/app` | Y | Y | Y | Y | Y | Y | Y |
| Family `/family-hub` | C | C | C | C | C | C | C |
| Calendar `/calendar` | C | C | C | Y | C | C | Y |
| Community `/channels` | C | C | C | C | C | C | C |
| Messages `/messages` | C | C | C | C | C | C | C |
| Forms `/forms` | C | C | C | C | C | C | C |
| Resources `/resources` | C | C | C | C | C | C | C |
| Theatre History | C | C | C | C | C | C | C |
| Volunteer tools | C | C | C | C | C | C | C |
| Production `/production` | C | C | C | Y | C | C | Y |
| People `/people` | N | N | N | Y | N | N | Y |
| Accounts `/admin/accounts` | N | N | N | N | N | N | Y |
| Registrations | N | N | N | Y | N | N | Y |
| Moderation | N | N | N | Y | Y* | N | Y |
| Safeguarding | N | N | N | N | N | Y* | Y |
| Email ops | N | N | N | N | N | N | Y |
| Playbill public | C | C | C | C | C | C | C |
| Archives | C | C | C | Y | C | C | Y |

Server-side: account/email use `accounts.manage`; People `people.manage`; registrations/forms admin `forms.manage`; channels admin `community.manage`; moderation `community.moderate`; safeguarding `safeguarding.manage`; Production Day/attendance writes `production.manage`. Staff sees all active productions, independent of staff membership. Family/profile/history/forms check subject relationship; messages check conversation participant; resources/channel/archive check audience. Public Playbill requires publication/slug and is available without login. Volunteer action requires active profile and current credentials, not just Volunteer role. Cast requires publication.

No confirmed route in the inspected web critical set relies **only** on hidden navigation. This is source-level evidence, not completed IDOR testing. Direct PHP entrypoints and mobile API need independent tests. Important safety gaps are conversation participant revocation and mobile messaging parity above, not missing sidebar hiding.

# Demo Data Checklist

`001_demo.sql` is a **destructive reset** with DELETEs and AUTO_INCREMENT resets. NEVER import on production. Static inspection only; current deployed row completeness unknown. Public homepage showed a Board-demo Matilda Playbill but no open registrations.

| Required record | Stock seed / closure |
|---|---|
| Guardian + linked child | Jamie/Emma Carter at `example.test`; active family relationship. Jamie has Volunteer too. |
| Student login | Emma is `managed`, no password. Activate in isolated staging using approved account workflow. |
| Volunteer | Taylor Brooks; profile/credentials/shifts seeded; no login password. |
| Production Staff | Jordan Lee; seed also gives Moderator/Safeguarding. Use least-privilege Staff fixture for matrix rehearsal. |
| Administrator | Maya Rivera; seed maps Manager to Admin plus Staff/Moderator/Safeguarding. No password. |
| Active production/schedule | Matilda, relative future calls and shifts. Confirm dates/timezones after import. |
| Form assignment | Jamie household assignments, pending review. Activate Student-specific assignment only if needed for demo. |
| Community/post/messages | Channels/posts and safeguarded + adult conversations present. Use fake content only. |
| Notification/cast/readiness | Not populated by reset seed. Optional `003_board_demo_supplement.sql` adds missing notification, cast record and checklist for exact fake fixture matches, without deletes/password changes. Publish cast through staff workflow. |
| Training/service history | Requirements/modules/signups seeded. Verify hours/history screen has useful completed data. |

Supplement is staging-only and must be validated against migrated DB before use; no production import performed. Keep outbound mail in log mode and push workers off for fake fixtures. Do not create shared production demo passwords.

# Board Questions / Decisions

- Which single production, cohort size and pilot owner are approved? Who handles support and incident escalation?
- Should Students have independent logins in the pilot, or managed household profiles only?
- Do all approved guardians participate in safeguarded conversations? What may a removed guardian retain, and who authorizes a safeguarding exception?
- Confirm organization-wide Staff powers (People, registrations, schedule, forms, resources and moderation) versus show-specific responsibilities. Safeguarding stays separately granted.
- Is Web Push optional for pilot, with in-app/email as the supported channels? Do not promise native push delivery.
- Which outcomes justify expansion, and what retention/consent/backup rules apply to student and safeguarding records?

Existing data can support future reporting without building a suite:

| Captured data | Board feedback question |
|---|---|
| Attendance records + absence reports | Which rehearsals have repeated absence patterns, and who needs follow-up? |
| Volunteer signups + verified hour entries | Are shifts covered by eligible volunteers, and how many verified service hours were contributed? |
| Form assignments/submissions/reviews | Which household requirements are missing or awaiting review? |
| Registration submissions/status/linking | Where do applicants wait or drop out before joining a production? |
| Checklists/casting/production context | What remains unresolved before a show is ready? |
| Schedule audiences/groups | Who was called or targeted? This is not proof they attended. |
| Messages/posts/moderation/audit + delivery logs | How much moderation/delivery follow-up is needed? Define privacy boundaries before reporting content. |
| Credentials/training completions | Which requirements expire before upcoming commitments? |

# Recommended Next Builds

1. **BLOCKER: security and safeguarding closure.** Complete hosting investigation; fix former-guardian future-content access and web/mobile policy parity; add meaningful revocation tests.
2. **BEFORE BOARD: repeatable staging rehearsal.** Validated schema/fixtures, five real test logins, clean-profile HAR/root-cause evidence, and desktop/mobile/keyboard signoff for the exact route path.
3. **BEFORE PILOT: delivery and operational acceptance.** Queue retry/recovery/audience handling, cron monitoring, inbox/device proof, rate limiting and a tested restore runbook. No reporting suite or new native features.

## Validation record

- PHP 8.3 lint: all 124 PHP files passed (including added regression checks).
- Added regression checks: `php tests/pilot_lifecycle.php` passed 21 cases; `php tests/pilot_push_claim.php` passed losing-worker claim behavior. These use stubbed PDO, not SQL/trigger integration.
- `git diff --check`: passed. Literal PHP asset-reference scan: no missing CSS/JS files. Manifest parses and local referenced scripts/styles were inspected.
- No automated suite, Composer test command, or native integrity CLI existed at baseline.
- Executed SchemaGuard with PDO MySQL: failed at connection, `SQLSTATE[HY000] [2002] Connection refused`. No local database or production DB credentials were available; migrations, triggers and optional SQL supplement were NOT executed or certified. SchemaGuard checks source through 060 only.
- Anonymous deployed HTTP requests covered every requested critical route; most redirected to login, `/production` first canonicalized with 301, and three later requests timed out as recorded above. This verifies neither persona actions nor server-side permission outcomes for authenticated users.
- Browser checked `/`, `/login`, `/app`, and `/forms/view?id=1`; desktop login screenshot inspected. No authenticated browser, mobile layout, production cron, inbox/device delivery or restore test performed.
- No production data was changed. Fixes are repository changes, not a Bluehost deployment.
