# CTSMD Connect public privacy and deletion pages: release review

Status: **DRAFT, NOT APPROVED OR DEPLOYED** (November 4, 2026).

## Pages
- `/privacy`: public, read-only privacy policy draft.
- `/account-deletion`: public, read-only request instructions.
- Both are routed before the authenticated pages and before the schema guard.
- Native Settings, public login, and the public site footer link to these pages.

## Required configuration
Set `CTSMD_PRIVACY_CONTACT_EMAIL` in the hosting environment **to a verified, actively monitored organizational address** for privacy and deletion requests. Without this configuration, the deletion page states that intake is not yet configured. Never commit credentials or invent the email.

## Before the Google Play submission
1. Approve final policy wording, actual effective date, verified legal contact, vendor inventory, processing and sharing, and retention/deletion schedules.
2. Confirm that email-based deletion intake, identity checks, record retention, and a documented fulfillment procedure meet Play Console's current account-deletion requirements.
3. Confirm whether an in-app deletion-request action is required in addition to the public page; the native app currently links to the page.
4. Ensure a qualified operator can process requests and students' guardian authorization without exposing records.
5. Confirm that real users are not present among demo accounts. Keep all demo records unchanged until an approved cleanup procedure is in place.
6. Run `php -l src/PublicPolicyExperience.php` and `php -l front.php`. Verify `GET /privacy`, `GET /account-deletion`, and unauthenticated accessibility return 200; verify POST returns 405.
7. Test routes through the site's actual rewrite/webserver configuration and links from the web footer, login, and native Settings.
8. Reconcile the Play Data safety disclosures with an actual inventory of data collected/shared by web, native, and third-party providers.

No database migration, demo-data mutation, deletion endpoint, or automatic data erasure is part of this change.
