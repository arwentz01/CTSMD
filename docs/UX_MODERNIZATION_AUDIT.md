# UX modernization audit

## Phase 3 — operational and native adoption

Baseline: remote-synchronized `abedbd4`, existing `audit/board-pilot-readiness`, October 27, 2026. Phase 2 templates, scoped tokens, viewer-filtered feeds and CI evidence were inspected before extension.

### Build 1 — Forms

Adopted the existing shell/tokens on the active FamilyFormsExperience routes (`/forms`, detail, staff review and group assignment), form management/edit/assignment, builder and builder index. No dormant legacy FormExperience rewrite. Subject/production, actual assignment/submission states, staff review and historical responses remain distinct. Returned-response notes are now visible; dynamically generated text/date fields receive names from their field legends. Closed/unsupported productions use the existing lifecycle guard only for a presentation read-only lock on family forms.

**Pre-existing release blocker:** active FamilyFormsExperience submission/review and management/builder handlers lack consistent lifecycle write enforcement. The legacy FormExperience source-contract test does not prove these active handlers safe. This phase does not repair backend rules or claim that hiding controls secures direct POSTs. Track in TODO's P0 gates.

| Before | After | Why |
| --- | --- | --- |
| Dark promotional form headings and tiny metadata | Compact form/task headings, shared warm surfaces and readable subject/production context | The assignment and next decision lead |
| Text/date field group legend without a control name | Legend-associated accessible name | Assistive technology can identify the field |
| Returned response lacked its reviewer explanation in the fill view | Existing reviewer note displayed without changing review status | User understands the requested correction |

Verification and subsequent build records follow as each batch lands. This phase never implies deployment or complete WCAG certification.

### Build 2 — Volunteer

Routes: readiness, opportunities/detail, shift management/new/detail, requests/staff review, training, verifications and verified service history. Reused shared tokens for headers, shift rows, eligibility reasons, approval context and service records. Existing eligibility, waitlist/approval, check-in/out, cancellation, verified-hour data and actions are unchanged. Print service records retain their print mode. Before/after authenticated Chromium at 1440/768/390; cancellation and lifecycle regression suites pass. Physical check-in/out and real-device testing remain open acceptance work.

### Build 3 — Scheduling and attendance

Routes: Calendar month/week/agenda, Attendance list/take/report, schedule creation, schedule notices, Production Day and late-pickup review. Shared heading, date/context, form/control and warm-surface patterns preserve every event/audience filter, attendance status, absence report and pickup record. Existing phone quick attendance and detailed drawer remain the interaction model; no replacement table or new status. Focus indication also covers radio-backed status labels. Lifecycle source-contract and late-pickup workflow tests pass; responsive renders are recorded in the final verification section.

### Build 4 — Tasks and operational readiness

Routes: `/production/tasks`, `/production/readiness`, production directory/detail and existing completion/archive panels. Shared task rows, owners/dates, summary typography and readable readiness findings. Finding text is derived only from existing severity/count: positive blocker = Blocked, positive nonblocker = Warning, zero = Ready. This does not decide eligibility; existing service readiness arrays, button disabling, typed confirmations, acknowledgments, transactions and audit records remain untouched. Completion and archival stay separate. Lifecycle and directory tests cover retained source contracts.

### Build 5 — Family, pickup and accounts

Routes: child-specific Family & Pickup, staff relationship review, caregiver logistics and account-management list/detail. Reused shell, readable relationship/pickup rows, forms and mobile stacking. Existing parent/guardian/caregiver explanations and separate pickup-only authorization are preserved, with no new permission claims. Primary guardian, automatic pickup authority, revocation, account invitation and relationship-review handlers are unchanged. A student ID is required for the child-specific route; browser coverage uses an authorized linked child rather than the parameterless error path.

**Actual local blocker:** the linked-child route fails in existing StudentProfileService because demo `users.date_of_birth` is absent. No schema change was made. An authorization-loader PDO failure now returns an HTTP 503 unavailable screen, with no student information or mutation control and no stack trace. Successful child-specific rendering/pickup actions remain unverified until approved schema reconciliation; the review queue and account list do render. This is not a claim that Build 5's successful workflow acceptance is complete.

### Build 6 — Native Expo

Inspected SDK `~58.0.20`, React Native `0.88.0`, Expo Router and existing dependencies; no SDK/dependency version upgrade. Shared colors now map to web semantic roles. Screen and Item use warm surfaces, system UI type, readable metadata, safe-area edges, a bounded tablet reading column, keyboard-aware scrolling and existing native tab/stack destinations. A shared TouchTarget supplies 48dp minimum controls with disabled semantics. Existing async data hook retains focus/unmount data clearing and adds explicit retry; consumers now expose it. Native message creation uses the shared Screen rather than a bare ScrollView; all API/auth/send/notification code is unchanged.

Native verification limitation: `xcrun simctl` is unavailable and no `adb` executable/device is configured. No native screenshot, simulator, tablet, TalkBack/VoiceOver, keyboard or hardware claim is made. TypeScript checks and hosted Expo CI are recorded separately. Existing structured-form support gaps, pickup administration, staff attendance/readiness tooling, complete dark-mode theming and expanded tablet navigation are not invented as frontend-only behavior; these remain documented follow-up acceptance/API work.

### Phase 3 verification and visual judgment

- All source/test PHP syntax and browser JavaScript syntax checks pass. Existing pilot suites, Action Center regression, new operational presentation regression, and 14 late-pickup workflow contracts pass. The lifecycle source-contract test retains its two existing interpolation warnings and does not cover the active FamilyForms handler gap.
- MAMP safeguarding integration: 36 checks pass in a rolled-back transaction. No schema/migration, authentication, delivery queue or policy service changed.
- Authenticated Chromium captures for 17 representative operational routes at 1440/768/390px, plus populated attendance detail and guardian form detail. Final DOM probes and viewport width measurements are scoped checks, not WCAG certification. The failed Family & Pickup success path is explicitly recorded above, not counted as successful workflow coverage.
- Browser assertions cover parent/student denials on staff forms, volunteer management, tasks, family review and accounts; populated attendance CSRF/action contract, drawer opening and Escape focus return; guardian form subject context. No communicating/destructive POST, approval, cancellation, attendance save or family mutation was sent.
- Native TypeScript and `expo install --check` pass after refreshing ignored local dependencies. Initial local typecheck lacked expo-video; `npm ci` also found a pre-existing optional-package lock inconsistency. An install without lockfile writes restored local dependencies; package.json/package-lock.json versions are unchanged.
- Hosted [Expo run 40624585409](https://github.com/arwentz01/CTSMD/actions/runs/40624585409) passed for `44a5976`. Hosted [web run 40624077274](https://github.com/arwentz01/CTSMD/actions/runs/40624077274) passed for `0c52563`; final follow-up run is recorded after push.
- Captures/measurement JSON in `/tmp/ctsmd-phase3-before`, `-after`, `-final`, `-verified` are local only because they contain demo account data and calendar subscription context.

Visual review (subjective, app-screen rubric): the operational question now leads, with subject/production and actual states carrying meaning rather than decorative hero panels. Warm CTSMD shell and familiar task controls persist. Remaining weak spots are inherited layer complexity, duplicated headings on a few deep views, thin empty demo accounts, and inconsistent small screen-specific metadata. Optional cosmetics are deferred rather than presented as inaccessible workflow exceptions.

ROUND 1: shared operational system, actual tasks and history. Question: what needs doing for this person/production? Design 7 · usability 7 · creativity 7 · content 8 → 7.1. Top issues in first captures: inherited light-on-light labels, mobile detail header colors, and Family & Pickup fatal response. Corrected the contrast/header cases and replaced the fatal response with an honest unavailable state; schema dependency remains.

ROUND 2: operational headers and forms remain coherent at three widths; no new sample/proof content, imagery or animation added. Native receives source/type/build checks only and is not visually scored. Successful pickup workflow, real-device accessibility and backend lifecycle enforcement remain acceptance blockers, not a completed Phase 3 claim.

## Phase 2 implementation record

Started from remote-synchronized `5093b1f` on the existing branch. Each build reuses `WorkspaceUI` and `--ux-*` tokens. Cosmetic follow-ups live in `UX_POLISH_BACKLOG.md`.

### Build 1 — Family

Routes: `/family-hub`, `/parent`. Shared warm shell, compact household header, forms and authorized casting offers before child summaries, deep links to individual assignments, combined schedule, volunteer commitments and updates. No relationship, pickup, offer-response or permission rules changed. Casting data comes from the same `CastingOfferService::pendingForUser()` used by the existing Action Center.

Verification: parent account rendered in authenticated Chromium at 1440/390px; no document overflow, no blocking DOM-probe findings after removing an inherited mobile gradient. PHP lint, member roster/media and lifecycle-boundary suites pass. Existing permission conditions remain intact. Multi-child data is still iterated across all active productions with no selected-production filter. Per-child filtering and physical-device review are deferred.

### Build 2 — Student and personal Today

Route: `/app`. Reused the role-aware renderer with a concise personal header, student Theatre History action, personal action and schedule sections, and explicit links to existing Messages, Community and casting announcements when membership/production access allows. Students no longer see a meaningless household-child count. Parent/staff versions retain their distinct household links and counts. No account, roster or schedule service changed.

Verification: authenticated student desktop/phone rendering; PHP lint; inherited mobile Next up colors corrected after the contrast probe found light text on the new ivory surface. The final student probe has no blocking findings. Closed-production exclusion and cross-production roster suites pass. Optional timeline duplication is deferred.

### Build 3 — Community

Routes: `/channels` and existing channel selections. Warm rail and reading pane, readable author/time metadata, labeled composer and file input, selected-channel semantics, textual Like/Liked state and pinned-announcement label. Membership, search scope, moderation, downloads and historical access remain authoritative. Existing reactions work through unchanged POST contracts. Source inspection found no threaded reply service; replies and authenticated image thumbnails are explicitly deferred dependencies, not nonfunctional controls.

### Build 4 — Messaging

Routes: `/messages`, `/messages/new`, `/messages/thread`. Consistent lists, metadata, bubbles, labeled search/reply/file controls, participant disclosure association and mobile composer. The legacy shell previously removed safety banners; modernized threads instead retain them in a native Safeguarding and privacy disclosure. Paused sending remains explicit in the existing server-controlled composer. No conversation policy, recipient eligibility, attachment access or delivery service changed.

### Build 5 — Notifications and actions

Route: `/notifications`. Current actions, deadline/status text, subject and production context, unread updates, read-update history and a separate completed-form disclosure. Read never means completed. Existing CSRF-protected read/complete/offer response actions and deep links remain intact. Students no longer get a Family link in this page's subnavigation.

Two narrow read-side corrections reuse existing authorization rather than introduce new rules: Family updates/unread totals now pass through `ProductionNotificationAccess`; Action Center forms now use `FamilyFormService::assignmentsForViewer`, then exclude completed/closed/unknown-production work from pending actions. Completed forms stay explicitly historical. Notification creation, delivery eligibility and queue processing are untouched.

### Phase 2 verification — local, October 27, 2026

- PHP syntax checks across all source/test files and front controller; Node checks for shared shell/workspace/upload scripts; all nine existing pilot safety suites pass. The lifecycle source-contract test retains its two pre-existing interpolation warnings.
- Added `tests/ux_action_visibility.php`: viewer/guardian-scoped service delegation and completed/closed/unknown-production exclusion. Community search and safeguarding-review regression tests pass and are now included in CI.
- Real MAMP database safeguarding regression: 36 checks pass, transaction rolled back. No schema change or persistent fixture write was needed.
- Authenticated Chromium before/after captures at 1440 and 390px for all five screens. Final sampled DOM reports have no blocking findings; intentional warm-ivory palette warnings are not accessibility failures. Selected channel and message-thread views additionally checked. This is not a complete WCAG conformance audit.
- Parent, student, production-manager and administrator identities exercised on shared routes. Source review and existing regression suites cover multi-production aggregation, current guardian relationships and closed-production exclusion.
- Local captures and probe JSON remain outside Git in `/tmp/ctsmd-phase2-before/`, `/tmp/ctsmd-phase2-after/` and `/tmp/ctsmd-phase2-final/`; they contain authenticated demo context.
- Physical devices, Safari/Firefox, screen readers, live communicating POST/upload paths and production deployment are not claimed. Deferred refinements are in `UX_POLISH_BACKLOG.md`. GitHub CI evidence is recorded after push below.
- Hosted CI: [run 40623076474](https://github.com/arwentz01/CTSMD/actions/runs/40623076474) passed for `81ca603`. Final thread review then caught and corrected an inherited mobile burgundy bubble/charcoal-text collision; the mine-bubble surface now stays ivory on phones. The workflow also watches the shared UX CSS/JS so presentation-only regressions trigger safety checks.
- Final implementation CI: [run 40623138011](https://github.com/arwentz01/CTSMD/actions/runs/40623138011) passed every step for `1721003`. Re-rendered desktop/phone thread checks confirm no document overflow, a reachable monitoring disclosure, and no blocking DOM findings after the bubble correction. Parent/student/staff/admin route assertions all pass. No live communicating POST or attachment upload was sent.

## Phase 1 historical record

Baseline: `d72cfd7`, branch `audit/board-pilot-readiness`, 2026-10-27. The checkout was fast-forwarded from `5bec6ae` after fetching the branch, before edits.

## Approved direction

Calm Backstage Operations: warm ivory and white, charcoal structure, burgundy actions, restrained gold, production artwork and editorial production titles. Sidebar destinations lead to personal, production and operations hubs. Existing permission checks and routes remain authoritative.

## First-pass findings

| Before | After target | Why / review lens |
| --- | --- | --- |
| Large decorative staff greeting with several tracked labels | Compact Staff Overview title, useful scope and working-production action | Emil: attention belongs on queues and upcoming calls |
| Permissioned queues displayed as repeated large metric cards | Compact review rows with labels, counts and explicit next step | Taste: content and state carry emphasis |
| Production workspace uses a uniform icon-card grid | Grouped task links with readable descriptions and an unmistakable production title/artwork | Emil: hubs organize tools without growing global navigation |
| Casting releases precede the working board and unrelated blue selected controls | Visible workflow guidance and consistent neutral/burgundy controls | Impeccable: private decision, roster access and publication remain distinct |
| Shared shell and page-specific styles compete with ten dynamically loaded stylesheets | Scoped modernization layer loaded after legacy shell layers | Impeccable: incremental adoption avoids broad regressions |
| Tiny metadata, weak field edges and minimal keyboard indication | Shared type, control, focus and responsive patterns | Impeccable: useful density remains readable |

## Protected boundaries

No modifications to services, SQL, role/permission conditions, CSRF fields, POST action names, lifecycle transitions, archival/completion behavior, notification logic, or native Expo integration. Public Playbill and casting publication privacy remain unchanged. A visual state never implies completion of a backend workflow.

## Prioritized roadmap

1. Shared scoped tokens, shell treatment and reusable page-header/workflow patterns; retain destinations and permission visibility.
2. Staff Overview hierarchy and compact queue rows, preserving all cross-production data.
3. Production Workspace identity and grouped tools, keeping every existing gated destination.
4. Casting workflow guidance, form/control consistency, responsive review and drawer verification.
5. Family/student views: retain all active productions and connect attention, next call, student and production; validate with parent/student roles before adopting the layer.
6. Lifecycle readiness: apply the shared patterns to the existing blocker/warning and explicit confirmation flow in a separate pass.
7. Publication design: use production artwork and editorial type on public Playbills, retaining QR routes, sponsor/headshot support and consent rules.

## Verification record

The first pass implements the shared warm shell, an escaped presentation helper, compact staff queues, grouped production tools and a casting workflow guide. Casting releases are available in a native disclosure below the working board. When callbacks are empty and cast decisions exist, the populated Cast tab is initially selected. No POST handler or service changed.

### Automated checks

- PHP syntax lint passed for all `src/*.php`, `tests/*.php`, and `front.php` using MAMP PHP 8.3.30.
- All nine suites from `pilot-php-safety.yml` passed: production directory, lifecycle boundaries, closed-production roster, member roster/media, community video links, video privacy, lifecycle, rate limiting, and volunteer cancellation.
- The existing lifecycle boundary test emits two PHP warnings about interpolated `$db` / `$viewerId` on line 36. Its assertions pass. This pre-existing test issue was not changed in a presentation pass.
- Node syntax checks passed for video upload, unified navigation and the new workspace script. The CI workflow now includes both shell scripts in its browser JavaScript step.
- `git diff --check` passed. Design-taste app source lint reported zero failures; its warnings on `box-shadow: none` and `backdrop-filter: none` reflect deliberate removal of legacy effects. Impeccable's automatic design hook reported no deterministic findings on the new stylesheet.

### Authenticated browser verification

The local PHP server used the current repository, existing local identity flow and MAMP demo database. Only session identity/working-production context was changed; casting decisions, publications, roster access and database schema were not mutated.

- Before/after Chromium captures for Staff Overview, Production Workspace and Casting at 1440, 768 and 390 CSS pixels. Final document width equals viewport width in all nine captures.
- The design-taste DOM probe and rule judge were run inside the authenticated browser with the app-screen exclusions. Nine final viewport reports have zero blocking findings and no remaining sampled text-contrast warning. This is a scoped DOM check, not the full standalone inspector's geometry or a complete WCAG audit.
- Remaining nonblocking design notes: the requested warm ivory palette is intentional; the inherited install prompt may retain a small uppercase label. No exception was taken for unreadable operational text.
- Browser assertions confirm mobile navigation opens/closes, Escape dismisses it, casting arrow keys select the matching tab/panel, role drawers open and restore focus after Escape, and both release forms remain reachable through the disclosure.
- The populated three-person casting board and candidate drawer were additionally rendered at desktop and phone widths. No save, send, finalize or publish operation was exercised.
- Baseline and final inventories match all existing contextual destinations, form actions, methods and named field/type contracts on the three routes.
- Parent and student identities receive 403 on `/staff` and `/production/casting`; staff management links remain absent from their production workspaces.

### Visual judgment and next review

Emil: the daily staff question is now answered by visible queues rather than a large greeting; production tools are grouped under two clear headings; private casting work precedes release. Impeccable: shared controls, focus, skip links, keyboard tabs and phone labels are consistent. Taste: burgundy is reserved for meaningful actions, artwork has one production-specific slot, and decorative metric/icon cards no longer dominate.

The screen's data and counts come from the existing services. The editorial production title and warm shell provide identity without changing lifecycle semantics. Real production imagery still uses the authorized endpoint and retains a readable solid fallback when missing.

Remaining validation: physical iOS/Android devices, Safari/Firefox, assistive technology, 200% zoom, image-present production samples, and destructive/communicating POST integration paths. Native Expo files and lifecycle/archival/completion screens were not altered. Hosted GitHub CI has not been run because these commits are local and were not pushed or deployed.

Local review captures and measurement JSON are in `/tmp/ctsmd-ux-before/` and `/tmp/ctsmd-ux-final/`. They are intentionally outside the repository because they contain authenticated demo-account context.
