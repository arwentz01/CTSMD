# CTSMD Connect: UX, navigation and usability audit

**Date:** October 30, 2026 (America/New_York)
**Repository / branch:** arwentz01/CTSMD · `audit/board-pilot-readiness`
**Audited source baseline:** `1ce1ba2` (current local revision, including the installed audit skill)
**Method:** installed `.agents/skills/uxui-audit/SKILL.md`, workflow, capture guide, all 13 dimensions, reporting template and regression-guard guidance. Rendered inspection used the browser tooling's Playwright locators, accessibility snapshots, screenshots, read-only DOM measurements and keyboard input. Source inspection supplied the route inventory and permission context, not substitutes for rendered testing.
**Disposition:** audit only. No application source, schema, permission policy, production data or deployment changes. Recommendations below require approval before implementation.

## Executive assessment

CTSMD Connect has substantial working capabilities and a coherent operational presentation. Representative local tasks can succeed: a Student completed an assigned task; a Volunteer cancelled and rejoined a shift; Staff created a rehearsal and a private Playbill; an Administrator created and edited a planning production. The application often provides good confirmation and clearly separates private decisions from publication.

The principal weakness is **continuity between capabilities**. Users must know which of several hubs owns a task, which meaning of “production” is currently active, and whether an action belongs under Notifications, My Theatre, Family, Production or Operations. Several labels promise a different destination from the one they open. Mobile layouts amplify this burden by putting summaries before actionable work and obscuring page titles.

Prioritize correct destinations, consistent action states, preserved person/production context and useful recovery paths. A new visual identity, new product modules or weaker safety checks would not solve these problems. This is an expert evaluation, not a measured usability study with representative humans; no completion-rate or satisfaction score is claimed.

## Scope, evidence and safety

- Public discovery, sign-in and registration entry were inspected on [ctsmd.me](https://ctsmd.me). Production account pages and private messages were never opened on the live site.
- Authenticated journeys used the current checkout at `http://127.0.0.1:8097`, an isolated migrated loopback `ctsmd_rc1_*` MySQL schema, existing fixture creation helpers and real password sign-in. No identity-switch endpoint, session fabrication or safeguarding bypass was used.
- All names, emails, dates of birth, relationships and program content in authenticated screenshots are synthetic. Fixture suffixes and unusually long production titles are deliberate test data, not production content defects.
- Test writes were limited to synthetic task completion, volunteer cancellation/rejoin, private program metadata/biography/review status, a rehearsal and its unsent communication draft, and planning-production creation/details. No authorization selections, family/pickup permissions, casting results, publication switches or notification workers were changed. Existing fixture helpers establish synthetic roles for setup; they do not change real accounts.
- After inspection, all audit fixture accounts were disabled and bearer tokens revoked; an independent query counted **zero active audit accounts**. Audit-owned productions, including the newly created planning production, were retired using existing fixture cleanup. Historical records were retained. The temporary server was stopped and its credentials file removed.
- Primary viewports: **1440×900 desktop, 390×844 mobile, 768×1024 tablet**. This was a representative matrix, not every route at every size. Desktop/mobile coverage spans every requested persona category; “production manager” was exercised using an Administrator's existing production-management authority, not a separately provisioned manager role. Tablet coverage sampled calendar detail and production management/artwork.
- **64 screenshots** are stored in [ux-navigation-audit-evidence](ux-navigation-audit-evidence/). Some capture a viewport and others a full scrolling document. Full-page browser captures can repeat a fixed bottom navigation across a stitched image; this is not evidence of duplicate navigation in the application.
- The default local MAMP database was not migrated or used. Source/hosted deployment parity is not assumed. Authenticated findings describe the local baseline, not an assertion that the same revision is deployed.
- Expected UI language: English. Brand yardstick: existing warm burgundy/ivory operational system, restrained theatre typography and production artwork. Cosmetic preferences were deprioritized.
- No accepted-findings baseline was present; suppressed findings: **0**.

## Strengths to preserve

1. **Separate decisions, communications and publication.** Casting explains Review → Assign → Communicate → Publish; the participant drawer distinguishes saving a decision from sending a result. The Studio distinguishes saved private preview from audience access. See [casting drawer](ux-navigation-audit-evidence/staff-casting-dialog-desktop.jpg) and [Studio preview](ux-navigation-audit-evidence/staff-studio-publish-desktop.jpg).
2. **Useful completion feedback.** Student task completion removes the task and reduces the attention count; shift cancellation/rejoin updates state and remaining slots. [Task completed](ux-navigation-audit-evidence/student-task-completed-tablet.jpg), [shift cancellation](ux-navigation-audit-evidence/volunteer-cancelled-mobile.jpg), [signup confirmation](ux-navigation-audit-evidence/volunteer-confirmed-mobile.jpg).
3. **Good form-state distinctions on the Forms page.** Needs response, awaiting staff review and read-only history are explicitly different; guardian submission explains who the form belongs to and who is recorded as submitter. [Forms](ux-navigation-audit-evidence/guardian-forms-mobile.jpg), [form details](ux-navigation-audit-evidence/guardian-form-detail-mobile.jpg).
4. **Audience-aware scheduling.** Whole-production versus group calls, group sizes and guardian inheritance are explained before creation. Event detail preserves a return to the schedule. [Creation](ux-navigation-audit-evidence/staff-schedule-create-desktop.jpg), [call detail](ux-navigation-audit-evidence/student-call-detail-mobile.jpg).
5. **Contextual account editing.** Account overview distinguishes membership, authorization roles, sign-in lifecycle and family relationships. Role editing opens a focused dialog with responsibility descriptions. No roles were saved. [Account](ux-navigation-audit-evidence/admin-account-detail-desktop.jpg), [roles](ux-navigation-audit-evidence/admin-roles-dialog-desktop.jpg).
6. **Tested keyboard recovery.** Escape closed the mobile navigation sheet, casting drawer and account roles dialog; focus returned to their triggering controls. A Tab inside the roles dialog reached a checkbox with a visible solid outline. These samples do not prove full keyboard accessibility.
7. **Authorization denial remains authoritative.** An out-of-scope Staff account could not open the Studio. Improve the denial experience, not the authorization boundary.
8. **Useful account-wide resources.** A Student followed a production's Resources link into its filtered library and opened the authorized note. No show-switch was needed to discover account-wide material. [Library](ux-navigation-audit-evidence/student-resources-desktop.jpg), [resource](ux-navigation-audit-evidence/student-resource-detail-desktop.jpg).

## Summary and severity

Severity follows the skill's Nielsen 0–4 scale. Mapping to the requested priorities: **S4 Critical**, **S3 High**, **S2 Medium**, **S1 Low**. Priority reflects verified task impact and frequency of the pattern, not implementation effort.

| Severity | Findings |
|---|---:|
| S4 Critical / catastrophic | 0 verified |
| S3 High / major | 10 |
| S2 Medium / minor | 12 |
| S1 Low / cosmetic | 0 separately logged |
| Total | 22 |

No confirmed data-loss or safeguarding breach was observed. This limited audit is not a security signoff. A broken secondary import route is rated S3 because the manual creation path remains available; a correct access denial is a recovery problem, not evidence of unauthorized access.

**Confidence:** High = reproduced in the rendered UI, with screenshot and/or DOM evidence. Medium = visible pattern established, but frequency, terminology comprehension or expected user benefit needs moderated testing. A finding may have high observation confidence and medium impact confidence.

| Dimension | Primary findings | Assessment |
|---|---|---|
| D1 Usability / Nielsen | F04, F18 | Conflicting action ownership; otherwise several good confirmations |
| D2 Accessibility | F17, F22 | Concrete accessible-name defects; broader WCAG review incomplete |
| D3 Visual design | F12 | Attention hierarchy rather than cosmetic styling |
| D4 Content & language | F14, F15 | Lifecycle and audience vocabulary disagree |
| D5 State & data | 0 primary | Empty, filled, pending, read-only and long-title states inspected; loading/offline largely untested |
| D6 Responsive | F05 | Shared title collision; sampled document widths otherwise fit |
| D7 Information architecture | F01, F07, F10, F19 | Most substantial cross-cutting weakness |
| D8 Interaction design | F16 | Registration interaction burden; contextual editing otherwise useful |
| D9 User journey / flow | F02, F03, F13, F20 | Dead end, lost return context and missing task handoffs |
| D10 Cognitive load / onboarding | F09, F11, F21 | Summaries and household guidance displace current work |
| D11 Trust & credibility | 0 primary | Safeguarding/consent transparency is useful; no dark pattern established |
| D12 Error prevention / recovery | F06, F08 | Denial recovery and simultaneous production contexts |
| D13 Performance perception | 0 primary | Loading, latency and web-vitals evaluation deferred; no speed defect established |

Dimensions share effects, but primary counts avoid double counting. All thirteen were considered; a dimension with no primary finding is not a certification that it is defect-free.

## Current information architecture

### Navigation layers

| Layer | Current destinations | Usability consequence |
|---|---|---|
| Public | Welcome, Productions, Auditions & signups, account creation/sign-in; Published Playbills near the homepage bottom | Programs are discoverable from the homepage, but not consistently from a production detail |
| Home | Today, My family, Forms, Notifications; attention, next call, timeline, updates, people/productions | Overlaps both Family and My Theatre |
| Family | Household overview, Manage household, child-specific Family & Pickup, linked profile/history | Child context and return path are not consistently retained |
| My Theatre | Productions, participant/household selector, attention, schedule, resources, published cast, history and volunteer links | Broad participation hub; some tasks still resolve through Notifications |
| Communication | Desktop Community; mobile Channels; Messages; Video Library | Same concept has different navigation labels by device |
| Production | Working production selector; Overview, Schedule, Attendance, Late, Groups, People, Casting, Tasks, Readiness, Production day, Resources, Videos, Files, Updates, Playbill, Playbill media | Up to 16 sibling tabs, plus a directory repeating many destinations |
| Operations | Staff Overview, Production workspace, Account & Access, Community Operations, Resource Operations, Volunteer Operations, Forms Management, Auditions & Signups, Safeguarding, Email Operations | A useful administration boundary, but more directory layers precede actual work |
| Account utilities | Inbox, notifications, account disclosure → My Account, Notification Preferences, Help, Sign Out | Compact and reusable; use stable naming and keyboard semantics |
| Mobile bottom bar | Home, Calendar, Channels, Messages, More for communication-eligible users; alternate Family/Setup/Updates/Account labels for other states | Direct calendar is useful, but Family, My Theatre and staff production switching move into secondary navigation |

### Roles and access: preserve the distinctions

- Administrator and explicit permission checks govern account and organization administration. Production Staff is a role; individual operations additionally use permissions such as `production.manage`, `schedule.manage`, `forms.manage` and `playbill.manage`.
- Guardian and caregiver are relationships, not interchangeable global roles. Current guardian authority must remain distinct from pickup-only permission and caregiver logistics. Former-guardian revocation must remain authoritative.
- Adult Performer is an additional participation role with no administrative grants; a creative/team credit does not grant Staff access.
- Student offer responses remain guardian-authorized. Student messaging restrictions and active guardian inclusion remain intact. Active-account conversations do not need a shared active production.
- The Studio additionally checks active Staff membership for that production, with the existing Administrator exception. Availability in the global Working production selector is not a promise of editorial authority.
- A “production manager” is treated here as a task persona with existing production-management authority, not a newly invented account role.
- Volunteer Lead is implemented as scoped task-owner eligibility/labeling, not a standalone global role or automatic volunteer-management authority. No distinct lead account was available for this audit.

Sources: `src/AccessPolicy.php`, `src/AppNavigation.php`, `src/ProductionContext.php`, `src/PlaybillStudioService.php`, `docs/ADULT_PARTICIPATION_ARCHITECTURE.md`, `docs/PRODUCTION_PEOPLE_REDESIGN.md`, `docs/PRODUCTION_TASKS_EXTRACTION.md`. These are inventory observations, not a comprehensive access-control test.

### Route inventory and dispatch

The current experience registry contains **61 entries, 150 path references and 140 unique registered paths**, excluding earlier public/mobile handlers and fallbacks. Appendix A inventories every registry entry. Public/auth/mobile handler declarations remain separate.

Several later handlers are shadowed by earlier dispatch: `/family-hub`, `/files`, `/files/view`, `/resources`, `/resources/view`, `/volunteer/history`, `/production`, `/production/day`. `/playbills` is handled by PublicExperience before the authenticated registry. Dispatch duplication is a maintenance observation; it becomes a UX finding only where the rendered destination conflicts with the link's promise, as in F01.

## Persona journeys and interaction ledger

**Counting convention:** one link/button activation or selection = one interaction; entering a field = one separate field edit. Native option opening/selection is counted as one semantic selection. Login is setup (two field edits + one submit) and excluded from each task count. Scrolling, screenshots, inspection, viewport changes and automation selector retries are excluded. Counts below describe the recorded segment from its stated starting page, not an optimized theoretical path. They are not timed human task measurements.

| Persona / task | Start → actual path | Interactions recorded | Result and friction |
|---|---|---|---|
| Public: find a current show | `/` → Production details → `/productions/view?id=1` | 1 activation | Detail reached. On mobile the large poster precedes title/dates; no venue/ticket action was present in this record. Availability of missing audience data was not established. |
| Public: account entry | `/` → Sign in → Create a parent/guardian account → `/join` | 2 activations; includes chosen sign-in route | Form reached; no live account was created. Direct homepage Create an account is also present, so this is not a required two-step path. Verification and approval were not tested. |
| Audience: read a program | Local `/playbills` → synthetic published program → Contents open/close → Next | 4 activations to next page; Escape probe had no dismiss effect | Reader and pagination worked. This was a local counterpart with synthetic content, not a live audience-program or print-device test. |
| Guardian: manage child pickup information | `/app` → My family → StudentMulti Family & Pickup → expand pickup-only form → header Back | 4 activations | Fields reached without granting access. Back opened unfiltered Theatre History for StudentB: context lost. No relationship or pickup change submitted. |
| Guardian: required form | `/app` → Forms → StudentForm → Complete this form | 3 activations | Completion control and guardian attribution reached. Agreement acknowledgement/submission was deliberately not executed; this is partial, not a successful form submission. |
| Student: review offer | `/app` → offer Open | 1 activation | Guardian guidance present, but enabled Accept/Decline actions compete with it. No offer response submitted and no authorization bypass attempted. |
| Student: view call details | `/messages` → Calendar → ChildCall | 2 activations | Correct event date, time, location, group and return link reached; tablet detail also inspected. |
| Student: complete assigned task | `/calendar` → notification bell → Mark completed | 2 activations | Completed: success message, task removal and attention count 3→2 observed. Task lives under Notifications rather than a plainly named task destination. |
| Student: messaging | `/app`/bottom bar → Messages → New conversation | 2 activations | Composer reached; recipient availability and restrictions inspected. No message sent, no private conversation content read. |
| Student: casting/resources | `/my-theatre` → published cast; browser Back → production Resources → note | 4 activations including Back | Unpublished cast shows an honest empty state; authorized note opened. No public cast was published for the synthetic production. |
| Staff: start production work | `/app` → select authorized production A → Production | 1 selection + 1 activation | Workspace and switch confirmation reached. Global selector initially chose another show, making explicit context checking necessary. |
| Staff: find Playbill editor | `/production` → Playbill → public directory → browser Back → Playbill media | 3 activations including Back | Editor found only after wrong destination. See F01. |
| Staff: draft a program/profile | Studio → edit program title/public address → Save cover details → bios tab → expand own profile → edit bio → Save program profile → Preview & Publish → Save for review | 6 activations + 3 field edits | Completed through private review state; existing roster profiles appeared, private renderer loaded, save messages observed. No public publication/QR/consent change tested. |
| Staff: rehearsal creation | `/schedule` → Add schedule item → fill title/location → Create schedule item → Updates | 3 activations + 2 field edits | Created; audience count and success shown. Unsent draft found under Updates, without an immediate creation-to-draft link. |
| Staff: import rehearsal schedule | `/schedule` → Import Schedule | 1 activation; 1 browser Back to recover | Blocked: plain Not found. No CSV import task could proceed. |
| Unscoped Staff: editor access | `/app` → Production → Playbill media | 2 activations | Correctly denied. Page has only a generic permission message and no recovery navigation. |
| Volunteer: manage an owned shift | `/app` → Volunteer hub → commitment → Cancel signup → Confirm signup | 4 activations | Completed cancellation and rejoin on own synthetic shift; coverage 3→4→3 and confirmations observed. Opportunity discovery and owned commitments share a useful screen. |
| Manager: create a production | `/production` → Manage productions → fill title/season → Create planning production | 2 activations + 2 field edits | Created under Administrator authority; no activation or public discovery change. Existing Working production stayed on the previous show. |
| Manager: edit production details | New production detail → Manage details → blank title → Save → correct title/edit season → Save | 3 activations + 3 field edits | Native required-field validation blocked blank title; correction saved and success shown. No lifecycle/eligibility changes. |
| Administrator: inspect permissions | `/app` → Operations → Account & Access → search StaffPerformer → account → Edit roles → Tab → Escape | 4 activations + 1 field edit + 2 keypresses | Role dialog inspected and dismissed with restored focus. No role, membership, family or sign-in changes saved. |
| Administrator: form queue | `/admin/operations` → Staff Overview → Missing/review forms → Review queue | 3 activations | Actual submission queue reached after detour through definitions. Submitted form review outcome not changed. |
| Volunteer lead | No dedicated lead account | Not executed | Source establishes task-owner eligibility only. No lead-management workflow claimed. |

Supporting captures are linked in the findings and evidence index. Desktop/mobile observations exist across the persona categories, but the table does not claim every write was repeated at both sizes.

## Top 10 highest-impact findings

### F01 · S3 High · Playbill links send staff to the audience directory

- **Primary dimension / persona / routes:** D7 IA; Staff; `/production` → `/playbills`, versus `/admin/playbill/media` → Studio.
- **Evidence:** [production navigation](ux-navigation-audit-evidence/staff-production-desktop.jpg), [wrong destination](ux-navigation-audit-evidence/staff-playbill-wrong-destination-desktop.jpg), [actual Studio](ux-navigation-audit-evidence/staff-playbill-studio-desktop.jpg). Reproduced by clicking the tab; the workspace also describes `/playbills` as “Digital Playbill workspace.”
- **Current experience / friction:** The app shell disappears into a public directory offering Member sign in. A staff member must backtrack and guess that “media” owns the entire editor.
- **Recommendation / expected impact:** One staff entry named **Playbill Studio** targeting the existing editor; separate **View published program** when available. Removes a misleading detour without changing consent or publication gates.
- **Confidence:** High observed behavior and destination mismatch.

### F02 · S3 High · Import Schedule is an advertised dead end

- **Dimension / persona / routes:** D9 Journey; Staff; `/schedule` → `/production/schedule/import`.
- **Evidence:** [schedule action](ux-navigation-audit-evidence/staff-schedule-desktop.jpg), [Not found destination](ux-navigation-audit-evidence/staff-schedule-import-destination.jpg). Browser Back recovered to Schedule.
- **Current experience / friction:** A prominent peer of Add schedule item opens a bare Not found page with no recovery action. Import task is blocked locally.
- **Recommendation / impact:** Resolve the existing destination if implementation exists, or remove/clearly mark the unavailable entry until it does. Do not build a new import product during a navigation correction. Retain the working manual creation path.
- **Confidence:** High on audited revision; hosted authenticated route not tested. Rated S3, not a whole-application S4 failure.

### F03 · S3 High · Family & Pickup Back loses both origin and selected child

- **Dimension / persona / routes:** D9 Journey; Guardian; `/family-hub` → `/student-profile/family?student=552` → `/theatre-history`.
- **Evidence:** [StudentMulti pickup](ux-navigation-audit-evidence/guardian-pickup-mobile.jpg), [Back destination showing StudentB](ux-navigation-audit-evidence/guardian-back-destination-mobile.jpg).
- **Current experience / friction:** Header Back opens Theatre History instead of the household and drops the Student parameter. The destination defaults to a different authorized child. No unauthorized data was exposed, but the task context is wrong.
- **Recommendation / impact:** Preserve a validated return destination and child context; explicitly label fixed destinations rather than calling them Back. Prevents sibling confusion while retaining per-child authorization.
- **Confidence:** High, reproduced through actual links.

### F04 · S3 High · Home counts awaiting-review forms as user work

- **Dimension / persona / routes:** D1 Usability/system status; Guardian; `/app`, `/forms`.
- **Evidence:** [Home includes UnderReview and two open forms](ux-navigation-audit-evidence/guardian-home-desktop.jpg); [Forms says one needs attention and distinguishes awaiting staff review](ux-navigation-audit-evidence/guardian-forms-mobile.jpg).
- **Current experience / friction:** Users cannot trust that “open,” “needs you” and the attention count mean the same thing. A submitted form implies another response is needed.
- **Recommendation / impact:** Apply the existing Forms distinction consistently: requires my response, awaiting staff review, completed/read-only. Use the same authoritative state resolution across summaries. Reduce unnecessary revisits without suppressing review history.
- **Confidence:** High state mismatch; frequency on real accounts requires follow-up.

### F05 · S3 High · Shared compact headers obscure page identity

- **Dimension / persona / routes:** D6 Responsive; all signed-in personas; `/app`, `/family-hub`, `/calendar`, `/messages`.
- **Evidence:** [Home](ux-navigation-audit-evidence/guardian-home-mobile.jpg), [Family](ux-navigation-audit-evidence/guardian-family-mobile.jpg), [tablet Calendar](ux-navigation-audit-evidence/student-calendar-tablet.jpg), [Messages](ux-navigation-audit-evidence/student-messages-mobile.jpg).
- **Current experience / friction:** The small CTSMD wordmark overlaps the page title at mobile and sampled tablet widths. This directly weakens “Where am I?”
- **Recommendation / impact:** Reserve independent title/utility space; suppress redundant compact branding when needed. Preserve labels, focus indicators and account controls. Benefits the shared shell rather than restyling each screen.
- **Confidence:** High visual observation; no claim of complete screen-reader or zoom failure.

### F06 · S3 High · Correct editor denial gives no useful recovery

- **Dimension / persona / routes:** D12 Error recovery; unscoped Staff; `/production` → `/admin/playbill/media`.
- **Evidence:** [bare permission denial](ux-navigation-audit-evidence/staff-scope-denial-desktop.jpg), compared with [offered navigation](ux-navigation-audit-evidence/staff-production-desktop.jpg).
- **Current experience / friction:** The selector and workspace offer the editor, but denial removes all navigation and says only that Playbill management permission is required. It does not distinguish missing production scope from missing general permission.
- **Recommendation / impact:** Keep the server denial; provide an in-shell explanation and safe return/change-production action. Availability cues may reflect current scoped capability, but never replace backend authorization. Reduces dead-end recovery and unnecessary access requests.
- **Confidence:** High denial/recovery observation; not a finding that more access should be granted.

### F07 · S3 High · A review queue opens form-definition management

- **Dimension / persona / routes:** D7 IA; Staff/Admin; `/staff` → `/admin/forms/manage` → `/admin/forms`.
- **Evidence:** [Missing/review forms queue](ux-navigation-audit-evidence/admin-staff-queues-desktop.jpg), [definition destination](ux-navigation-audit-evidence/admin-form-queue-destination-desktop.jpg), [actual review queue](ux-navigation-audit-evidence/admin-form-review-queue-desktop.jpg).
- **Current experience / friction:** “Review queue” first opens Create once/Assign with context and Settings/Assign controls. A second Review queue link finally shows pending submissions. Missing assignments and submitted reviews are also combined in the originating count.
- **Recommendation / impact:** Route pending-review actions to existing submission review with appropriate context. Name missing-response work separately and link to the existing assignment view. Reduces one detour and clarifies who must act without merging security ownership.
- **Confidence:** High destination mismatch; optimal queue grouping needs staff validation.

### F08 · S3 High · Production creation leaves two simultaneous contexts

- **Dimension / persona / routes:** D12 Error prevention; production manager; `/admin/productions/view?id=201`, global Working production.
- **Evidence:** [creation result](ux-navigation-audit-evidence/manager-production-created-desktop.jpg): new production title in content while the sidebar/selector still identifies the previous production.
- **Current experience / friction:** Detail editing correctly targets the new record, but the persistent Production link targets a different operational show. A user continuing to Schedule or Casting must remember to switch context first. No wrong-production save was performed or demonstrated.
- **Recommendation / impact:** Explicitly distinguish “editing production details” from “working in production,” and provide a deliberate handoff to the newly created show using the existing selection mechanism. Do not silently switch other users' sessions. Lowers context-error risk.
- **Confidence:** High simultaneous contexts; medium prediction of user error.

### F09 · S3 High · Mobile attention is placed after low-action summaries

- **Dimension / persona / routes:** D10 Cognitive load; Guardian/Student; `/app`, `/family-hub`.
- **Evidence:** [Home first screen](ux-navigation-audit-evidence/guardian-home-mobile.jpg), [Family first screen](ux-navigation-audit-evidence/guardian-family-mobile.jpg), [Student Home](ux-navigation-audit-evidence/student-home-mobile.jpg).
- **Current experience / friction:** Introductory copy, household/history CTA, counters and next-call details precede action items. With actual pending work, the first mobile viewport does not reveal the first actionable offer/form. Family similarly prioritizes six counters.
- **Recommendation / impact:** Lead with response-required items and a compact next-call summary; make overview counts secondary. Preserve recipient identity, deadlines and status distinctions. Makes the existing daily task faster to locate.
- **Confidence:** High ordering observation; medium predicted benefit pending usability testing.

### F10 · S3 High · Production navigation is a long, competing directory

- **Dimension / persona / routes:** D7 IA; Staff/manager; `/production` and production subpages.
- **Evidence:** [16-peer navigation with clipped trailing links](ux-navigation-audit-evidence/staff-production-desktop.jpg), [mobile Studio stages](ux-navigation-audit-evidence/staff-studio-mobile.jpg), [mobile full navigation](ux-navigation-audit-evidence/staff-mobile-navigation.jpg).
- **Current experience / friction:** Horizontal tabs extend beyond the visible edge; the workspace repeats many as a second directory. Publishing, resources and operations compete as peers. Mobile Working production switching is hidden in More, while page copy refers to the sidebar.
- **Recommendation / impact:** Group existing destinations by task and make overflow discoverable. Keep a clear production context control on mobile. Validate grouping with staff before changing navigation; hidden visual links are not claimed keyboard-inaccessible.
- **Confidence:** High structural observation; medium proposed grouping/learnability benefit.

## Additional findings

### F11 · S2 Medium · Volunteer Home leads with household setup

- **Dimension / persona / route:** D10 Cognitive load/onboarding; Volunteer; `/app`.
- **Evidence:** [volunteer Home](ux-navigation-audit-evidence/volunteer-home-desktop.jpg): Set up household is the primary heading action despite an owned shift; numerous empty family/production summaries surround it.
- **Friction / recommendation / impact:** An adult volunteering without children is directed toward irrelevant setup. Prioritize their existing commitment and make optional household setup secondary. No new role system is needed. **Confidence:** High observation, medium priority judgment.

### F12 · S2 Medium · Cleared staff queues compete with active work

- **Dimension / persona / route:** D3 Hierarchy; Staff/Admin; `/staff`.
- **Evidence:** [staff queues](ux-navigation-audit-evidence/admin-staff-queues-desktop.jpg): five zero-count queues occupy equal space/actions alongside six forms and four shifts.
- **Friction / recommendation / impact:** Scanning seven blocks to find two active categories increases effort. Prioritize nonzero work; retain cleared and restricted queues in a compact, authorized summary. Do not hide safeguarding visibility. **Confidence:** High layout observation, medium usability hypothesis.

### F13 · S2 Medium · Rehearsal creation does not lead directly to its prepared communication

- **Dimension / persona / routes:** D9 Journey; Staff; `/production/schedule/new` → `/production/edit` → `/production/notices`.
- **Evidence:** [creation includes Prepare communication draft](ux-navigation-audit-evidence/staff-schedule-create-desktop.jpg); [saved result](ux-navigation-audit-evidence/staff-rehearsal-created-desktop.jpg); [unsent draft found separately](ux-navigation-audit-evidence/staff-schedule-drafts-desktop.jpg).
- **Friction / recommendation / impact:** The save confirms the event but offers no direct Review draft next step. Users must know “Updates” owns the prepared draft. Link the existing draft after creation, explicitly saying it is unsent. Preserve audience review and deliberate sending. **Confidence:** High observed handoff; no message delivery tested.

### F14 · S2 Medium · Planning and Upcoming describe the same new production inconsistently

- **Dimension / persona / route:** D4 Content; manager; `/admin/productions/view`.
- **Evidence:** [new production](ux-navigation-audit-evidence/manager-production-created-desktop.jpg): “created in Planning,” “PLANNING,” and Stage “Upcoming.”
- **Friction / recommendation / impact:** Users must infer whether these are different lifecycle concepts. Verify the persisted meanings, then use consistent terminology or explicitly explain the two axes. Do not change lifecycle rules merely to align labels. **Confidence:** High visible inconsistency, medium root-cause interpretation.

### F15 · S2 Medium · Registration implies only parents/guardians belong

- **Dimension / personas / routes:** D4 Content; prospective adult performer/volunteer; `/login`, `/join`.
- **Evidence:** [sign-in link](ux-navigation-audit-evidence/public-login-mobile.jpg), [registration heading](ux-navigation-audit-evidence/public-join-mobile.jpg).
- **Friction / recommendation / impact:** “Create a parent/guardian account” and “Start with the parent or guardian account” exclude ordinary adult participation in their wording. Describe the existing adult account first, and explain guardian household setup where applicable. Preserve child creation and approval safeguards. **Confidence:** High wording observation; abandonment risk is a hypothesis.

### F16 · S2 Medium · Mobile account entry puts substantial reading before the form

- **Dimension / persona / routes:** D8 Interaction; new adult/parent; `/join`, `/login`.
- **Evidence:** [registration](ux-navigation-audit-evidence/public-join-mobile.jpg), [login](ux-navigation-audit-evidence/public-login-mobile.jpg).
- **Friction / recommendation / impact:** Long introductory and access-policy content precedes entry controls; registration includes many personal/contact fields in one sequence. Shorten the introductory block and attach essential guidance to its relevant fields. Review necessity/timing with safeguarding owners rather than removing required information. **Confidence:** High layout, medium completion benefit. Registration submission was not tested.

### F17 · S2 Medium · Three production artwork inputs lack accessible labels

- **Dimension / persona / route:** D2 Accessibility; manager; `/admin/productions/view#production-artwork`.
- **Evidence:** [artwork controls](ux-navigation-audit-evidence/manager-artwork-labels-tablet.jpg), [DOM measurements](ux-navigation-audit-evidence/dom-measurements.json). Each file input has no associated label, aria-label or aria-labelledby; AX names repeat Choose File.
- **Friction / recommendation / impact:** A screen-reader user cannot identify Banner versus Poster versus Thumbnail from the control name. Associate explicit labels and make upload/remove names slot-specific. **Confidence:** High DOM/AX observation. Relevant to WCAG 1.3.1/3.3.2/4.1.2; full conformance adjudication remains outstanding.

### F18 · S2 Medium · Student offer controls compete with guardian-only guidance

- **Dimension / persona / routes:** D1 Consistency; Student; `/notifications?offer=…`, `/my-theatre`.
- **Evidence:** [offer controls](ux-navigation-audit-evidence/student-offer-mobile.jpg), [My Theatre says Review with guardian](ux-navigation-audit-evidence/student-mytheatre-desktop.jpg). The rendered Accept offer button had no disabled attribute.
- **Friction / recommendation / impact:** A Student is told a current guardian must respond while still seeing an enabled self-action. Make the current actor's allowed next step explicit, while preserving server authorization and the guardian workflow. No response was submitted, so this is **not evidence of an authorization bypass**. **Confidence:** High interface contradiction; backend outcome untested.

### F19 · S2 Medium · Personal hubs promise overlapping jobs

- **Dimension / personas / routes:** D7 IA; Guardian/Student/Volunteer; `/app`, `/family-hub`, `/my-theatre`, `/notifications`.
- **Evidence:** [Home](ux-navigation-audit-evidence/guardian-home-desktop.jpg), [Family](ux-navigation-audit-evidence/guardian-family-mobile.jpg), [My Theatre](ux-navigation-audit-evidence/student-mytheatre-desktop.jpg), [Notifications task](ux-navigation-audit-evidence/student-task-completed-tablet.jpg).
- **Friction / recommendation / impact:** Forms, calls, offers and updates reappear under different headings and action language. Establish explicit ownership: Today = current actions, Family = children/household, My Theatre = participation/productions, Notifications = updates with contextual task links. Reuse summaries rather than maintaining competing task definitions. **Confidence:** High overlap, medium findability hypothesis requiring card-sort/tree testing.

### F20 · S2 Medium · Home's generic Open actions discard item specificity

- **Dimension / persona / routes:** D9 Journey; Guardian/Volunteer; `/app` → `/forms` or `/volunteer-shifts`.
- **Evidence:** [Home form actions](ux-navigation-audit-evidence/guardian-home-desktop.jpg), [volunteer Home](ux-navigation-audit-evidence/volunteer-home-desktop.jpg), compared with [specific form list links](ux-navigation-audit-evidence/guardian-forms-mobile.jpg).
- **Friction / recommendation / impact:** A named form/commitment opens a general list, requiring the same item to be found again. Use existing authorized detail destinations and action-specific labels. One fewer search/selection per task; direct links must retain permission checks. **Confidence:** High rendered href observations; volunteer list-to-detail journey was executed.

### F21 · S2 Medium · Form-definition management does not scale to retained history

- **Dimension / persona / route:** D10 Cognitive load; Staff/Admin; `/admin/forms/manage`.
- **Evidence:** [definition list](ux-navigation-audit-evidence/admin-form-queue-destination-desktop.jpg): many active definitions from completed synthetic productions precede current work, without a visible search/filter/pagination affordance in the captured page.
- **Friction / recommendation / impact:** The reused isolated database contains extensive retained test history, exaggerating the volume; the screen nevertheless makes users scan old definitions before reaching their current form. Default to relevant active context and expose existing history through clear filtering/paging, retaining all audit records. Validate with realistic data before prioritizing optimization. **Confidence:** High visible list behavior, medium real-world impact; no LCP/INP/latency finding is claimed.

### F22 · S2 Medium · Homepage poster links are unnamed

- **Dimension / persona / route:** D2 Accessibility; public visitor; `/`.
- **Evidence:** [homepage](ux-navigation-audit-evidence/public-home-desktop.jpg), [rendered link measurements](ux-navigation-audit-evidence/public-link-measurements.json). Two standalone poster anchors contain only an image with empty alt and no accessible label; the title is a separate link.
- **Friction / recommendation / impact:** Keyboard/screen-reader navigation encounters unnamed duplicate destinations. Name the artwork link “View [production title]” or combine it with the title in an appropriately named link. **Confidence:** High DOM and AX evidence; relevant to WCAG 2.4.4/4.1.2.

## Accessibility, responsive and state findings: measured versus deferred

### Executed checks

- DOM inspection sampled **seven surface/viewport states**: Student composer/mobile; Student calendar/tablet; Studio/desktop; Admin account/mobile; production management/mobile; form review queue/desktop; Student resources/desktop. All seven had document scroll width equal to viewport width. This does not rule out clipped inner scrollers or individual overlaps; F05 and F10 demonstrate why document-overflow checks alone are insufficient.
- The sampled production management page exposed **three unnamed file controls**. The public homepage separately exposed **two unnamed poster links**.
- Target-size probes found candidates below 24px in one dimension: composer Cancel link 18px high; tablet Calendar Back to schedule 17px, event links 22px; Admin account disclosures 20–21px; resource breadcrumb 21px. These are **candidates, not confirmed WCAG 2.5.8 failures**: inline-link and target-spacing exceptions need adjudication. Native checkboxes must also be assessed with their clickable labels, not only their visual square.
- An **11-element computed-color sample** on the form review queue produced contrast ratios **5.81:1–15.45:1** using opaque nearest-ancestor backgrounds. Those sampled pairs exceed 4.5:1. Alpha blending, gradients, pseudo-elements, focus states and all other text were not exhaustively evaluated. This is not an axe result or WCAG pass.
- Keyboard: mobile navigation sheet Escape restored focus to More; casting drawer Escape restored Manage/Update; roles dialog opened with focus on its close control, Tab moved to a checkbox with solid outline, and Escape restored Edit roles.
- Native production-title validation blocked an empty required title with “Please fill out this field.” The entered season remained available for correction, and subsequent save succeeded. This validates that specific native-validation path, not server-error preservation generally.
- State samples: empty messages; no published cast; no verified history; pending offer; awaiting-review and closed-production forms; zero staff queues; long titles; private program draft/review; saved/unsaved program-edit indicators; signup/cancellation; permission denial; missing import route.

See [DOM measurement evidence](ux-navigation-audit-evidence/dom-measurements.json). Automation here means DOM/AX probes and real interactions. **axe-core, pa11y and Lighthouse were not run**; no bundled runner was used to inject an accessibility engine into the application. There is no claim of an automated WCAG scan.

### Outstanding human and automated checks

- Complete keyboard-only journeys, reverse tab order, focus trapping at both dialog ends, skip-link activation, 200%/400% zoom, screen-reader announcements and mobile external keyboard.
- Screen-reader evaluation with VoiceOver/NVDA/TalkBack, especially native disclosures, repeated Manage buttons, alerts/statuses and calendar context.
- Full axe/pa11y scan plus manual contrast, focus contrast and target-spacing evaluation. HTML presence of a success message does not prove it was announced.
- Real-device touch/virtual keyboard/safe-area behavior and Windows/Android browser differences; viewport emulation is not hardware testing.
- Network/offline failure, slow submissions, duplicate-click handling, interrupted saves, authentication expiry during editing, and data retention after server validation errors.
- Actual loading states, layout shifts and performance telemetry. Local navigations appeared responsive, but tooling latency is not LCP/INP and cannot establish performance compliance.

## Proposed information architecture: approval proposal only

The proposal groups **existing capabilities**. It does not add routes, permissions, a new task engine, a theme engine or a new notification system.

| Top-level intent | Existing destinations to organize beneath it | Ownership rule |
|---|---|---|
| **Today** | Response-required work, next call, current changes | A consistent summary; link directly to authorized task detail |
| **My Theatre** | My productions, calendar, cast/company, forms/tasks, resources, volunteer activity, performer profile/history | Participation view across shows; do not confuse it with staff administration |
| **Family** (authorized accounts) | Household setup, each child, relationships, pickup, child's profile/forms | Person context persists through detail and return paths; caregiver/guardian authority remains distinct |
| **Community** | Existing channels and appropriate announcements | Use the same concept label on desktop/mobile; retain channel scopes and moderation |
| **Messages** | Inbox and safeguarded conversation creation | Account-level conversations; do not reintroduce a shared-production prerequisite |
| **Production workspace** (authorized staff) | Contextual groups below | Always identify the selected operational production and permission scope |
| **Operations** (permission-filtered) | Cross-production queues; Accounts & Access; Auditions & Signups; Forms; Volunteer administration; Community moderation; Resources; Safeguarding; Email | Organization-level work separated from personal participation and one-show work |
| **Account utilities** | My Account, notification preferences, help, sign out | Remain in the reusable account menu |

### Production workspace grouping to validate

- **Overview:** current stage, next operations and outstanding work.
- **Company:** People, Casting, Groups, linked profile editing. Do not merge creative credits with Staff authorization.
- **Rehearsals & show day:** Schedule, Attendance, late arrivals/pickups and Production day; retain their existing authority boundaries.
- **Tasks & readiness:** assignments and readiness signals, with clear ownership and explanatory status.
- **Resources:** discover existing links, files and videos together, while preserving distinct storage/playback authorization and implementation.
- **Publishing & updates:** Playbill Studio and production communications as distinct explicit publication actions.

Keep the global scope difference explicit: personal/household, selected production and organization-wide operations. Grouping must not imply that every viewer can perform every action within a group.

### Consolidation opportunities

1. One set of attention-state definitions across Home, Family, My Theatre and Notifications.
2. One Studio entry; separate staff editing from audience reading.
3. One visible vocabulary for Community/Channels and lifecycle states, with existing aliases retained where compatibility requires them.
4. One consistent return-context pattern for person, production, filter and originating task.
5. A coherent existing form lifecycle: definition → assignment → response → review → history, with task-specific destinations rather than a generic module directory.
6. Consistent contextual editor conventions: clear trigger names, Cancel/close, Escape/focus return, saved-state feedback and validation recovery. Preserve explicit sending/publication decisions.
7. Consolidated resource discovery, not merged storage/security systems.

### Hypotheses requiring user testing

The overlap between Home/Family/My Theatre is observed; the best grouping is not proven. Conduct short tree tests with guardians, Students, volunteers and staff using tasks such as “find tomorrow's call,” “change a child's pickup person,” “review a form,” and “edit a program.” Test the proposed Company/Rehearsals/Publishing labels before changing navigation. Do not use developer familiarity as evidence that terminology is understandable. Measure first correct destination, wrong turns, completion and subjective confidence with real users; no such metrics were collected in this audit.

## Prioritized remediation roadmap

Each phase is a coherent approval unit. No phase has been implemented.

### Phase 1: repair task destinations, context and recovery

**Scope:** F01–F08, F17, F22. Correct Playbill and form-review destinations; remove/fix the unavailable import entry; preserve child/back context; align response-required states; repair compact header layout; explain denied access within safe navigation; distinguish production detail/working context; label uploads and poster links.

**Acceptance gate:** representative Guardian, Student, Staff and Admin can follow the corrected routes without losing context; scoped denials still return no protected content; current guardian checks, publication gates, lifecycle restrictions and audit records remain unchanged. Re-capture the same evidence states and attach focused regression results.

### Phase 2: clarify personal and staff work ownership

**Scope:** F09–F12, F15–F16, F18–F20. Approve task-based grouping; prioritize current mobile actions; tailor existing empty/setup guidance to the account; distinguish Student review from guardian response; use direct detail links; align terms across devices.

**Acceptance gate:** moderated tree/journey testing supports the grouping; primary tasks are discoverable without knowing internal module names. Compare wrong turns and interactions against this ledger rather than making an unsupported global click-reduction claim.

### Phase 3: close cross-functional handoffs and retained-data friction

**Scope:** F13–F14, F21 plus existing form/resource organization. Add contextual links to existing prepared drafts; clarify lifecycle labels; filter/paginate retained definition history using approved existing patterns; standardize editor return/feedback conventions.

**Acceptance gate:** schedule → review draft → return to schedule retains show/audience; no message sends automatically; historical production/form records remain accessible and read-only where required.

### Phase 4: accessibility, resilience and acceptance completion

Run full automated accessibility scans and human keyboard/screen-reader/device journeys. Test expired sessions, connectivity loss, invalid input, duplicate actions and unsaved work. Complete parent registration/verification, safeguarded message sending and consent/publication journeys in controlled nonproduction environments with appropriate test authorization.

**Acceptance gate:** publish a coverage matrix separating automated results, human verification and deferred blockers; resolve verified severe issues before claiming pilot UX signoff. This audit alone does not authorize deployment.

## Recommended automated regression tests (proposal, not added)

Use the existing PHP guarded MySQL/HTTP fixture approach for authoritative state and Playwright for rendered journeys. Tests should preserve existing security assertions rather than duplicate implementation details.

| Guard | Smallest meaningful assertion |
|---|---|
| Playbill entry | Authorized staff click the production editor link and reach `/admin/playbill` with the correct production; public reading remains separate and drafts remain unavailable anonymously |
| Navigation route health | Follow advertised Schedule actions; no enabled link ends in a raw Not found/500 page; unavailable workflows are clearly absent/disabled |
| Child return context | Guardian with two children opens Child B from Family, enters Pickup, returns to Family/Child B; never defaults to Child A |
| Consistent attention | Seed missing, submitted, awaiting-review, approved and closed assignments; Home, Family, My Theatre and Forms agree about who must act |
| Direct task destinations | Open on a named form/shift reaches that authorized detail instead of a generic index |
| Scoped denial | Unscoped Staff gets a denial with safe recovery, while direct editor/preview/assets remain denied; no permission widening |
| Review queue | Staff Missing/review destination opens the correct existing pending-submission context; definition management stays separately labelled |
| Working production | New production detail and current operational context are explicit; following a deliberate context handoff makes subsequent schedule actions target the intended show |
| Compact shell geometry | At 390 and 768px, brand/title/utility bounding boxes do not overlap; title remains identifiable; do not rely solely on document scrollWidth |
| Modal/drawer keyboard | Open, traverse, Escape and verify restored focus; test both tab directions and no access to inert background while modal |
| Form error recovery | Empty/invalid title is rejected; other values survive; correction succeeds; separately test server-side failures and expired sessions |
| Task/shift completion | Confirm success, persisted state, count changes and reload behavior for task completion and volunteer cancellation/rejoin; preserve capacity/approval authorization tests |
| Accessible names | axe plus explicit name assertions for all artwork inputs/poster links and icon controls; contextual names for repeated actions |
| Student actor guidance | Student offer interface states guardian response; any forbidden POST remains rejected by authoritative backend tests |
| Communication handoff | Creating rehearsal with prepare-draft exposes its unsent draft and audience; no worker/delivery activation |
| History at scale | Retained completed-production definitions are findable without dominating active tasks; history is preserved and closed submissions remain enforced server-side |

For each approved fix: reproduce the current failure, implement only that change, re-capture the matching surface/viewport/state and verify its guard. Record any accepted tradeoff with evidence rather than silently discarding it.

## Coverage limitations and unfinished journeys

- **Registration:** entry and fields inspected, but no new public registration, verification email, account activation, staff approval or Student account setup completed. Do not mark the parent registration journey passed.
- **Family:** pickup/relationship forms inspected; no grant/revoke/approval submitted. Parent contact editing, caregiver and former-guardian end-to-end journeys were not freshly executed in this run. Existing source policy is not a replacement for those tests.
- **Student:** personal task completion and schedule/resource reading executed; offer response and form acknowledgement not submitted. Enabled offer controls do not establish whether an unauthorized POST could succeed.
- **Messaging:** empty inbox and safeguarded composer inspected; no message sent, attachment uploaded, guardian-inclusion delivery or revocation test executed. No private production conversation was captured.
- **Manager:** planning creation/details executed with Administrator authority. No independent restricted manager role account, activation, archive, completion, public basics publication, cast enrollment or irreversible lifecycle operation tested.
- **Staff:** casting drawer inspected, not altered or sent; no finalization, manual transfer, audition intake or attendance save. Private Studio draft/profile save/preview/review executed; public publish/unpublish, profile consent, QR delivery and print output not exercised during this audit. Prior integration results are not relabelled as current browser tests.
- **Volunteer:** owned unrestricted synthetic shift cancelled/rejoined. No new restricted shift signup, waitlist, capacity race, approval decision, training verification or volunteer-lead management test executed.
- **Administrator:** account search and role dialog inspected; no permissions, membership status, account disable/delete, safeguarding case or review decision modified. Form submission review screen itself was not opened to approve/reject.
- **Public audience:** live production discovery and registration entry tested read-only; synthetic local program reader used to avoid publishing/capturing real participant information. Live ticket purchase, external program, actual QR scanning and native apps were outside scope.
- **Accessibility/performance:** no complete axe scan, screen-reader audit, physical device test, zoom matrix, offline/failure injection, web-vitals measurement or slow-network UX verification.
- The local schema contains accumulated synthetic history and an optional external-Playbill setting reported as unavailable. No migration was applied; unsupported-setting and list-volume effects are environment-dependent, not a claim about production schema.

## Evidence index

All authenticated images contain synthetic test data only. The filename states surface and viewport; screenshots with `mobile` are 390px wide, `tablet` 768px, and `desktop` 1440px. Full-page image height can exceed the viewport. The following index links every stored capture; JSON measurements are supporting probes, not a complete accessibility scanner.

- [admin-account-detail-desktop](ux-navigation-audit-evidence/admin-account-detail-desktop.jpg)
- [admin-account-mobile](ux-navigation-audit-evidence/admin-account-mobile.jpg)
- [admin-accounts-desktop](ux-navigation-audit-evidence/admin-accounts-desktop.jpg)
- [admin-form-queue-destination-desktop](ux-navigation-audit-evidence/admin-form-queue-destination-desktop.jpg)
- [admin-form-review-queue-desktop](ux-navigation-audit-evidence/admin-form-review-queue-desktop.jpg)
- [admin-operations-desktop](ux-navigation-audit-evidence/admin-operations-desktop.jpg)
- [admin-roles-dialog-desktop](ux-navigation-audit-evidence/admin-roles-dialog-desktop.jpg)
- [admin-staff-queues-desktop](ux-navigation-audit-evidence/admin-staff-queues-desktop.jpg)
- [guardian-back-destination-mobile](ux-navigation-audit-evidence/guardian-back-destination-mobile.jpg)
- [guardian-family-mobile](ux-navigation-audit-evidence/guardian-family-mobile.jpg)
- [guardian-form-detail-mobile](ux-navigation-audit-evidence/guardian-form-detail-mobile.jpg)
- [guardian-form-expanded-mobile](ux-navigation-audit-evidence/guardian-form-expanded-mobile.jpg)
- [guardian-forms-mobile](ux-navigation-audit-evidence/guardian-forms-mobile.jpg)
- [guardian-home-desktop](ux-navigation-audit-evidence/guardian-home-desktop.jpg)
- [guardian-home-mobile](ux-navigation-audit-evidence/guardian-home-mobile.jpg)
- [guardian-pickup-disclosure-mobile](ux-navigation-audit-evidence/guardian-pickup-disclosure-mobile.jpg)
- [guardian-pickup-mobile](ux-navigation-audit-evidence/guardian-pickup-mobile.jpg)
- [manager-artwork-labels-tablet](ux-navigation-audit-evidence/manager-artwork-labels-tablet.jpg)
- [manager-details-menu-desktop](ux-navigation-audit-evidence/manager-details-menu-desktop.jpg)
- [manager-production-created-desktop](ux-navigation-audit-evidence/manager-production-created-desktop.jpg)
- [manager-production-mobile](ux-navigation-audit-evidence/manager-production-mobile.jpg)
- [manager-production-tablet](ux-navigation-audit-evidence/manager-production-tablet.jpg)
- [manager-productions-desktop](ux-navigation-audit-evidence/manager-productions-desktop.jpg)
- [manager-required-validation](ux-navigation-audit-evidence/manager-required-validation.jpg)
- [public-home-desktop](ux-navigation-audit-evidence/public-home-desktop.jpg)
- [public-home-mobile](ux-navigation-audit-evidence/public-home-mobile.jpg)
- [public-join-mobile](ux-navigation-audit-evidence/public-join-mobile.jpg)
- [public-login-mobile](ux-navigation-audit-evidence/public-login-mobile.jpg)
- [public-production-mobile](ux-navigation-audit-evidence/public-production-mobile.jpg)
- [public-reader-contents-mobile](ux-navigation-audit-evidence/public-reader-contents-mobile.jpg)
- [public-reader-page2-mobile](ux-navigation-audit-evidence/public-reader-page2-mobile.jpg)
- [public-reader-synthetic-mobile](ux-navigation-audit-evidence/public-reader-synthetic-mobile.jpg)
- [staff-casting-desktop](ux-navigation-audit-evidence/staff-casting-desktop.jpg)
- [staff-casting-dialog-desktop](ux-navigation-audit-evidence/staff-casting-dialog-desktop.jpg)
- [staff-mobile-navigation](ux-navigation-audit-evidence/staff-mobile-navigation.jpg)
- [staff-playbill-studio-desktop](ux-navigation-audit-evidence/staff-playbill-studio-desktop.jpg)
- [staff-playbill-wrong-destination-desktop](ux-navigation-audit-evidence/staff-playbill-wrong-destination-desktop.jpg)
- [staff-production-desktop](ux-navigation-audit-evidence/staff-production-desktop.jpg)
- [staff-rehearsal-created-desktop](ux-navigation-audit-evidence/staff-rehearsal-created-desktop.jpg)
- [staff-schedule-create-desktop](ux-navigation-audit-evidence/staff-schedule-create-desktop.jpg)
- [staff-schedule-desktop](ux-navigation-audit-evidence/staff-schedule-desktop.jpg)
- [staff-schedule-drafts-desktop](ux-navigation-audit-evidence/staff-schedule-drafts-desktop.jpg)
- [staff-schedule-import-destination](ux-navigation-audit-evidence/staff-schedule-import-destination.jpg)
- [staff-scope-denial-desktop](ux-navigation-audit-evidence/staff-scope-denial-desktop.jpg)
- [staff-studio-bios-desktop](ux-navigation-audit-evidence/staff-studio-bios-desktop.jpg)
- [staff-studio-mobile](ux-navigation-audit-evidence/staff-studio-mobile.jpg)
- [staff-studio-publish-desktop](ux-navigation-audit-evidence/staff-studio-publish-desktop.jpg)
- [student-calendar-mobile](ux-navigation-audit-evidence/student-calendar-mobile.jpg)
- [student-calendar-tablet](ux-navigation-audit-evidence/student-calendar-tablet.jpg)
- [student-call-detail-mobile](ux-navigation-audit-evidence/student-call-detail-mobile.jpg)
- [student-cast-empty-desktop](ux-navigation-audit-evidence/student-cast-empty-desktop.jpg)
- [student-compose-mobile](ux-navigation-audit-evidence/student-compose-mobile.jpg)
- [student-home-mobile](ux-navigation-audit-evidence/student-home-mobile.jpg)
- [student-messages-mobile](ux-navigation-audit-evidence/student-messages-mobile.jpg)
- [student-mytheatre-desktop](ux-navigation-audit-evidence/student-mytheatre-desktop.jpg)
- [student-offer-mobile](ux-navigation-audit-evidence/student-offer-mobile.jpg)
- [student-resource-detail-desktop](ux-navigation-audit-evidence/student-resource-detail-desktop.jpg)
- [student-resources-desktop](ux-navigation-audit-evidence/student-resources-desktop.jpg)
- [student-task-completed-tablet](ux-navigation-audit-evidence/student-task-completed-tablet.jpg)
- [volunteer-cancelled-mobile](ux-navigation-audit-evidence/volunteer-cancelled-mobile.jpg)
- [volunteer-confirmed-mobile](ux-navigation-audit-evidence/volunteer-confirmed-mobile.jpg)
- [volunteer-home-desktop](ux-navigation-audit-evidence/volunteer-home-desktop.jpg)
- [volunteer-shift-mobile](ux-navigation-audit-evidence/volunteer-shift-mobile.jpg)
- [volunteer-shifts-desktop](ux-navigation-audit-evidence/volunteer-shifts-desktop.jpg)

- [DOM measurements](ux-navigation-audit-evidence/dom-measurements.json)
- [Public poster-link names](ux-navigation-audit-evidence/public-link-measurements.json)

## Appendix A: source route inventory

This is a source inventory, not a claim that every endpoint was rendered or authorized in this run. Earlier public/auth/mobile handlers can intercept a route before this registry; later duplicated entries are shown to make that ambiguity reviewable. Media/export/transport endpoints are not necessarily navigable screens.

| Registry order | Handler | Declared paths |
|---:|---|---|
| 1 | `AccountManagementExperience` | `/admin/accounts`, `/admin/accounts/view` |
| 2 | `RegistrationOperationsExperience` | `/admin/registrations`, `/admin/registrations/edit`, `/admin/registrations/view`, `/admin/registrations/intake` |
| 3 | `EmailOperationsExperience` | `/admin/email` |
| 4 | `NotificationPreferenceExperience` | `/notification-preferences`, `/push-settings`, `/push/subscribe`, `/push/unsubscribe`, `/push/test` |
| 5 | `HelpExperience` | `/help` |
| 6 | `MyAccountExperience` | `/account` |
| 7 | `FamilyDashboardExperience` | `/family-hub`, `/parent`, `/onboarding`, `/family/manage` |
| 8 | `HomeExperience` | `/app` |
| 9 | `MyTheatreExperience` | `/my-theatre` |
| 10 | `StaffDashboardExperience` | `/staff` |
| 11 | `OperationsHubExperience` | `/admin/operations`, `/admin/operations/community`, `/admin/operations/resources`, `/admin/operations/forms` |
| 12 | `CalendarExperience` | `/calendar`, `/calendar/feed` |
| 13 | `MemberCastExperience` | `/cast` |
| 14 | `StudentProfileExperience` | `/student-profile`, `/student-profile/headshot` |
| 15 | `FamilyPickupExperience` | `/student-profile/family`, `/student-profile/family/review` |
| 16 | `CaregiverLogisticsExperience` | `/family/caregiver-schedule` |
| 17 | `ActingResumeExperience` | `/theatre-history/resume` |
| 18 | `TheatreHistoryExperience` | `/theatre-history` |
| 19 | `ProductionArchiveExperience` | `/archive`, `/archive/production`, `/archive/community`, `/archive/file` |
| 20 | `OrganizationResourceExperience` | `/admin/member-resources`, `/admin/member-resources/edit`, `/member-resources/download` |
| 21 | `AccountLibraryExperience` | `/files`, `/files/view`, `/resources`, `/resources/view` |
| 22 | `ProductionContextExperience` | `/production/select` |
| 23 | `PeopleExperience` | `/family-hub`, `/people`, `/people/view` |
| 24 | `NotificationExperience` | `/notifications` |
| 25 | `FamilyFormsExperience` | `/forms`, `/forms/view`, `/admin/forms`, `/admin/forms/review`, `/admin/forms/group-assign` |
| 26 | `FormManagementExperience` | `/admin/forms/manage`, `/admin/forms/manage/edit`, `/admin/forms/manage/assign` |
| 27 | `FormBuilderIndexExperience` | `/admin/forms/build` |
| 28 | `DynamicFormExperience` | `/admin/forms/builder` |
| 29 | `VolunteerServiceRecordExperience` | `/volunteer/history`, `/volunteer/service-record` |
| 30 | `VolunteerVerificationExperience` | `/volunteer/verifications`, `/volunteer/verification`, `/admin/volunteer-verification` |
| 31 | `VolunteerDevelopmentExperience` | `/volunteer/history`, `/volunteer/training`, `/admin/volunteer-development` |
| 32 | `VolunteerShiftManagementExperience` | `/admin/volunteer-shifts`, `/admin/volunteer-shifts/new`, `/admin/volunteer-shifts/view` |
| 33 | `VolunteerApprovalExperience` | `/volunteer/approvals`, `/admin/volunteer-approvals`, `/admin/volunteer-approvals/review` |
| 34 | `VolunteerExperience` | `/volunteer-readiness`, `/volunteer-shifts`, `/volunteer/shift` |
| 35 | `TeamExperience` | `/admin/teams`, `/admin/teams/view`, `/admin/private-channel` |
| 36 | `ModerationExperience` | `/admin/moderation/terms`, `/admin/moderation/terms/edit`, `/admin/moderation/queue` |
| 37 | `CommunityManagementExperience` | `/admin/channels`, `/admin/channels/edit` |
| 38 | `CommunityExperience` | `/channels`, `/channels/view`, `/channels/attachment` |
| 39 | `ProductionLifecycleExperience` | `/admin/productions`, `/admin/productions/view`, `/admin/productions/artwork-image` |
| 40 | `CastingExperience` | `/production/casting` |
| 41 | `ProductionReadinessExperience` | `/production/readiness` |
| 42 | `ProductionTasksExperience` | `/production/tasks` |
| 43 | `ProductionDayExperience` | `/production/day` |
| 44 | `ProductionWorkspaceExperience` | `/production`, `/production/artwork-image` |
| 45 | `ProductionGroupExperience` | `/production/groups`, `/production/groups/view` |
| 46 | `ProductionPeopleExperience` | `/production/people` |
| 47 | `ProductionSupportNeedsExperience` | `/production/support-needs` |
| 48 | `AttendanceExperience` | `/attendance`, `/attendance/take`, `/attendance/report` |
| 49 | `LatePickupExperience` | `/production/late-pickups` |
| 50 | `ScheduleCreateExperience` | `/production/schedule/new` |
| 51 | `ScheduleNoticeExperience` | `/production/notices`, `/production/notice` |
| 52 | `RehearsalVideoExperience` | `/videos`, `/videos/view`, `/videos/play`, `/videos/archive-download`, `/videos/inventory`, `/production/rehearsal-library`, `/production/rehearsal-video` |
| 53 | `ProductionFileExperience` | `/files`, `/files/view`, `/files/download`, `/admin/files`, `/admin/files/edit` |
| 54 | `ResourceExperience` | `/resources`, `/resources/view`, `/admin/resources`, `/admin/resources/edit` |
| 55 | `Playbill2Experience` | `/playbill`, `/playbill/asset`, `/playbill/qr`, `/playbill/sign`, `/admin/playbill/media` |
| 56 | `PlaybillExperience` | `/playbills`, `/playbill/consent`, `/admin/playbill` |
| 57 | `ProductionExperience` | `/production`, `/schedule`, `/production/day`, `/production/edit`, `/resources`, `/playbills` |
| 58 | `CommunicationExperience` | `/messages`, `/messages/new`, `/messages/thread`, `/messages/attachment` |
| 59 | `SafeguardingConversationReviewExperience` | `/safeguarding/conversations`, `/safeguarding/conversations/attachment` |
| 60 | `SafeguardingCaseExperience` | `/safeguarding/cases`, `/safeguarding/case` |
| 61 | `SafeguardingExperience` | `/safeguarding`, `/safeguarding/review`, `/safeguarding/audit` |

**Additional earlier handlers:** Public production listing/detail; PublicExperience welcome/features/program directory/registration; public safeguarding statement; public casting; AuthExperience login/logout/join/activation/verification/reset; `/calendar/feed`; public `/playbill`, `/playbill/asset`, `/playbill/qr`, `/playbill/sign`; `/health`; mobile API endpoints. Development-only identity/navigation/visual tools were not used and are not proposed as user navigation.

**Boundary of this report:** review and approve Phase 1 before implementation. Preserve current guardian authorization, scoped Staff checks, closed-form enforcement, publication consent, explicit communications and historical audit records throughout remediation.
