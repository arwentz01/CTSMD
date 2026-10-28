# Native Phase 4 — integration and acceptance evidence

October 28, 2026 · `audit/board-pilot-readiness` · starting baseline `5fd5d04`.

## Decision and scope

**Source integration advanced; native pilot/distribution remains NO-GO.** Local/API checks and bundle exports are not device acceptance. No production deployment, production migration, simulator run, native push delivery or app-store build is claimed. Active follow-up work lives only in [TODO.md](TODO.md).

This extends the existing Expo app, not a replacement. Reviewed the [modernization audit](UX_MODERNIZATION_AUDIT.md), [design system](UX_DESIGN_SYSTEM.md), [approved Warm Backstage direction](WARM_BACKSTAGE_DESIGN_STANDARD.md), [lifecycle](PRODUCTION_LIFECYCLE_V2.md), [safeguarding review](SAFEGUARDING_CONVERSATION_REVIEW.md), [guardian alerts](GUARDIAN_SAFEGUARDED_NOTIFICATION_MUTE.md), [pilot gates](BOARD_PILOT_READINESS.md) and [prior mobile notes](MOBILE_V1_FOLLOWUPS.md). Impeccable native hardening guidance favored shared platform controls and honest unsupported states over a navigation redesign.

## Architecture and native design audit

- Existing Expo SDK `~58.0.20`, React Native `0.88.0`, React `19.3.0`, Router `~58.0.27` retained. Added SDK-compatible `expo-notifications` and direct required peers `expo-font`, `expo-constants`, `expo-linking`; Expo Doctor originally identified those three missing direct dependencies. Lockfile retained; CI now uses `npm ci`.
- Existing `mobile/app` file router has a protected stack, five primary tabs and secondary Forms/Volunteer routes. New calendar/event screens are inside the authenticated stack. No desktop sidebar or second app.
- `AuthProvider` restores SecureStore credentials, resolves `/api/mobile/me`, refreshes on foreground, reacts to 401 and performs server logout. API base is HTTPS-only; no credentials in public Expo configuration. No second identity system.
- Backend `MobileApi` resolves hashed, expiring, revocable bearer tokens against active users and current permissions. ProductionContext, CalendarService, Community policies and SafeguardedConversationPolicy remain authoritative.
- Reused Phase 3 `Screen`, `Item`, `LoadState`, `TouchTarget` and semantic colors: safe areas, keyboard handling, system typography, explicit empty/error/retry states and minimum 48dp press targets. Schedule cards and notification controls use these primitives. Thread monitoring/participant disclosure and paused composer remain legible text, not color-only status.
- Text scaling stays enabled. Actual large-text layouts, screen-reader order, keyboard coverage, rendered contrast, tablet layout and dark appearance are **not device accepted**. No WCAG/platform accessibility certification is asserted by source checks.

## Feature parity matrix

“Implemented” means source/API integration, not device acceptance.

| Experience | Native / web parity | Evidence and remaining limit |
|---|---|---|
| Authentication | Existing login/persistence; new current-session invalidation, foreground refresh and online per-device logout cleanup | Active/anonymous/revoked bearer HTTP checks; stale-session/logout contracts. Cold start, device lock, offline/reinstall/account switching pending |
| Home / actions | Existing dashboard, casting offers and To-Dos, not a full staff dashboard | Parent daily-tasks HTTP read and existing lifecycle regressions pass; device action completion pending |
| Productions | Existing authorized Theatre cards/detail and published cast/callbacks; published announcements are narrow discovery only | Parent theatre HTTP read and roster/lifecycle tests pass; exhaustive multi-show/unrostered persona matrix pending |
| Schedule | New complete returned-window list and event detail; Home/Theatre/production rows open the actual event | Existing audience-filtered endpoints; forged event 404 tested. Not unlimited history, attendance editing or confirmation of personal assignment |
| Community | Existing channels, posts/replies, hearts and photo attachment | Parent channels read and scoped search/source tests pass; device upload and mutation acceptance pending |
| Messaging | Existing recipient/create/send/thread; server-controlled composer now shows safeguarding pause reason | Disposable revoked-participant history cutoff and rejected send verified over HTTP; 36 transactional policy checks pass. Mixed-role/multi-device acceptance pending |
| Notifications | Existing inbox/mark-read; new shared allowlisted inbox/OS response routes, cold-response listener and token rotation | Parent inbox read and mocked route/device-service contracts pass. OS arrival, foreground/background and tap behavior unverified |
| Forms | Own-assignment acknowledgment/signature/text; explicit closed/read-only and structured-form website-required states | Closed/structured direct submits rejected with zero submission rows. Guardian-on-child structured forms remain web-only |
| Volunteer | Existing signup/cancel/approval requests reuse backend | Existing cancellation/lifecycle regressions pass; end-to-end device mutations pending |
| Account / family | Read-only account and active family list; minimized caregiver entries render first name/logistics-only label without assuming ID/full name | Parent family HTTP read passes. Native contact/profile edits, Family & Pickup management and caregiver logistics screen remain missing |
| Videos | Existing authorized library/player | Backend privacy/storage-path regressions pass; device playback, expiry and no-cache behavior pending |
| Staff operations | Limited member-style experience, not staff web parity | Casting/task creation, attendance, safeguarding review, family approvals and production completion/archive remain web workflows |

## Backend safeguards and local migration

New `FormWriteGuard` locks relevant assignment/form/production rows inside callers' transactions and delegates to the unchanged lifecycle policy. Applied to family and legacy submit/review, definition save/toggle/assign, group assignment and dynamic field/version writes. Both assignment and definition production scopes are checked; organization-wide null scope remains supported. Existing actor permissions, relationships, CSRF, audit and history remain intact. This guard does not grant actor authorization.

Legacy mobile submission also rejects active structured fields, preventing simple acknowledgments from bypassing required dynamic answers. Detail exposes `read_only` and `requires_web`. Tests cover closed/unknown/missing and supported lifecycle states, locking queries and call-site contracts. **Not proven:** concurrent completion/submission, lock-order deadlocks, all direct staff POSTs and mixed-production staging cases.

Family & Pickup's local failure was an absent existing migration **086**, despite runtime schema version 84 passing. Reviewed and applied `database/migrations/086_account_contact_and_audition_age.sql` to **local MAMP demo only**: seven nullable user/contact columns and three nullable age-eligibility columns. No account rows removed or DOB values invented/backfilled. SHA-256: `4f03fef4765ec8c33b6e40707da8c9d3dcb32a7460733e88b30880b067cdd5d6`.

MySQL DDL is not rollbackable: the first ALTER succeeded before a local runner comment-parsing error interrupted the second; inspected complete column groups and resumed only the missing group. Final schema preflight and student profile load pass. `bin/check-schema.php` now checks all ten 086 columns read-only, not an exhaustive schema audit. Hosting migration state, backup and release approval remain separate; no production schema was inspected or altered.

## Safeguarding verification boundaries

| Boundary | Actual evidence | Still required |
|---|---|---|
| Active guardians | Existing family filters preserved; local parent read; policy suite synchronizes eligible guardians | Mixed parent/caregiver and every endpoint after relationship revocation |
| Former guardian | HTTP fixture's fixed history cutoff hides later message and rejects send; 36-test policy suite includes relationship revocation in rollback transaction | HTTP fixture revokes only its disposable participant, not a real demo relationship; full multi-device relationship revocation |
| Student / production / staff | Existing server audience filters retained; forged form/event IDs denied; privacy/roster/lifecycle regressions pass | All student-only schedules, groups, multiple shows and staff role-removal bearer checks |
| Closed/archived forms | Closed detail readable; direct submit fails without submission write; definition guards fail closed | Staging edit/review/assignment and concurrent transition tests |
| History / audit | Existing services and audit writes preserved; only disposable test records cleaned | No real production completed/archived or audit records purged |

Stale composers/forged links still meet authoritative backend checks. Foreground identity refresh and focus reload reduce stale views but cannot instantly remove content already visible offline. A privacy/cache threat review is necessary before real-family use.

## Push audit: storage is not delivery

Implemented explicit Settings registration/removal, OS permission handling, Android channel creation, secure stored identity, rotation, signed-in notification response handling and allowlisted internal routes. External/arbitrary destinations fall back to the inbox; destination APIs still authorize access. No bearer token is handed to a PHP web URL.

Backend registration is authenticated; identity validation is bounded. Deletion/logout are scoped to the calling user/device. HTTP tests prove another user cannot delete a registration and logout removes it/revokes the calling bearer. Registration used a **disposable fake token**, not an APNs/FCM handshake. Permission denial and rotation tests use **mocked native modules**, not actual OS prompts.

The response remains deliberately `native_pending`. Existing PushService is Web Push/VAPID, not native APNs/FCM. Missing: selected native provider/sender, APNs credentials/entitlements, FCM service/project configuration, build/distribution credentials, native dispatch/receipt/invalid-token handling and proven delivery-time authorization. Native production/community/safeguarding delivery is **blocked and unverified**. Reuse existing preferences, production lifecycle and current guardian policy at dispatch rather than creating mobile-only authorization.

Online logout cleanup passed. Offline logout clears local credentials and warns cleanup is unconfirmed; device identity remains for a later authenticated cleanup attempt. An offline client cannot revoke its old server bearer. Before enabling native delivery, resolve cleanup retry/account-switch ownership and OS Settings permission revocation on resume. Sign-in does not automatically enroll alerts.

## Verification ledger

| Executed check | Result / scope |
|---|---|
| Clean lockfile install, native TypeScript, `expo install --check` | Pass; dependency/source checks |
| Expo Doctor | **20/20 pass** after required native peer correction |
| `npm run test:integration` | **6 pass**, Node/TypeScript-transpiled API/navigation/device contracts with native mocks |
| Android + iOS Expo export | Pass, Hermes bundles; not signed/installed apps |
| All `tests/pilot_*.php`, Action Center, operational presentation, Community search, safeguarding review | Pass; predominantly stub/source tests; warning-only fixture deficiencies corrected |
| `tests/pilot_closed_forms.php` | **18 pass**, stubbed locking/lifecycle and source boundaries |
| `tests/safeguarded_conversation.php` | **36 pass**, real local DB transaction rolled back |
| `tests/native_api_integration.php` | **29 pass**, local HTTP bearer/reads/forged IDs/push ownership/logout/closed and structured submit/cutoff/send rejection |
| PHP syntax, browser JS syntax, diff whitespace | Pass; not runtime coverage |
| Local schema/profile and Family & Pickup browser smoke | Pass at 390px: no SQL error or horizontal overflow; no pickup mutation |
| iOS simulator | **Not run**: `xcrun simctl list devices` failed, simctl unavailable |
| Android emulator / physical devices | **Not run**: adb unavailable; no attached test device |
| Push arrival/banner/tap/revocation | **Not run**: native provider/build/device infrastructure missing |
| Remote web safety / Expo CI | Pending branch push; final evidence to be recorded |

Local HTTP reproduction: `CTSMD_NATIVE_INTEGRATION=1 APP_ENV=local DB_PORT=8889 php tests/native_api_integration.php`, local PHP router on 8093. Uses demo personas 7/6/13 and disposable token/form/thread fixtures cleaned in `finally`; no delivery worker. Do not run on hosting/real family data. Local QA outputs outside Git: `/tmp/ctsmd-phase4-native-export-final` and `/tmp/ctsmd-phase4-family-390.jpg`.

`npm audit --omit=dev` reports **8 advisories (3 high, 5 low)** in Expo tooling dependencies, including Zod collection-validation exhaustion and deepmerge prototype poisoning. npm's forced proposal downgrades Expo to 51; not applied. Compatibility passing does not close security advisories. Patched SDK-compatible tooling/exploitability review is required before release; no claim these do or do not affect shipped native runtime.

## Dated acceptance checklist / recommended Phase 5

- [x] Existing app audited; current SDK retained and native peers corrected.
- [x] Shared native design reused; real event navigation and explicit form/send/permission states implemented.
- [x] Local bearer/policy checks and scoped closed-form guards verified as above.
- [x] Existing DOB migration applied locally and checked, no production change.
- [x] Automated local checks and both bundle exports pass.
- [ ] Physical-device accessibility, cold start/deep links, offline/account switching and multi-persona safeguarding acceptance.
- [ ] Native push infrastructure, shared dispatch authorization and actual delivery/revocation acceptance.
- [ ] Prioritized missing guardian forms, family/profile/caregiver and staff parity; not substitute placeholders.
- [ ] Dependency advisory review, signed internal builds and release-owner approval.
- [ ] Existing operational/security/backup/retention/email queue pilot gates signed off. Phase 4 does not override board-pilot NO-GO.

Recommend Phase 5 as a **staging-only device and safeguarding acceptance sprint**: disposable parent/student/staff accounts on physical iOS/Android; session replacement, mixed active/former guardians, student-only and closed resources, multiple productions and write actions first. Then implement native dispatch with shared delivery-time policy/receipts before enabling alerts. Follow with VoiceOver/TalkBack, largest text, keyboard, tablet/dark-mode decisions and signed internal distribution. Expand missing guardian/family APIs only through shared backend contracts after those boundaries pass. Actionable priorities remain in [TODO.md](TODO.md).
