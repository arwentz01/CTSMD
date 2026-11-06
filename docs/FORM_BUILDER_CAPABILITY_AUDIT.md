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

## Follow-up safety batch (same draft PR)

- Editing a question with saved submission answers now blocks changes to its field key or type; staff can add a new question instead. This protects interpretation of prior response records while still allowing non-structural edits. A more complete published-definition freezing model remains needed.
- Structured answer validation now rejects impossible calendar dates and unrecognized multiple-choice values, rather than accepting malformed dates or silently filtering answers.
- Added `tests/form_builder_answers.php` for valid/invalid structured answer cases. Test execution remains outstanding in the current environment.
- **Next major build decision:** draft and published versions, with per-assignment frozen definitions rather than mutable shared live questions. This requires coordinated schema, submission/API, family UI and historical review changes; it is not implemented by the current preview.

## Copy-to-edit workflow (2026-11-06 follow-up)

- Form library now has a **Make a copy** action for forms whose production is still editable. It performs a transactional copy of form settings and all question rows, preserving order, choices and linked resource identifiers. The new form starts inactive and has new form/question IDs. Assignments and submissions are not copied.
- Builder displays an explicit inactive-state explanation. Staff can edit and preview the copy before activating it from Manage Forms.
- This is **not** draft/published revision isolation. Existing form assignments still resolve the current mutable question rows, so the next phase must freeze each assignment against the published definition before permitting safe edits to an in-use form.
- Read-only or completed/archived production forms cannot be duplicated using this action, because the current lifecycle guard protects writes. Future reusable template extraction needs its own reviewed permissions and scope rules.
- Database-backed transaction tests, PHP lint and browser acceptance are still outstanding.

## Latest batch: assigned-form protection and expanded question types (November 6, 2026)

- Added `FormWriteGuard::definitionEditable`, invoked inside transactions for question creation, edits, activation and reordering, and form setting edits. Assigned forms must be copied for further changes. Existing submissions and assignments remain attached to the original form.
- Builder displays a clear assigned-form warning and disables question editing controls; the server independently rejects writes. Activation/deactivation of an existing form remains a separate availability operation and has not been redefined as version publishing.
- Added `section_heading`: an informational heading with optional supporting description, never required and never stored as an answer. Supported in editor, disabled preview, family view and validation.
- Added `email`, `phone` and `number`: purpose-specific browser inputs with server validation. Phone format accepts common international and US formatting, requiring 7–15 digits; numbers are plain signed decimals rather than monetary or calculated fields.
- Database migration `121_form_section_heading.sql` extends the `form_fields.field_type` ENUM with all four types. It is **not applied**; review live enum and migration prerequisites before applying to populated schemas.
- Added validation regression cases for the new types and unsupported answers. Tests remain unexecuted in this environment.

**Important remaining limitation:** Assignments still point to a mutable form ID. Freezing edits after first assignment protects current definitions, but does not implement a publishable revision linked to each assignment. A future true draft/publish system needs a distinct persisted revision model and backfill/migration plan, not merely relabeling active/inactive.

## Conditional questions (first implementation, Nov 6, 2026)

- A question can be shown only when **one earlier, active, unconditional single-choice question** equals an exact configured option.
- No nested condition dependencies, conditional resource links, conditional section headings, AND/OR chains, or branching across pages yet.
- Conditions persist in `form_fields.condition_json`; add migration `122_form_field_conditions.sql` after the field type migration. **Neither migration has been applied.**
- Builder has a Show this question only when disclosure. Member web form toggles conditional fieldsets and disables hidden inputs. The server uses the same conditions to require only visible answers and never stores hidden submitted answers.
- Builder constraints keep controlling questions earlier than their dependents, reject a controller's deactivation or edits while dependents remain, and prevent reorder operations that would invert the dependency.
- Form duplication preserves condition JSON; submitted form-definition snapshots include the condition.
- Added regression checks for hidden required questions, visible required questions and preservation of visible answers. **Tests have not run in this environment.**
- **Before merging:** test against a migrated non-production database, including field types, JSON conditions, valid/invalid submits, guardian submissions, controlled-source edits/deactivation, reordering, historical responses, form duplication, JS disabled behavior, and small-screen accessibility. Do not deploy before migrations and tests are coordinated.
