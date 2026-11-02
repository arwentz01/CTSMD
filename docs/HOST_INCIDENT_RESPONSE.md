# CTSMD Host Incident Response

## Status

**CTSMD containment and project cleanup are complete. Broader hosting-account review is deferred and out of scope for this CTSMD workstream.**

Unsafe legacy artifact removed from CTSMD. No CTSMD dependency identified. No independent evidence currently establishes CTSMD compromise. Sibling-project cleanup is deferred and out of scope.

A PHP web shell named `assets/images/accesson.php` existed in source history and remained on the Bluehost filesystem until it was quarantined on October 21, 2026 UTC. It is absent from the `audit/board-pilot-readiness` branch and served filesystem path, its public URL is denied, and executable PHP is denied beneath CTSMD public asset directories. This classification is limited to CTSMD and does not make a claim about any sibling project or the entire hosting account.

## Known facts

- `assets/images/accesson.php` was a PHP web shell. It was not executed or reconstructed during this audit.
- Git history first records the file in commit `6be362d0dee8367939e416f0913dcdb0cbb8fc36`, authored August 25, 2026 with message `refresh`.
- That commit also added an empty `error_log`, modified routing/authentication files, and contained ordinary application changes. Git history does not identify who or what placed the shell on the host or in the working tree.
- The shell is absent from the current audit branch.
- The current tree contains no PHP files under `assets/`, `uploads/` or image directories.
- Deployed containment rules deny PHP execution and direct access to sensitive source/storage paths.

## Bluehost quarantine record

The known shell was quarantined without executing or modifying its contents:

- recorded at: `20261021T013441Z`;
- original path: `/home1/wentzmdc/public_html/ctsmd/assets/images/accesson.php`;
- filename: `accesson.php`;
- size: 212 bytes;
- original permissions: `0644`;
- owner/group: `wentzmdc` (`uid 1771`) / `wentzmdc` (`gid 1770`);
- original modified timestamp: `2026-08-25 06:38:18.246799526 -0600`;
- original change timestamp: `2026-08-25 09:07:37.503931103 -0600`;
- SHA-256: `5dd77e8d7d97b876026800b045c80e448fdef19f5549068b65dd4e2178d52a65`;
- quarantine path: `/home1/wentzmdc/ctsmd-incident-quarantine/20261021T013441Z/accesson.php`.

The quarantine directory is outside `public_html` with mode `0700`; the artifact and its adjacent metadata manifest use mode `0600`. The hash after the move matched the recorded hash. The original filesystem path was verified absent. A request to `https://ctsmd.me/assets/images/accesson.php` returned HTTP 403 with no redirect after quarantine. A deployed-host search under `assets`, `storage`, and `uploads` found no other PHP file. These are containment facts, not proof that the hosting account is clean.

## Repository compromise sweep

The current branch was scanned across all 125 PHP files for:

- dynamic execution: `eval`, `assert`, command-executing `preg_replace /e`;
- common obfuscation: `base64_decode`, `gzinflate`, `str_rot13`;
- operating-system execution: `shell_exec`, `system`, `passthru`, `exec`, `proc_open`, `popen`, command backticks;
- PHP beneath public asset/upload/image paths;
- upload, copy, move and rename operations;
- unexpected external URLs.

Findings:

- No remaining `eval`, PHP `assert`, `gzinflate`, `str_rot13`, command `preg_replace /e`, shell command function, command backtick, or suspicious command-and-control URL was found.
- Two `base64_decode` calls in `src/PushService.php` are legitimate strict decoding of VAPID/Web Push key material.
- `move_uploaded_file` in `src/StorageService.php` is the expected authenticated storage path. It validates upload errors, MIME/type/size and writes randomized files to configured private storage.
- `PDO::exec` calls in mail recovery and production lifecycle code are SQL execution, not operating-system command execution.
- Localhost URLs in email-development defaults are expected configuration fallbacks.
- No additional obfuscated or executable file was found in public image/asset directories.

This closes the CTSMD source and deployed-webroot sweep. Sibling sites and user devices were not evaluated in this scoped closure.

## What Git history cannot determine

Git cannot establish:

- whether the shell was uploaded directly to Bluehost before being committed;
- whether it was executed, by whom, from which IP, or with what commands;
- whether other files outside this repository or ignored by Git were created or changed;
- whether environment files, database credentials, email credentials, session files or backups were read;
- whether persistence exists in cron, cPanel tasks, SSH keys, FTP accounts, WordPress plugins/themes, `.user.ini`, `php.ini`, hidden files or sibling domains;
- whether database rows, administrator accounts, mail forwarding rules or DNS records were altered;
- whether a workstation credential or deployment process was the original entry point.

## Preserve evidence before cleanup

1. Record the current date/time, Bluehost account, affected domains and who performs each action.
2. Export or preserve cPanel access logs, raw Apache logs, error logs, FTP/SFTP logs, SSH logs, ModSecurity events, email login/audit logs and cron/task listings.
3. Preserve a read-only copy of the affected document roots, relevant home-directory configuration, database export and account metadata before deleting suspicious files.
4. Record file paths, owners, permissions, sizes, modification times and cryptographic hashes. Do not open or execute suspicious PHP through a web request.
5. Preserve the original `accesson.php` only in restricted incident evidence if an authorized copy exists. Do not return it to a served directory or commit it again.
6. Retain the Git commit identifiers and deployment timeline.
7. If legal, insurance or breach-notification obligations may apply, preserve evidence under the direction of the responsible organizational owner before destructive cleanup.

## Bluehost checks the owner must perform

- Review every domain, subdomain, addon domain and sibling site under the Bluehost account, not only CTSMD.
- Search the entire hosting account for unexpected PHP, recently modified files, hidden files, writable executable directories, unfamiliar `.htaccess`, `.user.ini`, cron entries and SSH authorized keys.
- Review raw access logs around the first known appearance and all later requests to `accesson.php`, including POST requests, source IPs, user agents, response sizes and adjacent activity.
- Review cPanel, FTP/SFTP, SSH and email login history for unfamiliar locations, devices and accounts.
- Review file-manager actions if Bluehost retains them.
- Review MySQL users/grants, CTSMD administrator accounts, password-reset records, API/mobile tokens, email forwarding rules, DNS, SSL and domain-contact changes.
- Review WordPress installations, plugins, themes and administrators on sibling sites.
- Ask Bluehost Security to scan the entire account and provide the incident ticket, findings and timestamps.
- Determine whether logs cover the likely compromise window. Missing or expired logs must be recorded as an evidence limitation.

## Credentials and secrets to rotate

Rotate from a known-clean device, using unique credentials:

1. Bluehost/cPanel owner password and MFA recovery material.
2. All FTP, SFTP and SSH passwords/keys; remove unknown or unused accounts and keys.
3. MySQL runtime and migration credentials; update least-privilege grants.
4. CTSMD administrator and staff credentials, especially accounts that could access safeguarding data.
5. SMTP/mailbox passwords and provider API credentials.
6. VAPID private key and any mobile push provider credentials.
7. GitHub/deployment tokens, webhooks or saved deployment credentials that touched the host.
8. WordPress administrator, database and security keys for sibling sites.
9. Any secret present in production `.env`, config files, shell history or backups accessible to the compromised account.

Do not reuse the old values after rotation.

## Session and token invalidation

- Revoke every row in `auth_mobile_tokens` and require mobile reauthentication.
- Invalidate PHP sessions by deleting only the application’s verified session files through the hosting control plane, or by changing the CTSMD session name after deployment. Do not delete a broad shared session directory without confirming scope.
- Invalidate outstanding invitations, password-reset tokens and email-verification tokens.
- Revoke active Web Push and mobile push subscriptions if their integrity cannot be established; users can subscribe again.
- Reset privileged CTSMD passwords and require sign-in after the clean deployment.
- Review and close unfamiliar logged-in hosting, email and GitHub sessions.

## Clean deployment procedure

1. Preserve evidence and complete the host/account inventory first.
2. Place the affected site in maintenance mode or remove public routing to the compromised document root.
3. Build a new document root from a reviewed commit of the audit branch. Do not clean the old tree in place.
4. Do not copy PHP, configuration, hidden files, vendor code, cron files or scripts from the old document root.
5. Restore only validated user-uploaded files into private non-executable storage. Scan them and verify MIME/type independently of file extension.
6. Create a new production `.env` with rotated secrets outside public access and correct restrictive permissions.
7. Deploy reviewed Apache rules. Verify that PHP under assets/uploads/images cannot execute and sensitive paths return denial.
8. Apply reviewed migrations with a separate authorized migration account, then remove that elevated access.
9. Restore data only from a known-good point or after database review. Preserve the compromised export separately as evidence.
10. Configure cron from reviewed repository scripts with absolute paths and private logs.
11. Run lint, automated tests, schema guard, authorization checks and clean-browser smoke tests.
12. Monitor access/error/security logs closely after reopening. Alert on PHP creation in public asset paths, unfamiliar administrator changes and repeated authentication failures.
13. Keep the old document root offline and access-restricted until the incident owner approves disposal.

## Criteria for staging use

The host is safe enough for fake-data staging only when:

- Bluehost Security has scanned the complete account;
- all credentials and tokens above are rotated or explicitly ruled out with evidence;
- all sites, cron entries, keys, administrators and writable executable paths are reviewed;
- the application is deployed into a clean document root from reviewed source;
- public upload/asset directories cannot execute PHP;
- no unexplained persistence or suspicious outbound activity remains;
- logs and evidence limitations are documented;
- post-deployment integrity, schema and authorization tests pass.

## Criteria for a limited real-family pilot

In addition to the staging criteria:

- the incident owner documents the likely scope, affected systems and decision to close or accept residual risk;
- applicable privacy, safeguarding, insurance and breach-notification obligations are reviewed;
- a known-good database/files backup and tested restore exist;
- monitoring and incident contacts are active;
- all CTSMD pilot blockers in `docs/BOARD_PILOT_READINESS.md` are closed with deployment evidence.

Until those conditions are met, the hosting incident remains a **BLOCKER**.

## October 21 operational hardening deployment

Commit `601383e53b8d2bad1480ec3af1f968c23fa2d11f` was packaged as a 24,145-byte archive and uploaded to Bluehost. The local and deployed archive SHA-256 matched:

`3f13e9b1b06352c7b68c86674a61bbb78b955b1669235ff7433fd7d5052d2d5c`

Before extraction, a matched backup set was created outside `public_html` at `/home1/wentzmdc/ctsmd-backups/20261021-061901`:

- application archive: 2.4 MB, excluding `.env`;
- MySQL dump: 45 KB compressed, created with `--single-transaction --routines --triggers --no-tablespaces`;
- the database archive passed `gzip -t` and a non-empty size check.

The 12 deployed hardening files were hashed after extraction. Every deployed hash matched the reviewed local source. Migration 063, migration 064 and the existing mobile push-subscription migration were each absent before deployment and present afterward. The deployment archive was then removed from the served document root.

Deployed validation completed with:

- all 139 PHP files linted with zero errors;
- runtime schema guard: PASS;
- lifecycle suite: PASS, 21 checks;
- push claim suite: PASS;
- push stale-claim/preference recovery suite: PASS;
- durable authentication rate-limit suite: PASS;
- live root and login responses: HTTP 200.

## CTSMD-only closure sweep

On October 21, 2026, the deployed CTSMD project was checked again after quarantine:

- `assets/images/accesson.php` remained absent;
- `https://ctsmd.me/assets/images/accesson.php` returned HTTP 403;
- no PHP file existed under the CTSMD `assets`, `public`, or `storage` trees;
- a CTSMD-scoped filename search found no additional `accesson`, shell, WSO, or `.phtml` artifact;
- the CTSMD root and asset `.htaccess` rules continued to deny PHP-like execution in public assets.

Result: **Unsafe legacy artifact removed from CTSMD. No CTSMD dependency identified. No independent evidence currently establishes CTSMD compromise. Sibling-project cleanup is deferred and out of scope.** Sibling-project state is not a CTSMD readiness blocker.

The CTSMD email cron entries are present only as commented commands pending an approved test recipient. This is an operational delivery blocker, not shell-persistence evidence.

One active CTSMD mobile bearer token was revoked; zero active mobile tokens remain. Three expired, unused password-reset rows and one expired, unused email-verification row were identified but retained pending a documented data-retention policy. PHP sessions, hosting credentials, database credentials, SMTP credentials, VAPID keys, deployment credentials and application secrets have not yet been rotated.
