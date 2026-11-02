# CTSMD Connect — Warmth & Theatre Identity experiment

## Scope and method

This is an isolated, visual-only experiment. B1, B2, and B3 share one Staff Overview markup file (`app.js`): identical navigation, content, card order, actions, metric count, and responsive breakpoints. Only visual tokens and visual treatment rules vary. All names and production data are fictional.

The screen’s first question remains: **what needs staff attention in the theatre today?** Its primary next action remains creating an announcement or reviewing one of the decision-queue rows.

## Screenshots

| Treatment | Desktop | Mobile |
| --- | --- | --- |
| B1 — Original Calm Backstage | [Desktop](screenshots/b1-desktop.jpg) | [Mobile](screenshots/b1-mobile.jpg) |
| B2 — Warm Backstage | [Desktop](screenshots/b2-desktop.jpg) | [Mobile](screenshots/b2-mobile.jpg) |
| B3 — Theatre Forward | [Desktop](screenshots/b3-desktop.jpg) | [Mobile](screenshots/b3-mobile.jpg) |

## What changed—and what did not

| Constant across all treatments | B1 | B2 | B3 |
| --- | --- | --- | --- |
| Navigation, information order, metrics, rows, content, buttons, and responsive layout | Neutral operational baseline | Same system, with warm stone surfaces, wine actions, gentle panel depth, and warmer person tokens | Same system, with dark program-like surfaces, gold action contrast, and a selective cover-art treatment |

## Subjective design assessment

Scores are design judgments, not usability-study results. Each category is weighted exactly as requested.

| Treatment | Clarity & task efficiency /25 | Personality & warmth /25 | Theatre identity /20 | Accessibility & readability /15 | Consistency & maintainability /15 | Weighted total /100 | Judgment |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| B1 — Original Calm Backstage | 24 | 12 | 12 | 15 | 15 | **78** | Very clear and durable; its calm starts to feel anonymous beside CTSMD’s community promise. |
| B2 — Warm Backstage | 24 | 22 | 17 | 14 | 14 | **91** | Best balance. The warmth is carried by surfaces, people, and the theatre-red action—not by extra layout complexity. |
| B3 — Theatre Forward | 22 | 20 | 20 | 13 | 11 | **86** | Most memorable and recognisably theatrical; strongest when it is deliberately scoped to a high-attention staff setting. |

### B1 — Original Calm Backstage

Its virtue is that it gets out of the way. Neutral chrome supports scanning, and the production panel provides the sole recognisable theatre moment. The trade-off is emotional temperature: it could be mistaken for a competent operations product from almost any organization.

### B2 — Warm Backstage

B2 keeps B1’s hierarchy and density but shifts the experience from a white work sheet to a welcome desk just inside the theatre. Low-chroma stone surfaces, a controlled wine action, warmer avatars, and restrained elevation introduce hospitality without diluting urgency. Its theatre identity comes from CTSMD’s burgundy/gold and production language rather than literal stage props.

### B3 — Theatre Forward

B3 treats the production object like a small program cover in a backstage control room. Deep charcoal makes the gold primary action and red production field more meaningful; the concentric art motif suggests a spotlight without becoming an ornamental backdrop. It is visually strongest, but dark operational surfaces require a tighter component system and more real-world accessibility validation.

## Measured checks

### Render inspection

`inspect.mjs --app` was run on all three standalone treatments at 1440px, 768px, and 390px.

| Treatment | Blocking inspection failures | Non-blocking notes |
| --- | ---: | --- |
| B1 | 0 | Dense screen retains 11–12px sublabels; panel-density and color-sprawl heuristics were flagged as review points. |
| B2 | 0 | Dense screen retains 11–12px sublabels; warm color allocation was flagged for human review. |
| B3 | 0 | Dense screen retains 11–12px sublabels; richer dark color allocation and two deliberate type voices were flagged for human review. |

Those warnings are not claimed as passes. In a production implementation, elevate any essential 11–12px labels where staff must read them at a distance, and test actual content volumes.

### Contrast calculations

The following normal-text and interactive-control pairings were calculated from the literal CSS color values using WCAG relative luminance. All exceed the WCAG 2.2 AA normal-text threshold of **4.5:1**.

| Treatment | Pair sampled | Ratio | Result |
| --- | --- | ---: | --- |
| B1 | `#242222` on `#ffffff` | 15.83:1 | AA pass |
| B1 | muted `#65615f` on `#ffffff` | 6.13:1 | AA pass |
| B1 | wine `#752238` on white | 10.28:1 | AA pass |
| B2 | `#302724` on `#fcf8f6` | 13.80:1 | AA pass |
| B2 | muted `#6f5e57` on `#fcf8f6` | 5.83:1 | AA pass |
| B2 | white on wine `#8e263b` | 8.43:1 | AA pass |
| B3 | light text on `#1c1b1d` | 15.51:1 | AA pass |
| B3 | muted `#c9b8ac` on `#1c1b1d` | 8.93:1 | AA pass |
| B3 | dark label `#231b12` on gold `#e1b659` | 8.92:1 | AA pass |

The prototypes also provide native buttons/links, a visible focus treatment, responsive layout rules, and `prefers-reduced-motion` handling. This is **not** a complete WCAG 2.2 conformance claim: keyboard flow, focus trapping (where overlays exist), semantic announcements, touch target dimensions, zoom/reflow, screen-reader behavior, and real-device/browser testing remain required for production.

## Recommendation: a B2/B3 hybrid

Adopt **B2 as the default staff operations system**, with exactly three B3 borrowings:

1. Use B3’s program-cover production artwork for the active production object and key public-facing production moments—not for every card.
2. Use B3’s dark canvas only for intentional contexts: production command pages, rehearsals/tech-night views, or optional low-light staff mode. Keep the default overview light for long daily sessions.
3. Keep B3’s gold only as a decisive-action or theatre-mark accent; do not turn it into a broad status color.

That hybrid preserves B2’s friendly legibility while giving CTSMD a visual moment that a generic SaaS product cannot claim.

## Appropriate variation by audience

| Surface | Appropriate treatment | Why |
| --- | --- | --- |
| Staff operations | B2 default; limited B3 production object | Staff need continuous scanning and low cognitive noise, with just enough theatre context to orient them. |
| Parent/student experiences | B2-derived warm light system | A welcoming, high-clarity surface supports family tasks, safeguarding-sensitive communication, forms, schedules, and mobile use. Avoid dark drama where it could feel intimidating or hide important status. |
| Public production pages | B3-forward, with more room for program typography and real artwork | Public pages can earn stronger theatre atmosphere because persuasion, discovery, and production excitement matter more than repeated operational scanning. Keep content and actions accessible. |

## Non-goals

No production route, application source, backend service, authorization rule, database schema, branch, or commit was modified. The experiment pages are visual-only and have no live CTSMD behavior.
