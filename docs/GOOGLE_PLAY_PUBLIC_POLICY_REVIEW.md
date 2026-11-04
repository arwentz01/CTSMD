# CTSMD Connect public privacy and deletion pages: release review

Status: **Public-facing draft notices removed; operational and legal accuracy still requires CTSMD review** (November 4, 2026).

## Pages
- `/privacy`: public, read-only privacy policy draft.
- `/account-deletion`: public request form; requests are queued through `MailService` to `arwentz01@gmail.com` for manual verification and handling.
- Both are routed before the authenticated pages and before the schema guard.
- Native Settings, public login, and the public site footer link to these pages.

## Required configuration
Deletion request notification recipient is currently `arwentz01@gmail.com` as authorized by the site owner. Confirm the mailbox is actively monitored and that the existing `email_queue` processing job is enabled in the deployed environment. Avoid replying with sensitive student records.

## Before the Google Play submission
1. Approve final policy wording, actual effective date, verified legal contact, vendor inventory, processing and sharing, and retention/deletion schedules.
2. Confirm that form-based deletion intake, identity checks, record retention, and a documented fulfillment procedure meet Play Console's current account-deletion requirements. The form queues emails and does not delete any records.
3. Confirm whether a Settings link to the public request form meets the in-app deletion-request requirement for the current Play policy.
4. Ensure a qualified operator can process requests and students' guardian authorization without exposing records.
5. Confirm that real users are not present among demo accounts. Keep all demo records unchanged until an approved cleanup procedure is in place.
6. Run `php -l src/PublicPolicyExperience.php` and `php -l front.php`. Verify `GET /privacy`, `GET /account-deletion`, and unauthenticated accessibility return 200; verify POST to `/privacy` returns 405, and POST to `/account-deletion` enqueues a single email with valid CSRF. Verify invalid form data, CSRF failure, the rate limit, and confirmation messaging.
7. Test routes through the site's actual rewrite/webserver configuration and links from the web footer, login, and native Settings.
8. Reconcile the Play Data safety disclosures with an actual inventory of data collected/shared by web, native, and third-party providers.

No database migration, demo-data mutation, or automatic data erasure is part of this change. Public POST requests insert a queued email and rate-limit record only.
