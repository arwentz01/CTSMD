# Implementation feasibility — recommended direction B

## What to build first

Build the **shared staff shell and Staff Overview hierarchy** first. It validates the global sidebar, title/action row, decision queue, production object treatment, density, and permissioned navigation before the production workspace or casting flows adopt them.

## Component plan

| Component | Existing relationship | Implementation note |
| --- | --- | --- |
| App shell + navigation | Extend `AppNavigation.php` and its existing desktop/mobile contracts | Preserve global destinations; do not create a second staff-only primary navigation |
| Title/action row | Normalize per page | One `h1`, optional finding line, one primary action only when it is real |
| Decision queue row | New shared operations primitive | Needs person, object, due state, and clear action; no generic KPI tile required |
| Production identity block | Extend production workspace | Maps artwork, lifecycle, readiness, and next-call data into one recognisable object |
| Schedule/agenda row | Normalize existing schedule data | A row handles time, production context, place, confirmation/exception status |
| Casting candidate row | Extend casting experience | Candidate, role, notes, and decision must be semantic and permission-gated |
| Mobile bottom navigation | Preserve canonical IA | Mobile is a different composition; use Home / Production / Messages / Volunteer / More, with staff destinations in More |

## Token proposal

Keep the confirmed CTSMD visual vocabulary but reduce uncontrolled variants.

| Role | Suggested value | Use |
| --- | --- | --- |
| Page ground | `#ffffff` / cool warm-gray frame `#efefec` | The shell and workspace separation |
| Ink | `#242222` | Primary text and one primary action |
| Theatre wine | `#752238` | Production art, active brand moment, high-attention object context |
| Warm paper | `#f3f3f1` | Sidebar and low-contrast utility surfaces |
| Quiet line | `#e7e6e4` | Dense row and panel separation |
| Success / attention / exception | Existing semantic tokens | Never use decoration as state color |
| Radius | 6–8px controls; 8px panels | One operational geometry, not a mix of soft cards |

## Navigation implications

No IA rewrite is proposed. The exploration follows the documented hierarchy: global shell → destination → optional section navigation → page context → actions. Production workspace local tabs remain local. Casting stays permissioned Operations rather than becoming a competing top-level system.

## Accessibility and responsive implementation

- Preserve real buttons/links and a visible `:focus-visible` treatment.
- Use text labels alongside every state; color alone must never carry queue urgency or decision state.
- Validate contrast in both the light recommended direction and any retained dark concept.
- Treat the 430px B variants as composition requirements: two-up metrics, stacked production modules, and rows that hide nonessential table columns rather than a pinched desktop table.
- Test keyboard order, zoom at 200%, screen readers, Windows high contrast, Safari/iOS, and actual staff devices before declaring accessibility complete.

## Risk, sequence, and effort

| Phase | Scope | Risk | Relative effort |
| --- | --- | --- | --- |
| 1 | Shared tokens + shell + Staff Overview | Medium: existing CSS overlap and route variants | 1–2 sprints |
| 2 | Production workspace modules and schedule rows | Medium: lifecycle/readiness data composition | 1 sprint |
| 3 | Casting list/role rail and decision states | Medium-high: permissions and offer workflows must remain exact | 1 sprint |
| 4 | Mobile compositions, states, and accessibility validation | Medium: behavior under real content and role permutations | 1–2 sprints |

## Explicit non-goals of this exploration

No production code, database schema, authentication, safeguarding, backend workflow, route, deploy, branch, or commit has been changed. The visual controls in these HTML files do not call CTSMD services.
