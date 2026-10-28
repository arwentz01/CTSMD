# UX modernization audit

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

Implementation and exact automated/visual results are recorded below after verification. This audit is source evidence, not a claim of full accessibility conformance or measured user-task improvement.
