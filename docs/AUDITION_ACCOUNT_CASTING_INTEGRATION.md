# Audition registration → verified household → student casting

## Behavior
- Public registration creates a durable audition submission independently of CTSMD accounts.
- Existing guardians can use the private registration management token, sign in to a verified account, and choose a matching child from their **active** household relationships.
- A newly verified guardian is reconciled against unlinked minor audition submissions only when the submitted guardian email matches the verified email and exactly one active Student relationship matches the submitted child's name.
- Creating a managed child can reconcile earlier submissions against the now-verified household.
- A linked, **accepted** minor audition with a production ID creates one **under_review** casting row for the Student. No parent/guardian is added as a casting participant.
- Casting enrollment is not production roster membership; existing casting offer acceptance/finalization remains responsible for participant enrollment and guardian access.
- Duplicate processing uses the existing unique (production_id,user_id) casting constraint and INSERT IGNORE. Existing decisions are not reset.
- No automatic intake into completed/archived productions.
- Adult auditions are excluded from this child-specific bridge, since the current casting board accepts Student identities only.

## Staff and family flow
1. Staff creates an `audition` registration opportunity and selects the production.
2. Family registers the child using parent/guardian contact information.
3. Family verifies or signs in to its CTSMD account. If a Student is already in its household, the private management link allows explicit association.
4. Staff approves the audition. If verified Student/guardian linkage is ready, casting is created automatically. Otherwise, submission remains available for intake review.
5. Staff may explicitly link an existing Student/guardian or create reviewed records from the intake screen. If the guardian is not yet verified, automatic casting will remain deferred.
6. Staff proceeds with the normal casting decision, callback, offer, and production enrollment workflow.

## Safety boundaries
- Email strings and child names are insufficient to grant guardian/Student access on their own.
- The self-service claim requires both the private submission token and a logged-in guardian whose email is verified, plus an active pre-existing family relationship.
- Auto-reconciliation never creates users, children or new guardian relationships.
- Multiple children with the same name require explicit selection through the token-based claim flow or staff review.
- The public registration management page exposes only linkage status, never private casting decisions.
- Staff `create_records` is an explicit manual conversion and may create managed records. It should not be confused with automatic matching.
- Existing invited guardians without completed account/email activation cannot automatically enter casting. Use the existing account activation process or review them on staging before launch.

## Staging acceptance checks (not yet executed)
- Existing verified parent, one matching child: submit, claim via management token, accept registration; only child's ID appears as Under Review.
- New guardian: submit, register, verify, add matching managed child; approve, check casting is created once.
- Accepted before verification: verify later, ensure deferred casting is created.
- Same name in two Student profiles: auto-match stops; manually select the correct child through verified token flow.
- Wrong guardian email, inactive relationship, disabled guardian, adult participant: no unauthorized automatic match.
- Cancelled/declined submission: no claim and no automatic intake.
- Re-approval/retry: no duplicate casting, no reset of callback/offered/cast decisions.
- Completed/archived production: no new casting writes.
- Verify php lint, `php tests/audition_intake_bridge.php`, existing regression tests, migrated MySQL integration and browser/device UI before production rollout.


## Follow-up hardening (October 26)
- Staff linking of existing students now requires an **already-active** guardian relationship. Registration intake cannot reactivate a former guardian. Household relationship changes belong in Family & Safeguarding.
- Staff intake displays whether student identity is missing, guardian verification is pending, the linked registration awaits approval/sync, or casting already has a record.
- Cancellation or staff decline withdraws only a casting record still in `under_review`, using the exact originating audition submission ID; callbacks, offers and cast decisions are preserved for staff review.
- The targeted stub tests also cover withdrawal and prevention of unintended withdrawal. They are committed but have **not** been executed in CI or against a migrated database.
- Casting creation requires both guardian email verification and an active guardian-to-student relationship. No parent account can be inserted as a Student participant.

## Remaining release gates
- Run `php -l` over changed PHP files and `php tests/audition_intake_bridge.php`.
- Test both public and staff status transitions in migrated staging MySQL, including cancellation after a callback.
- Validate account activation for existing `invited` accounts separately; email verification on new self-registrations does not automatically activate every legacy invitation state.
- Verify multiple registrations for the same student across one production preserve the original source and do not overwrite an existing casting decision.
- Confirm guardian revocation after linking removes future access while retaining historical registration/casting audit data.
