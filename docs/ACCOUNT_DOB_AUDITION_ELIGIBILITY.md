# Account contact details and audition age eligibility

Implemented on `audit/board-pilot-readiness` with migration `086_account_contact_and_audition_age.sql`.

## Account data

Adult `/join` registration collects first and last name, DOB, phone, street address, optional address line 2, city, state and postal code, email and password. The server validates and stores the profile details with the account.

When an authenticated guardian creates a Student under `/family/manage`, the Student must have their **own DOB**. The Student's initial contact phone and address are copied from the guardian account; a child remains the Student identity, never the parent. Authorized guardians and staff can update Student DOB from the Student theatre profile. A Student self-edit must not change DOB.

`/account` allows members to update their own DOB, phone and address. Existing accounts have null values until completed; the new columns do not force a migration-time fake date.

**Safeguarding caution:** Self-edited DOB is user-provided identity information, not third-party age verification. A future verification/attestation workflow may be appropriate for role-critical auditions. Changing DOB must not silently rewrite historic casting decisions.

## Audition age rules

Staff can configure an audition's inclusive `minimum_age` and/or `maximum_age` in Registration Operations. The check date is `age_eligibility_date` if specified, otherwise the audition `starts_at` date. At least one is required when setting restrictions.

Calculation uses completed birthdays on the cutoff date, not a rough current-year subtraction.

A logged-in verified guardian can select a Student from an active guardian household relationship on the public audition registration. The form then uses Student name and DOB (and guardian contact), not a user-submitted guest exact age. On successful public registration, the application attempts to claim/link the registration to the Student; original public manage-token recovery remains available.

If no Student is selected, the public form retains the existing age group: Under 13, 13–17, or adult. The age group **cannot establish exact-age eligibility**. For an age-restricted audition, unresolved DOB remains a staff-review requirement before account linkage and automatic casting intake.

`AuditionIntakeBridge` checks exact DOB age on public claim, verified guardian auto-reconciliation and accepted-to-casting sync. Staff linking of existing Student records uses the same check. A refused age link cannot create an `Under Review` casting row.

## Privacy and operational boundaries

- Do not render full DOB, home address or phone on publicly accessible cast or Playbill pages.
- Never use the guardian's DOB to determine the child's audition age.
- Date of birth belongs to the Student account, including guardian-managed children who cannot yet sign in.
- A registration's submitted guest exact age is retained as historical information; linking to an existing student consults current profile DOB.
- Do not put raw DOB/address into audit metadata or public URLs.
- Existing accounts need a profile-completion path before joining age-restricted productions.
- Age eligibility is distinct from casting suitability and final role assignment.

## Pilot/staging QA

1. Apply migration 086 in migrated staging and verify both adult account registration and guardian-managed Student creation.
2. Register as a verified guardian, select one of two Student accounts, and confirm identity and DOB reference the **child**.
3. Confirm under/over-boundary Student selection rejects the audition, including tampered POSTs.
4. Confirm guest registrations still accept age groups and are evaluated from the declared exact age.
5. Test staff linking, duplicate submission processing, and already-approved registration syncing.
6. Test DOB updates by guardian, staff, and Student; Student must not be able to alter DOB in theatre profile.
7. Test unauthenticated visitor, unverified guardian, disabled account and revoked family relationship.
8. Test February 29 birthdays and timezone-boundary audition cutoffs.
9. Verify CSS/mobile layout, labels and error feedback follow `WARM_BACKSTAGE_DESIGN_STANDARD.md`.
10. Run PHP lint and `php tests/person_eligibility.php` plus existing audition bridge tests.
11. Verify deployment uses the correct current schema before accepting registrations.

**Status:** Code and regression tests committed. No migrated staging database, PHP CLI, or end-to-end browser verification was executed through the GitHub connection. Do not mark this feature production-ready until the above checks pass.
