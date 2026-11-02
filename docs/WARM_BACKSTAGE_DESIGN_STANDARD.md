# CTSMD Connect — Warm Backstage Design Standard

**Status:** Approved design direction; **implementation is incremental, not a whole-app redesign**  
**Decision:** B2 **Warm Backstage** is the default visual foundation. Borrow selected B3 **Theatre Forward** elements in high-attention production and public contexts.  
**Scope:** Authenticated web operations, family/student spaces, Community, messaging, safeguarding, public production surfaces, and React Native / Expo parity.  
**Change control:** This document is a design standard. It does **not** authorize changing permissions, backend behavior, safeguarding boundaries, or existing workflows.

## 1. Source of truth and provenance

The design decision follows the three-skill audit and isolated exploration:

- `emil-design-eng`: intentional interaction feedback, purposefully short transitions, reduced motion, and overlay behavior.
- `impeccable`: clear, state-rich operational UI, hierarchy, accessibility, and restrained decoration.
- `design-taste-frontend`: distinctive but functional product identity, readable typography, purposeful information density, and contextual theatre visuals.

The selected local design reference was reported at:

`design-explorations/ctsmd-design-evolution/warmth-theatre-experiment/`

Its `index.html`, `COMPARISON_REPORT.md`, and rendered screenshots should be reviewed when available locally. **The exploration files were not present in the connected GitHub branch when this standard was written; specific B2 prototype values have therefore not been independently extracted or verified.** Any precise prototype-only colors, fonts, spacing or image treatments must be reconciled with this standard before implementation. Keep the source exploration intact.

Existing implementation baseline: `assets/css/design-system.css` and the existing authenticated application shell. The baseline already provides semantic color, spacing, typography, state, border, control-height, and elevation tokens. Reuse and refine these instead of creating a competing token namespace.

## 2. Product character

**Warm, capable, reassuring, and unmistakably CTSMD.** The metaphor is a welcoming, well-run backstage: tasks are obvious; production identity is present; the interface respects busy people.

- **Operational clarity first.** Users should know what requires attention, who owns it, what happens next, and what is safe to do.
- **Warmth without clutter.** Warm neutral backgrounds, measured contrast, natural language, approachable typography, and deliberate spacing. Avoid sterile gray-on-gray monotony **and** theatre-themed decoration on every card.
- **Theatre identity as context.** Production artwork, show titles, program-like headings, and sparse gold details communicate show identity. They do not replace functional labels or status indicators.
- **One platform, different working modes.** Staff screens can be dense; family/student screens must be inviting and understandable; public theatre pages can be more expressive.
- **Safeguarding is structural.** Child/guardian distinctions, permissions, restricted content and audit workflows remain explicit and authoritative.

## 3. Design tokens and visual roles

### Canonical implementation tokens

The following values are **existing repository baselines**, not claims about the uninspected B2 prototype.

| Role | Canonical token | Existing baseline | Intent |
| --- | --- | --- | --- |
| Primary action | `--brand-primary` | `#a6192e` | CTSMD red, used with restraint |
| Primary hover | `--brand-primary-hover` | `#74101e` | Interactive response |
| Selective accent | `--brand-accent` | `#f4bd3f` | Gold for production identity/active context, not competing CTAs |
| Page | `--surface-page` | `#f7f4f2` | Warm background |
| Card | `--surface-card` | `#fff` | Legible working surface |
| Raised / editorial | `--surface-raised` | `#fffdfa` | Subtle warmth when appropriate |
| Primary text | `--text-primary` | `#171419` | Readability |
| Secondary text | `--text-secondary` | `#3b3034` | Explanatory content |
| Default border | `--border-default` | `#e6e0e3` | Quiet grouping, not excessive dividers |
| Success / warning / danger / info | `--state-*` | Existing semantic tokens | State meaning, never color alone |

Use semantic tokens in components, not copied hex values. Before changing any baseline token globally, assess contrast, existing overrides, public screens, and all affected states.

### Typography

- Use the canonical UI sans (`--font-sans`) for navigation, controls, tables, messaging, and operational content.
- Reserve the canonical serif (`--font-serif`) for production titles, limited editorial headings, Playbill and public expression; not routine forms or tabular data.
- Prefer readable **sentence-case** controls, page labels, and statuses. Avoid tiny, heavily letter-spaced all-caps text as the primary means of conveying information.
- Existing `--type-body-size:14px`, `--type-label-size:11px`, and `--type-micro-size:11px` are implementation baselines, **not universal target sizes**. Improve readability where necessary, especially on mobile; test against WCAG 2.2 AA rather than assuming any particular number proves compliance.
- Preserve meaningful hierarchy: page title → key action/context → section titles → body/metadata.

### Spacing, surfaces and elevation

- Reuse `--space-1` through `--space-8` (4/8/12/16/24/32/48/64px existing scale).
- Prefer consistent gutters and section rhythm over one-off padding.
- Use borders and surface contrast for most grouping; shadow is reserved for true layering (e.g. floating menus/dialogs or selected raised panels).
- Radius and elevation should communicate component role, not aesthetic variety. Use existing `--radius-*` tokens.
- Avoid decorative gradient/shadow layering that competes with production imagery or status cues.

## 4. Canonical component behavior

Every **new or substantially revised** operational screen must reuse documented primitives or add shared primitives in the established design system rather than extending an arbitrary page-local CSS override stack.

| Component | Standard |
| --- | --- |
| Primary button | One dominant primary action per decision area; clear text label; hover, pressed, focus-visible, disabled, loading and success/error feedback |
| Secondary / destructive button | Consistent placement and prominence; destructive actions confirm scope and consequences |
| Inputs / search | Visible labels, predictable validation, inline error text, keyboard support; filters preserve selected state |
| Status indicators | Text + shape/icon where useful, semantic color only as supporting signal; use consistent labels for `under review`, `callback`, `offered`, `cast`, `paused`, `sent`, `failed` etc. |
| Alerts / notices | State the issue, consequence, and next action; use live-region behavior when the status changes dynamically |
| Cards / panels | Preserve a useful hierarchy; do not make every operation an oversized decorative card |
| Tables / lists | Preserve density and scanability for staff; sorting/filtering/bulk actions where workflows warrant them |
| Drawers / dialogs | Focus entry, trap, Escape/close behavior, focus restoration, scroll handling, clear errors, and accessible naming |
| Empty / loading / error states | Say what happened and what a person can do next; skeletons and loaders should not disguise failures |
| Navigation | Explicit destinations and current context; role/permission-aware access; no visually implied controls without actions |

Use short purposeful motion with `prefers-reduced-motion` support. Never make frequent actions slower for the sake of animation. Interaction feedback is subordinate to actual saving, authorization, and error behavior.

## 5. When to use B2 versus B3

**Default — B2 Warm Backstage:** Staff Overview, daily operations, Community, Messages, Safeguarding, registrations, family dashboard, volunteer shifts, admin tools, forms, dense workflows and most native screens.

**Selective B3 enhancements:** Production Workspace hero/title, production switcher context, show information, public casting announcements, public production pages, Playbills, and special editorial moments. Favor **real approved production artwork** and program-style typography. A more expressive header does not justify decorative dashboards.

**Do not:** change global CTA colors per show; obscure task priority behind large hero imagery; use gold as a second equally dominant action color; turn safeguarding warnings into theatrical motifs; reproduce protected Broadway trade dress or copyrighted creative assets.

### Staff vs families

- **Staff:** compact information hierarchy, visible urgency/owner/age/next action, filterable lists, stable desktop layouts and reliable save/undo/error feedback.
- **Parents/guardians:** clear household/child context, upcoming commitments, confirmations, practical language, transparent permissions.
- **Students:** age-appropriate clarity, recognized student identity, safeguarding expectations, no adult account shown as the performer.
- **Public:** stronger theatrical personality using licensed/owned artwork and editorial structure; accessibility remains unchanged.

## 6. Information architecture principles

Do **not** replace global navigation based on aesthetics alone. Evaluate destinations and wording with actual role-based tasks.

- Distinguish **personal/family**, **selected production**, and **restricted operations** contexts.
- A user may hold multiple roles (e.g. guardian, volunteer, director). Navigation should adapt to granted permissions without duplicating destinations or hiding the active working context.
- `My Theatre` may remain a valuable personal home; it should not become an undifferentiated catch-all for unrelated staff operations.
- Group production work by user intent (e.g. Plan, Cast, Run, Communicate, Publish), while preserving all current destinations.
- Keep search and quick actions near the place they are needed, not buried beneath promotional UI.

## 7. Responsive, accessibility and safeguarding requirements

- Target **WCAG 2.2 AA** where applicable; validate actual contrast and behavior instead of asserting compliance based on tokens.
- Text must remain readable with browser zoom; controls and touch targets must be usable on mobile.
- Keyboard-only and screen-reader paths must cover navigation, search, filtering, dialogs, interactive status, and form errors.
- Always provide accessible names, visible focus and non-color state cues.
- Reduced-motion preferences must be respected.
- **Responsive density is contextual:** dense desktop tables may use horizontally scrollable regions, condensed columns or a task-oriented drill-in on mobile; do not mechanically stack every row into a giant card.
- Privacy and safeguarding controls are never cosmetic. Restricted DM review, student/guardian relations, channel visibility, and audit actions require server-side enforcement. Visual changes cannot weaken them.

## 8. Implementation rules — avoid another CSS override pile

1. **Read this document and inspect the B2/B3 prototype references** before implementing a substantial new page.
2. Inspect `assets/css/design-system.css`, the relevant shared shell and existing component patterns first.
3. Prefer modifying or extending the **owner** of a primitive over appending another global override.
4. Reuse semantic tokens rather than introducing duplicated `--red2`, `--new-gold`, etc.
5. A new page-specific stylesheet is acceptable for domain layout, but do not redefine shared buttons, alerts, inputs, typography or status rules inside it.
6. Avoid mass replacement and avoid introducing a new frontend framework only for a visual refactor.
7. Preserve existing routes, permissions, business logic, data persistence, keyboard interactions and API contracts.
8. Ship changes in small, reviewable commits with before/after screenshots for representative desktop and mobile states.
9. Keep prototypes, actual implementation, and documentation explicitly distinguished. A screenshot is a reference, not proof of behavior.
10. Where a design choice conflicts with accessibility, safeguarding or task completion, **function and safety win**.

## 9. Migration approach

**Feature-first and design-aware** is the active strategy: continue functional backlog work. Apply B2 to new work and substantially revised pages, then migrate older interfaces opportunistically.

Suggested controlled sequence once functional release gates permit:

1. Establish ownership for canonical shell, buttons, inputs, statuses, cards, tables, alerts, dialogs and typography. Record the prototype-to-token reconciliation.
2. Use **Staff Overview** as the reference implementation; evolve the shared shell alongside a real screen, not in isolation.
3. Extend the tested patterns to Production Workspace, applying B3 selectively to its production identity.
4. Apply them to Casting while preserving complex decisions, child identity, guardianship and roster workflow.
5. Incrementally unify Community, Messages, Safeguarding, family, volunteers and Playbills as they receive feature updates.
6. Align native Expo components/tokens with the same language after web patterns are validated.

**Release gates remain separate:** safeguarding, authorization, database-backed integration and pilot-readiness checks are required before claiming any feature is production-ready. A design pass never replaces these checks.

## 10. Definition of done for design-aware feature work

- [ ] The screen is recognizably B2; any B3 element has a contextual reason.
- [ ] Shared tokens and components are reused without unnecessary CSS-specificity escalation.
- [ ] No backend workflow, safeguarding rule or permission changed unintentionally.
- [ ] Primary action, empty/error/loading states and current user/production context are clear.
- [ ] Keyboard, zoom, contrast, focus and mobile behavior are checked in rendered UI.
- [ ] Desktop staff density and mobile task ergonomics are both appropriate.
- [ ] Real user data is never required for screenshots or design prototypes.
- [ ] Changes and residual validation gaps are documented.
- [ ] Screenshots and test evidence support any stated validation claims.

## 11. Design decision record

**Decision:** Adopt B2 Warm Backstage as default; selectively adopt B3 program-cover artwork and gold contrast in prominent production contexts.

**Reason:** The team preferred B2's balance of operational clarity and warmth over a visually sterile B1 or more overtly theatrical B3 treatment.

**Rejected alternatives:** Full application redesign before feature completion; wholesale stylesheet replacement; dramatic theatre styling on every administrative surface; allowing independent page-level visual systems to proliferate.

**Revisit only if:** Persona usability tests, accessibility findings, or actual production workflow data reveal specific problems. Otherwise, treat this decision as stable and return to the feature backlog.

---
_This document records the agreed design direction and current repository baselines. It does not claim to have inspected uncommitted local prototype files or verified screenshots._
