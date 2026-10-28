# UX modernization audit

## Phase 2 implementation record

Started from remote-synchronized `5093b1f` on the existing branch. Each build reuses `WorkspaceUI` and `--ux-*` tokens. Cosmetic follow-ups live in `UX_POLISH_BACKLOG.md`.

### Build 1 — Family

Routes: `/family-hub`, `/parent`. Shared warm shell, compact household header, forms and authorized casting offers before child summaries, deep links to individual assignments, combined schedule, volunteer commitments and updates. No relationship, pickup, offer-response or permission rules changed. Casting data comes from the same `CastingOfferService::pendingForUser()` used by the existing Action Center.

Verification: parent account rendered in authenticated Chromium at 1440/390px; no document overflow, no blocking DOM-probe findings after removing an inherited mobile gradient. PHP lint, member roster/media and lifecycle-boundary suites pass. Existing permission conditions remain intact. Multi-child data is still iterated across all active productions with no selected-production filter. Per-child filtering and physical-device review are deferred.

### Build 2 — Student and personal Today

Route: `/app`. Reused the role-aware renderer with a concise personal header, student Theatre History action, personal action and schedule sections, and explicit links to existing Messages, Community and casting announcements when membership/production access allows. Students no longer see a meaningless household-child count. Parent/staff versions retain their distinct household links and counts. No account, roster or schedule service changed.

Verification: authenticated student desktop/phone rendering; PHP lint; inherited mobile Next up colors corrected after the contrast probe found light text on the new ivory surface. The final student probe has no blocking findings. Closed-production exclusion and cross-production roster suites pass. Optional timeline duplication is deferred.

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
