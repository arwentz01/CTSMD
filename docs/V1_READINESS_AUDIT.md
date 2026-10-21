# V1 Readiness Audit

Date: 2026-10-08

## Summary

CTSMD Connect is materially beyond the original prototype described in `README.md`, but V1 is not ready to ship as a production system. The strongest areas are the server-rendered product surface, role-aware navigation, schema guards, and domain coverage across families, productions, forms, volunteer work, communications, files/resources, Playbills, and safeguarding. The main blockers are deployment/routing confidence, missing automated regression tests, documentation drift, and several backlog items that the product docs still describe as pending runtime verification.

## Readiness Findings

### R1 - Local runtime URL currently points to another app

- Evidence: `http://localhost/CTSMD/app` returns a fatal error from `/Applications/MAMP/htdocs/Operations/...`.
- Impact: Fresh-browser, mobile, PWA, and routing QA can produce false results until Apache is corrected.
- Recommended fix: Correct the local vhost/document root, then repeat smoke tests for `/`, `/login`, `/app`, `/production`, `/production/people`, `/production/groups`, `/admin/accounts`, `/people`, `/resources`, `/files`, `/messages`, and `/channels`.
- Classification: Blocker.

### R2 - README understates implemented platform capabilities

- Evidence: `README.md` says the prototype does not yet implement production authentication or push notifications. Code now includes `Auth.php`, RBAC migrations, `PushExperience.php`, `PushService.php`, push migrations, account activation, password reset, and notification preferences.
- Impact: New maintainers or deployment operators may follow stale assumptions and miss required migrations/configuration.
- Recommended fix: Update README to match the current authentication, RBAC, email, push, schema-guard, and migration state.
- Classification: Documentation gap.

### R3 - No first-class test runner is present

- Evidence: No `composer.json`, `phpunit.xml`, `package.json`, or project test script was found. `bin/console` does not exist.
- Impact: High-risk areas such as safeguarded messaging, guardian visibility, membership gates, production context, form assignments, volunteer eligibility, and file/resource access are protected mostly by manual review and DB constraints.
- Recommended fix: Add a small CLI test harness or PHPUnit setup covering access policy, route authorization, schedule audience resolution, guarded conversation participants, form assignment subject rules, and production group membership integrity.
- Classification: Quality blocker before V1 launch.

### R4 - Runtime verification remains marked pending in canonical backlog

- Evidence: `docs/TODO.md` marks runtime verification pending for multiple implemented slices, including production day, readiness, staff dashboard, organization resources, DB-backed home/member libraries, registration intake, platform registration, and public registration.
- Impact: The docs themselves say important V1 workflows have not received complete local runtime verification.
- Recommended fix: Convert pending verification notes into a V1 smoke checklist and close each item with a dated pass/fail result.
- Classification: Release readiness gap.

### R5 - Schema guard is current through migration 060, but migration folder contains newer files

- Evidence: `RuntimeSchemaGuard` expects schema version 60. `database/migrations/` contains 62 SQL files, including `061_moderation_starter_library.sql`, `20260920_mobile_api_tokens.sql`, and `20260920_mobile_push_subscriptions.sql`.
- Impact: Newer migrations may be optional or intentionally outside the runtime guard, but the contract is not explicit. Fresh installs can become ambiguous.
- Recommended fix: Document which migrations are required for V1 runtime and either extend the guard or explicitly mark later migrations optional/operational.
- Classification: Deployment risk.

### R6 - Audit logging exists, but audit coverage is uneven by feature

- Evidence: Many workflows write `audit_events`, including forms, schedule, attendance, files, resources, teams, community moderation, Playbill, account profile/password changes, and safeguarding credentials. Some account and membership lifecycle operations rely on state changes without a central audit service.
- Impact: V1 safety and administration claims are stronger if sensitive actions share a consistent audit helper and review surface.
- Recommended fix: Centralize audit event writes behind a small service and inventory sensitive actions that should always emit an audit event.
- Classification: Product/technical hardening.

### R7 - Public landing is intentionally lean, not a full website replacement

- Evidence: `docs/TODO.md` keeps full public website/CMS bridge, broad public calendar, class/camp/workshop registration, payments, and expanded public production detail pages as future options only.
- Impact: V1 should be framed as private CTSMD Connect plus limited public registration/playbill surfaces, not a public website replacement.
- Recommended fix: Keep launch messaging and acceptance criteria aligned to this boundary.
- Classification: Product scope boundary.

## Validation Results

- PHP lint: passed for all repository PHP files using `/Applications/MAMP/bin/php/php8.3.30/bin/php -l`.
- Schema guard: passed with `SchemaGuard::requireCurrentSchema(__DIR__, '')`; output `schema guard ok`.
- Schema inventory: local database reports `tables=83`; migration folder reports `migrations=62`.
- Audit-event integrity query: `audit_required_field_issues=0`; `audit_invalid_metadata_json=0`.
- Diff whitespace check: `git diff --check` passed.
- Automated tests: not run because no test runner or project test command exists.
- Route smoke through MAMP Apache: blocked because Apache served `/Applications/MAMP/htdocs/Operations`, not this repository.

## Recommended V1 Gate

Do not call V1 ready until these are done:

1. Correct local/deployment routing so `/CTSMD` serves this repository.
2. Run and record a fresh-browser smoke pass for public, auth, member, production, staff/admin, communication, files/resources, forms, volunteer, and safeguarding routes.
3. Resolve README/runtime documentation drift.
4. Decide whether migrations after 060 are required for V1 and make the schema guard/docs match that decision.
5. Add at least a minimal automated regression harness for access/safety rules.
