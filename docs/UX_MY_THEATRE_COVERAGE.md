# My Theatre UX coverage

Baseline `7de9adb`, existing `audit/board-pilot-readiness`, October 29, 2026. Latest account summary/dialog work is preserved. Actual first-dispatch owners below take precedence over dormant legacy renderers.

## Route inventory and implementation tracking

| Route | Actual owner | Purpose | Batch/status | Shared UI | Responsive / accessibility | Functional evidence |
|---|---|---|---|---|---|---|
| `/my-theatre` | `MyTheatreExperience` | Hub, household and personal production focus | B / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | 30 MySQL persona assertions; authenticated HTTP; 11 active browser personas |
| `/app` | `HomeExperience` | Home / Action Center entry | A / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/family-hub` | `FamilyDashboardExperience` | Household overview and alternate entry | A / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/parent` | `FamilyDashboardExperience` | Household overview and alternate entry | A / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/onboarding` | `PlatformOnboardingExperience` | Household overview and alternate entry | A / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/family/manage` | `PlatformOnboardingExperience` | Household overview and alternate entry | A / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/calendar` | `CalendarExperience` | Month/week/agenda and event detail | C / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Household/group MySQL checks; authorized and denied event-ID HTTP; month/week/agenda/detail renders |
| `/schedule` | `ProductionExperience` | Existing production schedule | C / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/cast` | `MemberCastExperience` | Published cast and approved team credits | C / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated persona HTTP; published-data services; real Student/Adult/guardian offer policies |
| `/notifications` | `NotificationExperience` | Casting offers, personal tasks and notices | C / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated persona HTTP; published-data services; real Student/Adult/guardian offer policies |
| `/forms` | `FamilyFormsExperience` | Assignments, completion and response history | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Real guardian submission; closed POST/history and revoked legacy-recipient HTTP checks |
| `/forms/view` | `FamilyFormsExperience` | Assignments, completion and response history | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Real guardian submission; closed POST/history and revoked legacy-recipient HTTP checks |
| `/volunteer-readiness` | `VolunteerExperience` | Readiness, opportunities and commitment detail | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/volunteer-shifts` | `VolunteerExperience` | Readiness, opportunities and commitment detail | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Real signup/cancel HTTP; eligibility services retained; concurrency not claimed |
| `/volunteer/shift` | `VolunteerExperience` | Readiness, opportunities and commitment detail | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Real signup/cancel HTTP; eligibility services retained; concurrency not claimed |
| `/volunteer/approvals` | `VolunteerApprovalExperience` | Own approval requests | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/volunteer/history` | `VolunteerServiceRecordExperience` | Own service record and printable certificate | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/volunteer/service-record` | `VolunteerServiceRecordExperience` | Own service record and printable certificate | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/volunteer/training` | `VolunteerDevelopmentExperience` | Training and requirements | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/volunteer/verifications` | `VolunteerVerificationExperience` | Own verified service letters | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/volunteer/verification` | `VolunteerVerificationExperience` | Own verified service letters | D / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/theatre-history` | `TheatreHistoryExperience` | Personal and authorized Student credits | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/theatre-history/resume` | `ActingResumeExperience` | CV and printable credits | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/student-profile` | `StudentProfileExperience` | Shared performer profile, biography and headshot | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Real Adult save HTTP; keyboard disclosure/focus checks; guardian/Student renders |
| `/student-profile/family` | `FamilyPickupExperience` | Child-specific family and pickup workflow | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/family/caregiver-schedule` | `CaregiverLogisticsExperience` | Separate authorized caregiver logistics | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/resources` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Current guardian and former/unrelated denial HTTP; current audience resolver |
| `/resources/view` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Current guardian and former/unrelated denial HTTP; current audience resolver |
| `/files` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated metadata renders; existing RC1 file-delivery checks separate |
| `/files/view` | `AccountLibraryExperience` | First-dispatch organization and production libraries | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated metadata renders; existing RC1 file-delivery checks separate |
| `/videos` | `RehearsalVideoExperience` | Authorized video resources and detail | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/videos/view` | `RehearsalVideoExperience` | Authorized video resources and detail | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/archive` | `ProductionArchiveExperience` | Historical production and authorized Community | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/archive/production` | `ProductionArchiveExperience` | Historical production and authorized Community | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/archive/community` | `ProductionArchiveExperience` | Historical production and authorized Community | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/attendance` | `AttendanceExperience` | Personal/family attendance and absence reporting | C / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/production` | `ProductionWorkspaceExperience` | Existing member production overview; staff mode preserved | C / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |
| `/playbills` | `PlaybillExperience` | Authenticated published program | E / implemented | Shared MemberUI / existing domain components | Chromium 320/390/768/1440; shared focus styles; see F limits | Authenticated route render; retained workflow/protocol contracts; see conditional-state limits |

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

Performer profiles show biography, stage name, skills and the existing authorized headshot first. The original editing form opens in a native disclosure; individual Playbill publication consent remains separate. Family management links target Student profiles rather than an Adult's own profile. Household management shows linked children first; adding a child opens intentionally, while onboarding keeps its purposeful creation step open. History keeps verified credit sources, production dates and CV export with compact program typography. No invented credits or identity migration.

Resource/file lists and details now use the shared member presentation. First-dispatch library authorization resolves current production audiences through ProductionContext, matching the existing download policy rather than trusting a stale guardian membership row. Authorized family access no longer depends on a duplicate guardian roster row. Download/storage routes remain unchanged. Video, archive, Playbill, family/pickup and caregiver destinations retain their authoritative policies and adopt shared presentation; unavailable records render the existing restricted/empty state, without revealing private content.

## Batch F — measured acceptance (October 30, 2026)

All **38 inventoried routes** have successful authenticated Chromium renders at **320, 390, 768 and 1440 CSS pixels**, including separately addressed details and delegated household entry points. The ledger records 385 successful captures across incremental implementations; it excludes invalid-ID/denied attempts and superseded failures. Profile and delegated-household rendering failures found in the first detail pass were fixed and rerendered. File/video details use explicitly synthetic metadata; their screenshots do not prove download/playback. The archived Community view was rendered with an authorized historical member and a correctly configured historical channel audience.

Evidence: [`my-theatre-browser-evidence.json`](../tests/fixtures/my-theatre-browser-evidence.json) records each route, widths, actual personas/query views, source hashes and limitations. Raw local capture sets remain under `/tmp/ctsmd-mytheatre-*`; representative synthetic captures are committed below. The matrix does not promote a parent render into detail coverage.

| Automated evidence | Result | Practical boundary |
|---|---|---|
| PHP syntax and pilot safety suite | Pass, including 52 Operations behavior/protocol contracts and member trailing-slash regression | Source/stub checks are separate from database execution |
| My Theatre MySQL personas | 30 assertions pass | All 12 required scenarios, including disabled account and retained archive history; owned synthetic fixtures only |
| My Theatre real HTTP suite | 131 assertions pass | Real login, 15 active personas, disabled login protection, exact group event authorization, forged context/other-family denial, closed form POST rejection, legacy former-guardian revocation, actual Student offer response under existing active-guardian policy, guardian form submission, Adult profile save, volunteer cancel/signup, caregiver task denial |
| Existing RC1 real HTTP/mobile API | 173 checks pass locally | Existing production lifecycle, file authorization, identity/session and API safeguards; no live account tests |
| Browser interactions/measurements | 24 checks pass | Native disclosure keyboard open/close/focus; seven routes with 200% root-font emulation and reduced-motion media; six shared text-token contrast pairs; long phone title does not overlap utilities |
| Expo TypeScript and integration tests | Pass, 7 integration tests | Static/contracts; no simulator or device run |
| Android / iOS exports | Both pass independently | Bundling is not device, push or distribution acceptance |
| Expo dependency compatibility | Existing failure remains | SDK 58 patch mismatches; no dependency upgrade made |

Shared text contrast samples measured in the browser: ink/paper **15.45:1**, muted/paper **6.37:1**, wine/paper **8.75:1**, ink/ivory **12.94:1**, muted/ivory **5.33:1**, paper/wine **8.75:1**. These exceed the 4.5:1 ordinary-text threshold for the sampled pairs. Legacy pale-gold volunteer capacity text was replaced with the shared ink token. This is **not a complete WCAG 2.2 AA certification**: photographic overlays, every legacy state, all screen readers and every interactive target were not exhaustively measured. Controls inherit the existing 44px minimum and visible focus treatment. Small inline links retain normal link semantics; no blanket claim of 44px links.

The browser persona set includes StudentSingle, StudentMulti, GuardianOne, GuardianTwo, Adult, AdultParent, Volunteer, StaffPerformer, FormerGuardian, Member and HistoryAdult. A separate caregiver relation exercises the limited logistics page. Disabled access is tested through real login/protected-route HTTP rather than a development identity bypass. Representative long production names, empty histories, overlapping cast/guardian/team contexts, cancellation, closed responses and current plus archived participation are covered.

### Rendered examples

- [Household desktop](assets/my-theatre/household-desktop.jpg) / [phone](assets/my-theatre/household-phone.jpg)
- [Forms list](assets/my-theatre/forms-desktop.jpg) / [closed response history](assets/my-theatre/closed-form-phone.jpg)
- [Performer profile](assets/my-theatre/profile-phone.jpg) / [volunteer commitment](assets/my-theatre/volunteer-phone.jpg)
- [Theatre History](assets/my-theatre/history-desktop.jpg) / [authorized file detail](assets/my-theatre/file-phone.jpg)

### Design judgment, separate from measurements

The hub now answers participation, next event, required action and theatre information through a compact hierarchy. Warm neutral surfaces and burgundy actions retain the established identity; editorial production titles and verified credits add theatre character. Attention leads on phones. Subject/context labels explain overlapping responsibilities without multiplying production cards. These are design judgments, not measured task-completion improvements; family usability sessions remain useful.

### Native/API review and remaining limits

Reviewed existing Expo Theatre/tab and API consumers. New web read models reuse backend authorization; CalendarService/ScheduleAudience personal mode is optional and defaults to existing behavior, preserving API consumers. Offer DTO fields are additive. Native Theatre retains its existing operational/discovery behavior for Staff; it does not yet reproduce the new web household/person filter. Existing native guardian structured-form and pickup-editing gaps remain fail-closed. No Expo screen rewrite or duplicate business logic.

The local `--platform all` exploratory export also attempted web and failed because react-native-web is not installed. Required Android and iOS exports then passed separately. Online Expo compatibility currently requests compatible patches for expo, expo-constants, expo-font, expo-linking, expo-notifications, expo-router, expo-video and react-native. This known dependency gate remains open; builds alone do not close it.

Remaining acceptance: physical phones/tablets, Safari/Firefox, VoiceOver/TalkBack/NVDA, real browser zoom/OS font settings, virtual keyboards, populated conditional training/approval/publication states, media playback, and hosted Apache/TLS. The 200% browser check changes the root font, not all fixed-pixel legacy type or OS text sizing. Volunteer approval/capacity races and concurrent form closure remain existing RC1 release gates. Default-demo schema prerequisites and Playbill Media synchronization remain separately documented dependencies. This phase did not migrate the default demo, change production data, deploy, activate workers or verify push delivery.

### Repeatable verification

Use an explicitly isolated `ctsmd_rc1_*` schema with existing RC1 bootstrap/preflight instructions. Set `APP_ENV=rc1-test DB_HOST=127.0.0.1 DB_PORT=<local-port> DB_DATABASE=<isolated-schema>` and run:

```sh
php tests/my_theatre_mysql.php
php tests/my_theatre_http.php
php tests/rc1_acceptance.php
php tests/member_ui_trailing_slash.php
php tests/ux_operations_contract.php
```

For optional browser fixtures, run `tests/create_my_theatre_fixture.php`, then `tests/extend_my_theatre_fixture.php`; credentials are not exported. Use the existing local-only identity switch against that same isolated database. Run `tests/my_theatre_browser.mjs` with `CTSMD_BROWSER_LIB` pointing to the installed Taste browser library, `CTSMD_UX_BASE_URL` set to an explicit loopback URL/port, and persona/route/width environment filters. `tests/my_theatre_interactions.mjs` verifies the representative interactions. Fixture IDs come from the temporary metadata, never hardcoded production IDs. Retire synthetic accounts with `retireMyTheatreFixture` when no longer needed; retain their history.

### CI evidence

- [PHP safety, final implementation c31590c](https://github.com/arwentz01/CTSMD/actions/runs/41060207419): passed.
- [Isolated MySQL/HTTP RC1, final implementation c31590c](https://github.com/arwentz01/CTSMD/actions/runs/41060207412): passed, including the My Theatre suites.
- [Expo CI, implementation 78df6f5](https://github.com/arwentz01/CTSMD/actions/runs/41059543277): TypeScript, native integration and Android/iOS exports passed; dependency compatibility step failed. This is a release limitation, not a successful overall Expo workflow.

Final form-history and intentional household-disclosure follow-ups are independently rendered, locally verified and included in passing implementation c31590c CI above. Later documentation/evidence-only commits do not change runtime code.
