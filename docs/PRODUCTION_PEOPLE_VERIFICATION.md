# Production People verification

October 29, 2026. Branch `audit/board-pilot-readiness`. No production deployment/data changes, notification workers, real-account tests, dependency upgrades or new branch.

## Automated/local evidence

| Check | Result |
|---|---|
| Fresh complete source bootstrap through 099 | PASS on `ctsmd_rc1_teamfinal_20261029` |
| Production People/team/permissions/publication | PASS, 56 real MySQL assertions |
| Existing Adult/Student participation | PASS, 83 real MySQL assertions with cast-removal callers moved to Casting |
| Real HTTP web/native personas | PASS, 170 checks, two productions |
| Safeguarding | PASS, 36 real transaction checks |
| Multiprocess MySQL races | PASS, 16 checks |
| PHP safety workflow equivalents | PASS locally: PHP lint, lifecycle/form/privacy/contracts and isolated regressions |
| JavaScript syntax | PASS for workflow scripts and new People interaction script |
| Browser interactions | PASS, 24 assertions at 1440/768/390, including real create/edit/remove POSTs, modal focus, category options, deduplication, responsive fit and Escape |
| Native TypeScript and integration | PASS; 7 integration contracts |
| Android/iOS bundle exports | PASS |
| Online Expo compatibility | FAIL, existing SDK/React Native patch mismatches retained |

The existing source-contract baseline is unchanged. Reviewed functional extensions are recorded separately in `operations-feature-contracts.json`. Browser artifacts are local at `/tmp/ctsmd-team-interactions` and `/tmp/ctsmd-team-ui`; the former contains nine current roster/detail/editor captures and assertion JSON. Native exports are at `/tmp/ctsmd-team-native-export`. These local artifacts are not a deployment package.

## Required scenario coverage

| Scenario | Executed evidence |
|---|---|
| Student cast with guardian | Offer acceptance/enrollment; automatic guardian read model; existing safeguarding and HTTP suites |
| Adult cast without guardian | Independent acceptance and roster inclusion |
| Adult cast parent | One account in All People; separate cast/guardian responsibilities |
| Parent Director | Team function alongside real family link; no permission grants |
| Adult Performer + Staff | Independent cast and Staff membership; account-owned scoped revoke/restore |
| Two team functions | Creative and technical assignments; one unique person; separate assignment count |
| Multiple productions | Independently scoped cast/functions and retained other-production involvement |
| Cast removal retains family | Casting withdrawal retains team, real family links and open team credits |
| Team removal retains cast | Specific team removal retains accepted cast and Staff scope |
| Guardian revocation retains adult participation | Current family filter disappears; independent cast and team remain |
| Playbill/history | Reviewed import, real publication/public rendering, approved Student display name/withdrawal, no private fields, frozen snapshots, retained ended credits |
| No privilege escalation | Team-only Member has no Staff permissions/membership; forged actor and wrong category rejected; Staff scope requires actual Staff/Admin role |
| Closed/archived | Team save/removal and cast removal rejected; historical snapshots/credits retained |
| No duplicate notifications | No team operation notifications; actual overlapping cast-parent and cast-Staff recipients deduplicated |
| No duplicate directory people | User-ID-keyed All People and actual HTTP/browser row assertions |

HTTP tests additionally verify real team/account authorization routes, CSRF rejection, obsolete generic removal rejection, native roles unchanged by team credit, private People route denial to ordinary parent/team members, and public program privacy. The public publication test found and now covers edition snapshot ordering for sponsor rows without an `id` column.

## Limits and rollout requirements

- **Default local demo:** `ctsmd` remains behind the previously required role/schema prerequisites; 098/099 are not silently applied there. The running isolated local preview uses `ctsmd_rc1_20261028b`, where 098 was applied explicitly for browser tests. Reconcile the default demo with a reviewed prerequisite plan before expecting the new screens to work against it.
- **Hosting:** independently review prerequisites through 097, 098 DDL/keys and 099 populated legacy-public snapshot result/size/order. Back up, rehearse matched runtime/database restore, and obtain approval before migration. Do not deploy runtime ahead of its schema. Retain old functions, memberships, credits, audits and editions; do not roll back by dropping populated columns or replaying historical seeds.
- **Permission separation:** a team credit alone provides no operational production access. Administrators use the existing account Authorization roles and scoped Staff-access controls when justified. Student team-only public credits remain blocked until an approved visible existing performer profile is available; this task does not invent another Student media approval process.
- **Physical accessibility/devices:** screenshots, native dialogs and keyboard tests do not constitute WCAG certification, screen-reader testing or physical iOS/Android acceptance. Existing simulator tooling is unavailable; actual device and push delivery remain unverified.
- **Expo:** the online compatibility check still expects newer compatible patch releases, including Expo 58.0.21 and React Native 0.88.1. Existing installed versions were not changed. Bundle success does not close that release gate.
- Broader RC1 hosting, backup/recovery, publication permutations and live pilot approval remain open. No signoff is inferred from these tests.

CI evidence will be appended after the implementation push.
