# Adult Performer correction — verification

October 29, 2026. Existing branch `audit/board-pilot-readiness`, upstream baseline `bf2abeb`; implementation starts at `3537a55`. No production deployment, production schema/data changes, workers, real-account mutation or dependency upgrades.

## Executed locally

| Check | Measured result |
|---|---|
| Fresh schema + every source migration | PASS, isolated `ctsmd_rc1_role_20261029`, including 097 |
| Forward migration regression | PASS, 9 assertions on fresh schema and two previously populated isolated schemas through 096 |
| Migrated performer services | PASS, 83 assertions in `adult_participation_mysql.php` |
| Real multiprocess MySQL concurrency | PASS, 16 assertions |
| Real HTTP web/native acceptance | PASS, 146 checks, seven required personas plus second Student, two productions |
| Existing safeguarding transaction suite | PASS, 36 checks, transaction rolled back |
| PHP safety workflow checks | PASS locally, including lint, lifecycle/forms, source contracts, media/privacy and 30 RC1 tooling contracts |
| Workflow JavaScript syntax checks | PASS |
| Native TypeScript | PASS |
| Native integration contracts | PASS, 7 tests |
| Android and iOS export | PASS, `expo export --platform android --platform ios` |
| Expo dependency compatibility | FAIL: existing SDK patch mismatches; see below |
| Browser routes | 18 complete renders, 1440/768/390, no runtime errors or page overflow |
| Simulator/device | NOT VERIFIED; `xcrun simctl list devices` fails because simctl is unavailable; adb unavailable |
| Actual push delivery | NOT VERIFIED; no workers activated |

The initial `--platform all` export additionally attempted web and failed on absent `react-native-web`. Native-only Android/iOS exports above succeeded; this correction does not add Expo web support or dependencies.

## Behavior covered

Tests exercise the actual existing account-administration POST to assign/remove/restore Adult Performer; immediate native role changes; one role panel and no separate classification/transition actions; shared Student/Adult candidate selection with ordinary Member exclusion; role assignment without DOB; Volunteer/Member coexistence; independent Staff and genuine guardian memberships; Adult self-acceptance and Student guardian acceptance; two productions; cast/crew/creative functions; group inclusion and revocation; revoked-role denial without deleting retained history; no management permission grants; Student age exceptions/category/closure rules; manual profile transfer; Adult Playbill consent/withdrawal and archived snapshot preservation.

Messaging tests cover account-wide conversation creation without shared production, Adult-to-Adult messaging, guardian-inclusive Student conversations, unrelated private production access denial, former-guardian revocation, queued-recipient checks, historical cutoffs, disabled accounts, stale web sessions and native bearer revocation. HTTP acceptance includes closed-form POST enforcement and web/native access boundaries. Concurrency tests cover competing acceptance, closure versus enrollment, age exception changes, staff authority removal versus casting, and guardian revocation versus send.

Migration tests preserve populated legacy flag/review data, skip Student anomalies, retain a mapping audit, grant no permissions or blanket Member role, and prove retry does not restore a revoked role. These disposable tests are not a complete populated-host migration or restore rehearsal.

Local browser evidence: `/tmp/ctsmd-role-ui` contains screenshots and JSON for account list/detail, casting, production groups, roster and Playbill Media. Account detail was visually inspected at desktop and phone widths: Adult Performer uses the existing checkbox/description pattern, with one Authorization roles section and no replacement approval card. This is a narrow visual judgment, not WCAG certification or screen-reader/device acceptance. Recent upstream production drawers/navigation remain intact; reviewed source fingerprints document both upstream changes and this functional correction without rewriting historical baselines.

## Remaining release gates and evidence required

- **Hosting migration/recovery:** explicit approval; independent read-only prerequisite/role/trigger/ledger inventory; reviewed 093–097 statements and locks; backup and matched-runtime restore rehearsal; mapping anomalies resolved; production history/family/audit counts retained. No hosting changes authorized here.
- **Expo compatibility:** online check expects Expo 58.0.21, constants 58.0.14, font 58.0.11, linking 58.0.15, notifications 58.1.7, router 58.0.28, video 58.0.13 and React Native 0.88.1. Installed patch levels remain unchanged. Review compatible upgrades separately and rerun CI; bundle success does not close this gate.
- **Actual devices/push:** signed internal iOS/Android builds; real login/logout/account replacement; accessibility and dynamic text; denied notification permissions; token ownership/logout cleanup; foreground/background/deep-link delivery; guardian and role revocation before queued dispatch. Compilation and contract tests are insufficient.
- **Full publication/history recovery acceptance:** remaining manual credit/media transfer, closed/archive/publication permutations and recovery preserve consent, private media and history. Current focused tests are not full release signoff.
- **Broader RC1 blockers:** existing TODO and release readiness gates remain open, including historical backfill review, closure/capacity concurrency and hosted acceptance. This role correction does not authorize a real-family pilot.

CI results are recorded below after the pushed implementation is checked. Commands and isolated-fixture guards live in the committed workflows/tests so CI can repeat the checks without live accounts.
