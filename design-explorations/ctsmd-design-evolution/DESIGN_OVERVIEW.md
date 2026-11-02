# CTSMD Connect visual design exploration

This directory is an isolated visual proof-of-concept. It does not use active CTSMD routes, styles, database data, or live interactions. Names, productions, dates, and decisions are fictional.

## The design question

How can CTSMD feel like a calm, theatre-specific operating environment while keeping staff work legible under pressure? The three concepts test that question on the same three jobs: understand theatre-wide priorities, run one production, and make a casting decision.

## Concepts

| Concept | Visual system | Best at | Watch-out |
| --- | --- | --- | --- |
| A — Refined CTSMD | Warm paper, burgundy shell, gold theatre accent, softened existing card language | A low-risk upgrade that preserves the most familiar CTSMD signals | It retains more of the present dashboard vocabulary, so hierarchy gains are smaller |
| B — Calm Backstage Operations | Neutral backstage sheet, quiet gray navigation, deep wine only on theatre objects and exceptions | Repeated staff work, scanning, production context, and a coherent mobile model | Needs disciplined token adoption so it does not regress into generic white SaaS |
| C — Contemporary Theatre Platform | Ink-black operating room, copper highlights, bolder production art | Focused, desktop-first staff sessions and a more memorable theatre identity | Dark-density accessibility and print-like contrast need more implementation testing |

## Recommendation

Choose **B — Calm Backstage Operations** as the implementation direction.

It is the best bridge between CTSMD’s existing warm-paper/burgundy identity and an operations product that staff may use for hours. Its distinguishing move is not decoration: it gives production objects actual presence. The current show, rehearsal, call sheet, family acknowledgement, and casting role carry the theatre’s color and language; navigation and general layout deliberately recede.

The direction came from the `achromatic-ledger` app-screen style dealt by the required design-taste workflow, translated to CTSMD’s burgundy and warm neutrals rather than its default blue. It preserves the product’s confirmed palette and desktop-sidebar/mobile-bottom-navigation contract.

## Screen intent

| Screen | Question it answers first | Primary visual proof |
| --- | --- | --- |
| Staff overview | What needs my attention in the theatre today? | One decision queue alongside today’s production rhythm |
| Production workspace | What must this company do next to be ready? | A production-specific title block, next call, checklist, and people context |
| Casting board | Which roles need a decision, and what evidence is available? | Candidate rows paired with a compact role-status rail |

## Prototype map

Open `index.html` to reach each static screen. The nine desktop pages are the three `concept-[a|b|c]-[staff|production|casting].html` files. The three `mobile-b-*.html` files are independent phone compositions for the recommended direction—not scaled desktop screenshots.

## Interaction and accessibility scope

The prototype uses real links/buttons, focus indication, a reduced-motion rule, semantic page headings, and navigation labels. Controls are visual-only and do not persist or execute actions. This is not an accessibility certification: production validation still requires keyboard, screen-reader, color-contrast, browser, and real-device testing against the implemented components.
