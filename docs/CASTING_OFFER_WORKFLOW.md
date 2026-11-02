# Casting offer responses and Working Board drawers

Migration: `database/migrations/080_cast_offer_responses.sql`. Deploy **before** new PHP files.

## Staff workflow
- The Working Board now lists one compact row per student/manual participant. **Manage / Update** opens a slide-out drawer with the existing editable casting, communication, and roster controls.
- The drawer sends form POSTs asynchronously and displays save/error feedback without closing or reloading. Uploaded manual headshots use FormData.
- Save the decision as **Offered**, then choose **Send offer for acceptance** in the same drawer for registered students. This intentional second step avoids accidentally releasing private casting decisions.
- The offer creates Notifications Action Center tasks for the Student and all currently active guardians. It does not immediately roster a student.

## Family response
- Authorized active Student or current active guardian can Accept or Decline on `/notifications`. The first committed response wins.
- Accept changes `production_casting_records.casting_status` from Offered to **Cast**. Staff must still finalize the roster/group assignments.
- Decline requires an explanatory note, changes status to **Withdrawn**, and posts a production staff notification. The note remains in the casting offer record and is shown in the staff Manage drawer.
- Full name/role details stay behind authentication; invitation notifications do not expose the student's name or role in the message.
- Access checks are repeated at decision time, including current family relationship, student account activity, and writable production lifecycle.
- Changing the offered role or decision cancels a pending offer. Previously sent notification records are retained but former guardians cannot view new offer notification content through the filtered Notifications feed.

## Important boundaries and tests
- Manual participants have no CTSMD account; they can be managed through drawers but **cannot** receive an authenticated family offer task until linked to a Student account.
- Sending a normal casting-result email is still an independent, explicit staff action; email queue delivery depends on the hosting worker.
- Verify the database migration, PHP lint, all major actions inside drawers, attachment save, offer delivery to Student/current guardian, former-guardian revocation, simultaneous response race, decline-note visibility, stale offer cancellation, accepted status, roster finalization, and mobile keyboard focus.
- These checks have **not** been run against CTSMD.me from the GitHub connector. Do not mark production acceptance complete without deployment and verification.
