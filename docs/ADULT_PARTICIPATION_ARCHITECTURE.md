# Adult Performer and mixed-age productions

October 29, 2026. Source correction on `audit/board-pilot-readiness`; based on current branch `bf2abeb`. No deployment, production data change, notification worker activation or live-account testing. RC1 and pilot signoff remain separate.

## Account model

**Adult Performer is the normal `adult_performer` authorization role.** Administrators assign and remove it in the existing `/admin/accounts/view?id=...` Authorization roles checkbox form. The existing `accounts.manage` authorization, role validation, CSRF and `account.roles_updated` audit apply. No classification reason, second approval, independent identity system or age-derived promotion exists. Role assignment does not require a DOB. Existing membership approval and account availability remain authoritative for their respective access checks.

The role has no permission grants. Member, Volunteer, Production Staff, Administrator and genuine family relationships can coexist with it. Ordinary Members do not become eligible performers. Student and Adult Performer cannot be assigned together. DOB changes never change roles. A guardian/caregiver relationship is independent data: Adult Performer grants no child management, pickup, confidential support access or staff authority.

Existing structural Student checks remain: resolve active Student memberships and child relationships through their existing tools before removing Student. An available child must retain an available guardian. Where family links must be retired, disable the account using existing account administration first. Existing account role administration then changes the roles; there is no separate transition application or reviewer. Student role changes increment identity revision, revoke bearer/push credentials and invalidate old web sessions. Removing Student freezes old safeguarded conversation access at its retained message cutoff. This is administrative work on the existing account, never automatic birthday promotion.

## Shared performer integration

`PerformerQuery` provides Student-or-Adult Performer SQL predicates for casting candidates, audition intake candidates, performer profiles, publication candidates, production groups and schedule recipients. `ProductionParticipantEligibility` enforces role category, active account, lifecycle, Student age bounds and audited age exceptions inside existing enrollment/casting transactions. Revoked performer roles no longer authorize stale Adult roster membership; records are retained rather than deleted. Independent Staff and family memberships remain effective under their own rules.

| Workflow | Existing integration |
|---|---|
| Audition/intake | Eligible role-based candidates; central eligibility on production linkage; Student guardian checks retained |
| Casting/review/callbacks/role assignments | One casting service and board; ordinary Members excluded from performer candidates; manual credits remain explicit non-account records |
| Offers/enrollment | Adult responds for self; Student authorized guardian responds; same offer/enrollment transaction and lifecycle checks |
| Roster/functions/groups/schedules | Independent audiences and descriptive functions; role-aware roster/group and schedule access; no Staff membership overwrite |
| Profiles/headshots/history | Shared existing profile/history storage; owners retain their own existing profile/credits after role removal; private media authorization retained |
| Playbill | Eligible live cast sync, Adult self-consent, Student guardian safeguards, withdrawal retained, archived snapshots frozen |
| Public casting | Staff-reviewed name/role snapshots only; no private biography, DOB or headshot exposure |
| Native | Existing API/services; authorization roles displayed; offers accepted natively; profile/history/registration/consent use existing website handoffs |

An intake for a new adult without the role does not silently confer it. Create/approve the normal account and assign Adult Performer through account administration before linking to a production. Existing guest declarations are not authorization. Production eligibility is not a substitute for account identity review.

Production settings still support Students, Adults or mixed, optional Student age bounds, explicit eligibility date and audited individual age exceptions. Here Adult means Adult Performer role. Age exceptions cannot waive participant category, lifecycle, safeguarding or account availability.

## Communication and consent

Conversations are account-wide between active platform accounts; shared active production is not required. Adult-to-Adult and Adult-to-Staff use ordinary authorized messaging. Any Student conversation retains required active guardian inclusion, including when the Adult Performer is the parent of a different Student. Student-to-Student direct messaging remains prohibited. Send and queued-delivery authorization recheck current relationships, disabled accounts and retained historical cutoffs. Production Community/private resources remain separately scoped.

Role assignment never implies public media consent. Adult publication approval must come from the subject, including after performer-role removal. Students retain existing guardian/staff approval constraints. Historical casting, accepted offers, credits, manual transfer records and approved archived snapshots are retained. No role change deletes production history.

## Forward migration and existing databases

Historical migrations **093–096 remain immutable**. `users.adult_participant` from 093 and `participant_transition_reviews` from 095 are obsolete runtime classification mechanisms, retained for historical data and audit. `AdultParticipantService.php`, `ParticipantTransitionService.php`, account classification actions and both extra panels are removed. New runtime authorization never reads the legacy flag/review table.

Migration **097** seeds the ordinary role without permission grants. Explicit legacy Adult flags map to that role unless the account is a Student or active child relationship. A persistent mapping ledger records mapped/skipped decisions and prevents retries from restoring subsequently revoked roles. Ordinary Members receive nothing. Skipped anomalies require operator review; do not infer adulthood from DOB or an old flag. The migration restores the strict available-child last-guardian trigger, removing 095's separate review exception. All populated legacy columns, tables and audit events remain.

Hosting procedure remains blocked pending explicit approval, read-only inventory, backup/restore rehearsal and reviewed statement/lock plan. Verify populated prerequisites through 096, stop old-runtime writes during 097 and matching runtime replacement, inspect mapping decisions and unchanged family/history counts, verify role description and zero permission grants, then run role/persona acceptance in staging. DDL is not transactionally reversible; trigger replacement and populated mapping need operator review. No automatic production migration runner is introduced.

Rollback requires a matched runtime/database backup and a reviewed restore plan. Do not drop populated legacy data or replay arbitrary backfills. Rolling runtime alone back to classification-based code is unsafe after role changes: its retained legacy flags are deliberately no longer synchronized. Preserve new audit/history records and reconcile reviewed account roles before any recovery. See RC1 restore runbook for the broader release procedure.

## Verification and limits

Measured local evidence is recorded in `ADULT_PERFORMER_VERIFICATION.md`. Source contracts and fresh bootstrap alone do not prove populated hosting compatibility, real-device behavior, push delivery, full publication/restore acceptance or WCAG certification. Historical evidence in the assumption inventory describes the previous implementation and is not the current identity model.

Remaining blockers include independently reviewed hosting prerequisites and 097, restore rehearsal, full publication/manual-media/archive permutations, actual iOS/Android device sessions and notification revocation/delivery, and the existing Expo SDK patch compatibility gate. No dependency upgrade or production release is part of this correction.
