# CTSMD Connect invitation onboarding: rollout and acceptance

Branch: `audit/board-pilot-readiness`. Changes are **not deployed** until the board-pilot release process is completed.

## Design rules

- An **administrator with `accounts.manage`** can invite an **adult** using Accounts & Access → Invite an adult. No first/last name is needed from staff. The invitee completes these details, date of birth, contact phone, and password.
- The invitation link proves access to the invited email. Upon acceptance the adult receives verified email and **approved General Membership only**. It does not assign staff roles, production memberships, or production channels.
- Invitations are seven-day, single-use, token-hashed records. Resending invalidates any earlier unaccepted token; revoking disables the unused placeholder account. A revoked placeholder can be re-invited with the same email after additional role and status checks.
- A new invitation must not be sent to an existing registered email. Review that account separately.
- Only a linked parent or legal guardian can invite a child to activate an **existing managed student account**. No child needs an independent login to have a managed profile; no CTSMD staff direct student invitation is permitted. A student invitation lasts 48 hours.
- Both website Home and native Home/Action Center show pending/expired student activation messages for linked guardians. Completed student activation clears the alert.
- The website and app Help screens use the same guide catalog with role filtering. Native requires a backend offering `/api/mobile/help-guides`.

## Required database change

Apply `database/migrations/128_adult_general_membership_invitations.sql` after the existing authentication migration(s) and before enabling the invitation UI.

This adds `auth_invitations.purpose`, defaulting old invitations to `account_access`. **Do not run the new application code against an unmigrated database.** Do not apply it to production without the normal backup, schema check, authorization review and release approval.

## Pre-pilot acceptance checks

1. As a user with `accounts.manage`, invite a new adult address. Confirm the invitation queue item and branded HTML+text email, expiration and deep link.
2. Follow the email as the recipient. Test missing name/date/phone/password, weak password, DOB showing someone under 18, wrong token and expired token. Confirm no activation or membership approval on failed input.
3. Accept successfully as an adult. Check `users.account_status='active'`, email verification, saved profile, `organization_membership_status='approved'`, **only** the `member` role and no production assignments. Confirm public signup members still require the normal review.
4. Test duplicate email detection, resend invalidating old tokens, revoke preventing activation, and re-invite after revocation.
5. Confirm a staff user cannot invite a student via the existing account access action or new adult form. Adult confirmation rejects a DOB younger than 18 even when the email exists nowhere in CTSMD.
6. Guardian adds a child without email: no activation-needed Action Center alert. Guardian adds child with email: pending activation alert appears. Expire link: expired alert appears. Resend via Family, accept new link: alert disappears. Other parents and staff without an active guardian relationship see no private alert.
7. In native app Home → Needs Your Attention and native Action Center, ensure student alerts appear and link to Family. Confirm only the guardian has the server-side authority to resend.
8. In native Help, test filtering and expanding articles for student, parent, production staff and account-review roles. A caregiver-only account may read general Help, not administrative guides.
9. Run PHP lint, `tests/account_email_template.php`, `tests/help_guide_catalog.php`, mobile `npm run typecheck`, CI and browser/device acceptance before rollout.

## Operational notes

Email is **queued**, not guaranteed delivered immediately. Confirm the `account_security` queue worker and sender configuration before pilot testing.

Existing recipients may need to sign into the website after opening the app's web handoff because native authentication does not automatically confer browser session cookies.

The two invitation classes intentionally do not share their issuance rights. Administrator adult invitations and guardian-managed student invitations must remain separate at both UI and authorization layers.

## Release notes

Both `src/ReleaseNotesExperience.php` and `mobile/src/content/releaseNotes.ts` contain an upcoming update section for invitation-led General Membership, mobile Help and student activation reminders. These items must stay upcoming until the app build and database migration have passed acceptance.
