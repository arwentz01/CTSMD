# Production People and Production Team

## Batch A — reviewed data/workflow ownership (October 29, 2026)

Baseline: `a3d4944`, existing `audit/board-pilot-readiness`; fetched remote and confirmed clean/current before work. No deployment authorized.

Existing membership uniqueness is `(production_id,user_id,audience_type)`; independent cast, staff and family records can coexist. The old People editor writes all audiences and removes casting/history alongside generic membership removal. It also retires unrelated crew/creative functions when cast is removed. This is the ambiguity being removed, not a reason to create another identity table.

Reuse `production_participant_functions` for team assignments. Add stable assignment IDs, operational category, controlled function code/custom title, private notes, display order, publication eligibility and revision tracking via additive migration 098. Keep populated composite identity and previous history. Team assignments reference existing accounts; they do not create memberships, auth roles, family links, shift assignments or notifications. A team credit alone grants no production/private-resource access; existing authorization and membership management remains authoritative.

Casting owns performer candidates, character/ensemble assignments, offers, enrollment, removal and cast credits. Move cast removal into the Casting workflow and preserve independent team credits, Staff memberships and family relationships. Family & Pickup owns real relationships; derive current production guardians from active parent/guardian links to available participating Students rather than stale manually assigned Guardian rows. Caregiver relationships do not become guardians. Volunteer Management remains unchanged.

Unified roster is a read model keyed by existing user ID: cast membership/casting titles, team functions, valid guardians discovered from Student participation, and current staff membership/authorization context. All People contains each person once; filters may overlap. Historical/inactive responsibilities remain retained and separately inspectable. Staff global authorization is context, never an invented team function or membership.

Team editing uses current production management permission and production Staff membership (Administrator exception), locked lifecycle/actor/subject state, revision checks, audits and retained Theatre History credits. Ordinary eligible active adult Member/parent/Volunteer accounts may receive a descriptive team credit without Adult Performer authorization. Students remain safeguarded; assignment does not waive any restrictions.

Playbill imports explicitly reviewed eligible name/title credits into existing sections as a snapshot; it never imports notes/contact/biography/photos. Existing publication permissions/approvals still apply, including approved Student performer profile before Student team-name export. Team changes do not mutate published/archived snapshots. No automatic publication or notifications.

## Rollout boundary

Migration 098 and prerequisites must be reviewed/applied with matching runtime in an approved environment. Only explicitly disposable local schemas are migrated for tests. The default local `ctsmd` demo remains behind 093–097 as previously reported; this task will not silently replay historical migrations into it. No production migration, worker, branch creation or force push.

Implementation and measured verification will be appended as batches complete.

## Implemented batches B–E

- **B:** 098 extends the existing function table with stable IDs, categories, controlled labels/custom title, ordering, private notes, eligibility and optimistic revisions. Team membership never requires a performer role and never creates an authorization membership. Existing populated functions and credits remain.
- **C/D:** One read model keyed by account ID drives All People/Cast/Production Team/Guardians/Staff, search, unique counts and detail drawers. Add/edit team uses native dialogs with ordinary server forms, CSRF, production context validation, lifecycle/actor/subject locks, revision conflict errors and audit events. The old generic audience/participation editor and generic Remove POST are rejected. Casting removal moved into Casting, including withdrawal through the decision editor. Removing cast ends cast credits only; independent team, family and Staff data remain. Structured role changes synchronize Student and Adult roster/history display.
- **Authorization ownership:** Existing account Authorization roles now includes a scoped Staff-access disclosure using the same `production_memberships.audience_type='staff'` and `accounts.manage` mechanism. It cannot grant Staff to an account without an existing Staff/Admin role. Team credits never grant it. Revocation preserves cast/family/team data. Closed Staff scopes stay historical; they no longer prevent removal of global Staff authority after live scopes are resolved.
- **E:** Team credits enter Playbill through an explicit reviewed name/title preview/import into existing sections. Import is restricted to unpublished, writable productions and current authorized editors, with preview fingerprints and retained previous sections. Existing publication workflow snapshots sections into editions; a pre-existing sponsor-table sort bug preventing real publication was corrected. Current/archived editions are not live-resynchronized from team changes. Student names use approved profile display names and remain gated by that approval on public reads. No team bio/photo/contact/notes are automatically published.
- **Historical public compatibility:** Additive migration 099 freezes only previously public legacy Staff name/title lists in existing sections before replacing live RBAC-derived team publication. It is retry-safe and never overwrites a new reviewed snapshot. Both 098/099 require populated-host review, backup/restore and explicit hosting approval; only disposable schemas were used here.
- **Notifications:** Team assignment operations produce no notification fan-out. Production-wide schedule recipient resolution now deduplicates overlapping real cast/guardian/Staff membership rows, matching the existing group-audience behavior. Delivery authorization and workers are unchanged.

## Measured verification

See [Production People verification](PRODUCTION_PEOPLE_VERIFICATION.md). Schema changes are available in disposable local preview/test schemas only. They are **not** applied to the default MAMP `ctsmd` database or production.
