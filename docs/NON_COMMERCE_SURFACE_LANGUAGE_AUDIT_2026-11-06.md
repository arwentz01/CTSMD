# CTSMD Connect non-Commerce surface language audit
**Date:** 2026-11-06  
**Branch:** `audit/board-pilot-readiness`  
**Scope:** Authenticated and public web experiences, major navigation and notification surfaces, and native Expo screens. Commerce and ticketing were excluded.

## Goal
Remove implementation/developer vocabulary from member-, family-, volunteer-, caregiver-, and staff-facing copy. Prefer **what happened, what to do next, and what remains available** over endpoint statuses, database terminology, or implementation details. Keep intentional staff workflow terms (for example, draft notices that really must be reviewed before publication).

## Initial code-level findings and changes
| Surface | Problem | Improvement |
|---|---|---|
| Casting decisions | HTTP status and host-log guidance exposed to casting staff | Actionable “couldn’t save” instructions, with no endpoint internals |
| Volunteer requirement setup | “Stable code”, “current definitions” without an explanation | “Requirement identifier”, “Volunteer requirements”, plus purpose and accepted-character help |
| Signup administration | “Public slug” | “Web address name” and a short URL example |
| Form editor | “Definition scope”, “Completion mode” | “Where this form can be used”, “How members complete this form” |
| Form designer | “Canonical” resource explanation | Explains that one Resource Library update is reflected wherever linked |
| Mobile notifications | Service worker/subscription diagnostics always visible | Collapsed “Having trouble with notifications?” help with clearer descriptions |
| Late pickup | “Recompute” and confusing queue/delivery note | “Update reviews” and explicit statement that queued does not guarantee delivery |
| Moderation term editing | “Canonical term” | “Term to moderate” |
| Schedule creation | “Resolved production/group audience” | Explains who receives a prepared message and that it can be reviewed first |
| Production day | “Attendance marks”, “readiness tasks open” | “Attendance records”, “unfinished tasks” |
| Volunteer service records | “Internal verified service records” | Staff-confirmed volunteer records |
| Native More navigation | “· website” implementation-oriented descriptions | Action descriptions and “opens in browser” when relevant |

## Reviewed but intentionally unchanged
- **Draft / publish / approve:** These often describe actual CTSMD review steps. In particular, unsent schedule notices and cast publications should remain distinguishable from published communication.
- **Safeguarding:** Restricted access and staff-only case terminology can be necessary and legally/safely meaningful.
- **Registration and family management:** “Parent/guardian”, “authorized pickup”, “safeguarded messages”, and eligibility terminology are user-meaningful.
- **Developer-only identity switching:** The local-development-only identity surface should not be treated as a public feature; confirm it remains protected rather than disguising its purpose.
- **Commerce:** Excluded, including sandbox and payment-testing terminology.

## Areas that still need live browser and device acceptance
1. **Route-by-route visual walkthrough:** Validate actual menus, action hierarchy, errors, labels, and empty states in administrator, staff, guardian, student, volunteer, and caregiver sessions. Static search cannot verify what is rendered for every permission state.
2. **Backend exception messages:** Trace user-visible `getMessage()` and fetch-error paths to their service origins; map internal failures to understandable guidance while preserving permission checks and audit logging.
3. **Notification troubleshooting:** Check the native `details` open/close behavior, keyboard access, touch layout, and service diagnostic scripts.
4. **Volunteer identifier and registration address name:** Inline examples and accepted-character guidance have been added. Verify that the guidance renders clearly and still matches server-side validation.
5. **Native links:** Validate pages that open a browser and return to Expo correctly, including accessibility labels.
6. **Content beyond page source:** Review organization-managed help text, database-stored labels/form instructions, notification templates, and existing production-specific content, which may include staff-authored terminology.

## Verification
- Added `tests/non_commerce_surface_copy.php` static regression checks for replaced wording.
- Source updates committed to development branch.
- **Not deployed.** PHP lint, browser interaction, accessibility and physical-device checks have **not been executed** as part of this audit.
- No data migration, authorization rule, or workflow changes are intended.
