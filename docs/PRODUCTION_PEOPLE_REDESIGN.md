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
