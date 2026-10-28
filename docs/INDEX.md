# CTSMD Connect documentation map

Updated 2026-10-26. Start here instead of reading every chronological build note.

**Single active task tracker:** [TODO.md](TODO.md) holds P0–P2 work, release gates and the product backlog. The [historical snapshot](archive/TODO_PRE_CONSOLIDATION_2026-10-26.md) is archived. Do not maintain a second active list.

## Current sources of truth

| Area | Document | Purpose |
|---|---|---|
| Release / pilot decision | [BOARD_PILOT_READINESS.md](BOARD_PILOT_READINESS.md) | Historical evidence and pilot gates; dates matter, not automatic signoff |
| Platform launch reference | [PLATFORM_LAUNCH_READINESS.md](PLATFORM_LAUNCH_READINESS.md) | Supporting audit rationale; actionable priorities live only in TODO.md |
| **Single work tracker and roadmap** | [TODO.md](TODO.md) | **Only maintained actionable To-Do**: pilot gates, verification, and future backlog |
| Architecture / product intent | [PRODUCT_CHARTER.md](PRODUCT_CHARTER.md), [VISUAL_FIRST_ARCHITECTURE.md](VISUAL_FIRST_ARCHITECTURE.md) | Product scope and implementation principles |
| Mobile integration | [Phase 4 acceptance](NATIVE_PHASE4_ACCEPTANCE.md), [MOBILE_V1_FOLLOWUPS.md](MOBILE_V1_FOLLOWUPS.md), [mobile parity](../mobile/docs/CASTING_TASKS_PARITY.md) | Native parity, actual test evidence and explicit device/push limitations |
| Production lifecycle | [PRODUCTION_LIFECYCLE_V2.md](PRODUCTION_LIFECYCLE_V2.md) | Stage, lifecycle and approval behavior |
| Incident perspective requests | [SAFEGUARDING_STATEMENT_REQUESTS.md](SAFEGUARDING_STATEMENT_REQUESTS.md) | Confidential third-party case statements, invitation workflow and release gates |
| Guardian safeguarded alerts | [GUARDIAN_SAFEGUARDED_NOTIFICATION_MUTE.md](GUARDIAN_SAFEGUARDED_NOTIFICATION_MUTE.md) | Guardian-only push mute, safeguarding boundaries and QA |
| Web and Expo video playback | [VIDEO_PLAYBACK_WEB_MOBILE.md](VIDEO_PLAYBACK_WEB_MOBILE.md) | Native playback, secured mobile streaming and deployment checks |
| Rehearsal video library | [REHEARSAL_VIDEO_LIBRARY.md](REHEARSAL_VIDEO_LIBRARY.md) | Bluehost-local production video uploads, privacy and storage operations |
| Production intake support needs | [PRODUCTION_INTAKE_SUPPORT_NEEDS.md](PRODUCTION_INTAKE_SUPPORT_NEEDS.md) | Private allergies/accommodations, permission-gated production summary, migration and QA |
| Account DOB / audition age eligibility | [ACCOUNT_DOB_AUDITION_ELIGIBILITY.md](ACCOUNT_DOB_AUDITION_ELIGIBILITY.md) | Required contact fields, student DOB ownership, age rules, guest fallback and staging release gates |
| Casting / published Playbill | [CASTING_ROLES_ROLLOUT.md](CASTING_ROLES_ROLLOUT.md), [CASTING_OFFER_WORKFLOW.md](CASTING_OFFER_WORKFLOW.md), [PLAYBILL_PUBLICATION.md](PLAYBILL_PUBLICATION.md), [MANUAL_PARTICIPANTS.md](MANUAL_PARTICIPANTS.md) | Roles, offers, publication and manual records |
| Tasks / guardians / Volunteer Leads | [PRODUCTION_TASKS_EXTRACTION.md](PRODUCTION_TASKS_EXTRACTION.md), [PRODUCTION_TODO_ASSIGNMENTS.md](PRODUCTION_TODO_ASSIGNMENTS.md) | /production/tasks and individual completion |
| Attendance / late pickups | [LATE_PICKUP_WORKFLOW.md](LATE_PICKUP_WORKFLOW.md) | Current late-pickup design and acceptance |
| Email operations | [EMAIL_DELIVERY.md](EMAIL_DELIVERY.md), [EMAIL_AUTOMATION_ROLLOUT.md](EMAIL_AUTOMATION_ROLLOUT.md) | Mail queue, worker, opt-in activation, stale email safety |
| Hosting incident | [HOST_INCIDENT_RESPONSE.md](HOST_INCIDENT_RESPONSE.md) | Incident response and disaster recovery work |
| UX direction — approved | [WARM_BACKSTAGE_DESIGN_STANDARD.md](WARM_BACKSTAGE_DESIGN_STANDARD.md) | B2 Warm Backstage decision; B3 accents; rules for feature-first, design-aware delivery |
| UX / IA | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), [NAVIGATION_IA.md](NAVIGATION_IA.md), [UI_OPTIMIZATION_AUDIT.md](UI_OPTIMIZATION_AUDIT.md) | Existing implemented tokens/primitives, navigation guidance and historical UI findings; read alongside approved B2 direction |

## Current delta relative to older notes

- Production **Readiness** now covers automated signals and links to **Tasks**; task creation and per-person completion are at `/production/tasks`. Any doc implying the full checklist editor is still on Readiness is historical.
- Casting includes production-role multi-select, distinct callback/cast lists, private working drawers, manual participant support, and authorized student/guardian offer responses. Pre-role-library `Next — Casting` language in the original TODO predates these builds.
- Attendance records editable arrival time; late checkout is in each student's **More** section with time-only UI. Server stores full timestamps.
- Late-pickup workflow has advanced beyond the original [LATE_PICKUP_CODEX_HANDOFF.md](LATE_PICKUP_CODEX_HANDOFF.md) specification. Consult [LATE_PICKUP_WORKFLOW.md](LATE_PICKUP_WORKFLOW.md) for newer implementation/acceptance evidence; the handoff is retained only as historical requirements.
- Native app has a student/guardian Action Center for casting offers and To-Dos, but staff creation/editing parity is documented as incomplete.
- Volunteer Lead currently affects **production task owner eligibility only**. Do not infer volunteer-shift authority, training permissions, financial enforcement, or broader administration.
- Production deployment status and operational approval **cannot be inferred from GitHub commits**. Use the pilot report plus real deployment evidence.

## Historical records
`BUILD_001.md`, earlier audit checkpoints, rollout specifications and old readiness percentages remain for chronology and traceability. They should not be read as current acceptance decisions. Prefer updating the current source and linking historical notes rather than deleting evidence.

## Documentation maintenance rule
When work changes, update TODO.md as the single actionable tracker, then the relevant feature/evidence document and this index only if needed. Supporting launch and pilot documents keep dated findings, not duplicate active checklists. Never replace untested claims with “production ready.”
