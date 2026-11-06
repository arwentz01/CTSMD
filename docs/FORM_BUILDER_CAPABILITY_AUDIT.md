# CTSMD Connect Form Builder: capability and expansion audit

Date: 2026-11-06
Scope: non-Commerce forms only. Source inspection of active FormBuilderIndexExperience, DynamicFormExperience, DynamicFormService, FamilyFormsExperience, FamilyFormService, FormManagementExperience, FormWriteGuard and Expo forms detail.

## Current behavior confirmed

- Staff with forms-management permission can create/edit form metadata, assign forms, configure review, and manage eight field types: short_text, long_text, single_choice, multiple_choice, date, acknowledgment, signature and resource_link.
- Fields support label, help text, required, choice options, active state, technical key and numeric sort order.
- Required resource links are tracked via explicit open events and checked before submission.
- Structured submissions have saved definition snapshots and answer rows, and staff can review submitted forms.
- The active web submission path uses field validation and form write/lifecycle guards.
- Native Expo structured form completion does **not** have parity: it sends supported simple completion modes but directs forms requiring structured web fields to the website.

## First expansion (this feature branch)

1. In-builder, read-only preview of all active field types (no member data, no submission or write side effects).
2. Up/down question ordering with server-side transaction, lifecycle guard, form version increment and audit event. Does not mutate field IDs or keys.
3. Technical field key and numeric sort order moved to a labeled Advanced disclosure.
4. Simplified builder instructions and CSS for small screens, focus and disabled controls.

## Important further opportunities

### P1: design and workflows
- Sections/information-only blocks (required database ENUM migration and family rendering, plus submission snapshot coverage).
- Email, phone, number and time/date-time questions (schema migration, server-side validation, web rendering, and native API parity required). Do not implement UI-only new types.
- Conditional visibility and conditional requiredness, using stable keys, bounded non-recursive rules and server validation on only visible questions; must be tested against old submission versions and reviewers.
- Draft answers and resume, with distinct draft and submitted state, explicit retention/privacy policy and parent/guardian authorization. Avoid client-only pseudo-drafts.
- Form cloning/templates, preserving current published questions but assigning fresh form and field IDs and no historical submissions.
- Safer editing of live forms: detect existing assignments, show publication implications, and consider draft revisions and release/publish action rather than applying definition changes to all open assignments.

### P1: safety and correctness
- Confirm field keys remain unique and immutable when answers already exist; database has uq_form_field_key.
- Use strict date parsing with actual calendar validation, not just YYYY-MM-DD regex.
- Reject rather than silently discard unknown values in multiple-choice submissions.
- Verify guardian/form assignment authority, closure/concurrency, reviewer access, attachment/resource access, and submission snapshots in migrated DB-backed tests.
- Full keyboard and screen-reader review, especially the move controls and disabled preview.
- Reconcile navigation between Manage Forms, Form Builder, Group Assignment and Review Queue.

### P2: administration
- Saved form templates, form duplication, bulk assignments, targeted reminders, completion overview and exports with access controls.
- Export redaction/retention and sensitive-form field policy, particularly student health and support needs.
- Expo structured forms API and native renderer with authorization/revalidation, no offline unsent sensitive answers by default.

## Verification boundaries
Changes committed to a feature branch only. Source-level review and file readback do not establish PHP syntax, browser acceptance, migrated database behavior, or mobile-device parity. No migration, deployment or production data changes are part of this first expansion. Complete those checks before merging.
