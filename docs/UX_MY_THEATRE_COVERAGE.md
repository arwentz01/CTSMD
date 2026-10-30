# My Theatre UX coverage

Baseline `7de9adb`, existing `audit/board-pilot-readiness`, October 29, 2026. Latest account summary/dialog work is preserved. Actual first-dispatch owners below take precedence over dormant legacy renderers.

## Route inventory and implementation tracking

| Route | Actual owner | Purpose | Batch/status | Shared UI | Responsive / accessibility | Functional evidence |
|---|---|---|---|---|---|---|
| `/my-theatre` | `MyTheatreExperience` | Hub, household and personal production focus | B / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/app` | `HomeExperience` | Home / Action Center entry | A / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/family-hub` | `FamilyDashboardExperience` | Household overview and alternate entry | A / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/parent` | `FamilyDashboardExperience` | Household overview and alternate entry | A / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/onboarding` | `FamilyDashboardExperience` | Household overview and alternate entry | A / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/family/manage` | `FamilyDashboardExperience` | Household overview and alternate entry | A / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/calendar` | `CalendarExperience` | Month/week/agenda and event detail | C / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/schedule` | `ProductionExperience` | Existing production schedule | C / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/cast` | `MemberCastExperience` | Published cast and approved team credits | C / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/notifications` | `NotificationExperience` | Casting offers, personal tasks and notices | C / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/forms` | `FamilyFormsExperience` | Assignments, completion and response history | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/forms/view` | `FamilyFormsExperience` | Assignments, completion and response history | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer-readiness` | `VolunteerExperience` | Readiness, opportunities and commitment detail | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer-shifts` | `VolunteerExperience` | Readiness, opportunities and commitment detail | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer/shift` | `VolunteerExperience` | Readiness, opportunities and commitment detail | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer/approvals` | `VolunteerApprovalExperience` | Own approval requests | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer/history` | `VolunteerServiceRecordExperience` | Own service record and printable certificate | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer/service-record` | `VolunteerServiceRecordExperience` | Own service record and printable certificate | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer/training` | `VolunteerDevelopmentExperience` | Training and requirements | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer/verifications` | `VolunteerVerificationExperience` | Own verified service letters | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/volunteer/verification` | `VolunteerVerificationExperience` | Own verified service letters | D / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/theatre-history` | `TheatreHistoryExperience` | Personal and authorized Student credits | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/theatre-history/resume` | `ActingResumeExperience` | CV and printable credits | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/student-profile` | `StudentProfileExperience` | Shared performer profile, biography and headshot | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/student-profile/family` | `FamilyPickupExperience` | Child-specific family and pickup workflow | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/family/caregiver-schedule` | `CaregiverLogisticsExperience` | Separate authorized caregiver logistics | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/resources` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/resources/view` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/files` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/files/view` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/videos` | `RehearsalVideoExperience` | Authorized video resources and detail | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/videos/view` | `RehearsalVideoExperience` | Authorized video resources and detail | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/archive` | `ProductionArchiveExperience` | Historical production and authorized Community | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/archive/production` | `ProductionArchiveExperience` | Historical production and authorized Community | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/archive/community` | `ProductionArchiveExperience` | Historical production and authorized Community | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/attendance` | `AttendanceExperience` | Personal/family attendance and absence reporting | C / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/production` | `ProductionWorkspaceExperience` | Existing member production overview; staff mode preserved | C / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |
| `/playbills` | `PlaybillExperience` | Authenticated published program | E / inventoried | Member patterns planned | Pending authenticated verification | Existing backend retained; regression pending |

## Nonvisual and linked boundaries

`/calendar/feed`, `/student-profile/headshot`, `/files/download`, `/member-resources/download`, `/archive/file`, `/videos/play`, `/videos/archive-download` remain existing authorized byte/feed endpoints, not replacement UI. `/playbill`, assets, QR and print-sign retain the existing public program identity. Community announcement destinations remain authoritative; My Theatre shows a concise authorized update list, not another Community feed. Staff schedule edit, roster/casting management, forms administration and volunteer operations are outside this member redesign. Shadowed legacy routes are not counted as extra completed screens.

## Audit findings and constraints

- The existing hub is a grid of navigation cards with no personal priorities; it uses staff-wide active-production enumeration. Personal participation must be resolved separately without weakening staff or member authorization.
- Overlapping Adult/guardian contexts must be retained while production/event/action rows deduplicate by underlying ID. A context filter is a filter over authorized subjects, never an access grant.
- Reuse current task, casting offer, family form, schedule audience, archive, notification, resource and volunteer policies. Team/volunteer involvement alone must not unlock production-private information.
- Member-specific scoped patterns consume existing `WorkspaceUI` and `--ux-*` tokens. No new palette, font dependency or second application. Current account detail remains information-first with intentional edits.
- No new migration, production write, deployment, worker activation or Expo upgrade is authorized by this UI work. Default-demo schema limitations remain explicitly separate from isolated verification.

## Verification ledger

Batch results, before/after captures, persona coverage, measured checks and deferred refinements will be appended as implementation proceeds. No screen is marked verified merely because its parent page was styled.

## Batch B — personal/household hub

Replaced the navigation-card grid with a prioritized action list, deduplicated production rows, upcoming events, concise authorized production updates and context-aware links. Personal participation is separate from staff-wide operational visibility. A household context is validated against current parent/guardian links and Student roles; self-only events exclude inherited child calls. Team and volunteer credits describe involvement without granting private production access. Upcoming inactive-planning participation is visible as metadata without opening its unpublished schedule.

Initial verification: 30 real MySQL assertions across the required persona combinations; existing 173 HTTP/API acceptance checks and action-center source checks pass locally. Authenticated hub captures at 1440/390 for two multi-role households show no page overflow or server errors. Final full-route/role/browser evidence remains Batch F work. No migration or native API contract change was needed.

## Batch C — schedules, casting and member production information

The calendar now has an explicit personal/household mode and an authorized event-detail query. Member mode does not inherit staff-wide visibility, and current guardian/subject checks are reapplied for direct event IDs. Month, week and agenda preserve context; production-wide events are not labeled as assigned individual calls. Calendar subscriptions are intentionally disclosed rather than permanently exposing a private URL. The operational Staff calendar and existing edit/cancel handlers remain intact.

Published Cast & company uses personal/household productions and reviewed publication snapshots. Team functions group by stable account identity where the snapshot supplies it; legacy name-only records are not merged by a guessed name match. Only published program team credits are displayed. Notification offers keep the existing accept/decline workflow. Student guidance explains the active-guardian requirement; final verification reconciled the controls against CASTING_OFFER_WORKFLOW.md and the unchanged authoritative service, which permits the Student or their current guardian to respond. Adult roles never bypass Student relationship checks. Submitted forms awaiting review are informational rather than active tasks.

Shared member presentation was adopted on Home/household alternate entry points, member schedule, notifications, attendance and the non-staff production overview. Staff production management retains its existing rendering mode. Initial authenticated Chromium: five C routes × two personas × desktop/phone, all complete with no overflow/server errors. Direct event/role/POST acceptance and final wider-device checks remain in Batch F.

## Batch D — forms and volunteer workflows

Assignments now offer All, Needs response and Submission history views with a participant filter. Closed definitions and closed productions retain readable responses without a completion form. Submitted responses awaiting review are informational; editable responses open intentionally in a native disclosure. Existing signatures, acknowledgments, review states, CSRF and write guards remain authoritative. GET can opt into closed history; POST cannot retrieve a closed definition for submission. Legacy guardian-addressed Student assignments now require a current parent/guardian relationship instead of trusting the recipient column. Checklist completion likewise requires parent/guardian rather than caregiver status.

Volunteer lists separate owned commitments and pending requests from available opportunities. Waitlisted records explicitly do not represent confirmed places. Existing capacity, training, approval, cancellation and eligibility services are unchanged. Readiness, own approval requests, training, service records and verification destinations adopt the same member surfaces, controls, breadcrumb and responsive table wrapper. Printable records retain their original print mode.

## Batch E — profiles, credits and member material

Performer profiles show biography, stage name, skills and the existing authorized headshot first. The original editing form opens in a native disclosure; individual Playbill publication consent remains separate. Family management links target Student profiles rather than an Adult's own profile. History keeps verified credit sources, production dates and CV export with compact program typography. No invented credits or identity migration.

Resource/file lists and details now use the shared member presentation. First-dispatch library authorization resolves current production audiences through ProductionContext, matching the existing download policy rather than trusting a stale guardian membership row. Authorized family access no longer depends on a duplicate guardian roster row. Download/storage routes remain unchanged. Video, archive, Playbill, family/pickup and caregiver destinations retain their authoritative policies and adopt shared presentation; unavailable records render the existing restricted/empty state, without revealing private content.
