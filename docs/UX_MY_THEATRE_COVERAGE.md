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
