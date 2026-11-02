# Dimensions — the UX/UI audit catalogue

Thirteen dimension groups. For each item: what to look for **in a screenshot**, and
the typical severity band. Walk every group over every surface. A finding =
`dimension · severity · surface/screenshot · what's wrong · concrete fix`.

A screenshot proves *layout, content, colour, state*. It does **not** prove
keyboard order, focus management, motion/animation, exact contrast ratios, ARIA,
gesture behavior, or actual undo/recovery — for those, say so and route to a
live/automated pass. `⚠ live check` below marks the groups where this applies.

---

## Severity rubric (Nielsen 0–4)

Rate by **impact × frequency**, not by how easy it is to fix. Unified 0–4 scale: 4🔴 / 3🟡 / 1-2🟢 / 0 no emoji.

| Severity | Emoji | Meaning | Examples |
|---|---|---|---|
| **0** | — | Not a usability problem | — |
| **1** | 🟢 | Cosmetic — fix only if time allows | 1px misalignment; a slightly off shade |
| **2** | 🟢 | Minor — low priority | a non-ideal label; mild spacing inconsistency |
| **3** | 🟡 | Major — high priority, fix soon | mixed-language UI; unreadable low-contrast text; primary action not findable; trust-breaking dark pattern |
| **4** | 🔴 | Catastrophic — must fix before ship | broken/blank screen; error-page leak to users; data loss with no undo; illegible content; deceptive pricing |

When unsure between two bands, pick the higher one if it touches a primary task
or first impression.

---

## 1 · Usability — Nielsen's 10 heuristics

1. **System status** — visible nav, spinners, progress, toasts, "saved" confirmations
2. **Real-world match** — user's language/mental model, not dev terms
3. **Control & freedom** — cancel/close/back/undo on every modal/form
4. **Consistency & standards** — same concept = same word, icon, colour, place
5. **Error prevention** — confirmations on destructive actions; disabled states with reason
6. **Recognition over recall** — options visible; helper text for non-obvious formats; findable IA (see §7)
7. **Flexibility & efficiency** — shortcuts/filters/bulk actions for frequent tasks
8. **Minimalist design** — no noise competing with primary content
9. **Error recovery** — error messages in plain language say what happened and how to fix
10. **Help & documentation** — contextual help/tooltips where needed

Typical bands: 3–4 for control/freedom and error recovery failures; 2–3 for consistency; 1–2 for minimalism nits.
Cross-refs: §4 (content), §7 (IA), §9 (journey), §12 (error recovery).

## 2 · Accessibility — WCAG 2.2, POUR

- **Perceivable**
  - **Contrast** — body text ≥ 4.5:1, large text/UI ≥ 3:1 (or APCA equivalent).
    Watch for light-grey-on-white, white-on-light, brand-colour text on a tinted
    bg. *A screenshot shows obvious failures; verify exact ratios with a tool.*
  - **Colour is not the only signifier** — status/required/error also conveyed by
    text or icon, not hue alone.
  - **Text alternatives / labels** — icon-only controls have a visible or
    accessible label (flag icon-only buttons for an ARIA check).
- **Operable**
  - **Target size** — interactive targets ≥ 24×24 CSS px (WCAG 2.2 AA), ~44 px
    recommended on touch. Watch tiny icon buttons / dense table actions.
  - **Focus visible** — *can't verify from a static shot* → route to live/axe.
  - **Keyboard reachable** — *can't verify from a shot* → route to live/axe.
- **Understandable** — consistent navigation/identification; clear labels and
  instructions; predictable behaviour.
- **Robust** — semantic structure (one h1, logical heading order), valid
  components → route the un-seeable parts to axe-core / pa11y.

Typical bands: 4 for illegible contrast on core content; 3 for sub-minimum
targets on primary actions; 2 for icon-only labels pending a11y verification.

## 3 · Visual design

- **Hierarchy** — is the primary action/most-important content the most
  prominent? Can you find the main CTA in < 2 seconds?
- **Spacing, rhythm, alignment** — consistent gutters and vertical rhythm;
  elements aligned to a grid; no cramped or floating bits.
- **Typography** — a limited, consistent type scale; readable size (~16px body)
  and line length (~45–75 chars); not too many weights/families.
- **Colour semantics** — red = danger/destructive (a red *create/save* button
  reads as dangerous); green = success; amber = warning; one brand primary used
  consistently. Flag semantic mismatches.
- **Density & overflow** — tables/cards that overflow horizontally, force
  scroll, or truncate key columns; content clipped by a container.
- **Brand consistency** — logo, palette, font, corner radius, iconography
  coherent across surfaces.
- **Imagery/iconography** — icons consistent in style/weight and meaningful.
- **Motion & animation** — transitions visible in screenshots (jarring jumps,
  missing loading states where the layout shifts abruptly). Flag
  motion-sickness triggers (rapid flashing, parallax without reduced-motion
  alternative). Note: smoothness/timing need a live pass → flag as deferred.

Typical bands: 3 for an unfindable primary action or horizontal overflow on a
core table; 2 for colour-semantic mismatch; 1 for minor rhythm nits.

Cross-refs: §8 (interaction design for affordance), §10 (cognitive load).

## 4 · Content & language

The dimensions generic skills miss, yet they dominate "this feels unpolished"
impressions.

- **i18n / language consistency** — within a surface, **one** language.
  Cross-check title vs nav vs breadcrumb vs buttons vs column headers vs
  placeholders vs empty-states. Mixed EN/IT (or any pair) in one screen, or a
  button half-translated ("New Assistente"), is severity 3 — it screams
  "unfinished" on first impression.
- **No internal-jargon leak** — no class names ("…Page"), ticket codes ("JIRA-123"), file/path/script names, config keys, UUIDs, stack traces, SQL, or lorem/faker data in user-facing text.
  Severity 2–4 depending on whether it's a stray helper (2) or a config/file
  name shown to a customer (3) or a stack trace on a user error page (4).
- **Microcopy quality** — labels are human and unambiguous; terminology is
  consistent (one name per concept); empty/error copy is specific and helpful;
  no truncated/clipped strings.
- **UX writing depth** — does the microcopy guide or blame? Error messages that
  explain *what happened* and *what to do* vs. "Invalid input." CTA text that
  says what will happen ("Save changes" vs. "OK"). Tone is consistent across
  surfaces (friendly vs. clinical vs. corporate). Empty states that invite
  action rather than state the obvious ("No items yet — create your first one"
  vs. "No records found").

Cross-refs: §1 (Nielsen error recovery), §5 (empty states), §11 (trust signals
in copy), §12 (error message actionability).

## 5 · State & data coverage

The states teams forget. Capture and review each where reachable.

- **Empty state** — must **guide, not alarm**: a neutral icon + "nothing here
  yet" + a next action. A red/✕ "error-shaped" empty state, or a bare
  English "No records", is a finding (severity 2–3).
- **Loading** — skeletons/spinners, no layout jump, no frozen-looking blank.
- **Error** — actionable, plain-language, with a recovery path; never a raw
  exception (that's a severity-4 leak — see §4).
- **First-run / onboarding** — does a brand-new account see something sensible?
  Is there a guided first step or does the user face an empty dashboard with no
  direction? (Cross-ref §10.)
- **Data extremes** — review **zero / one / many / very-long**: long names
  wrapping or truncating, huge lists/pagination, overflowing tables, 0-value
  formatting.
- **Partial/degraded** — missing avatar, absent optional field, offline.

Cross-refs: §10 (onboarding depth), §12 (error recovery).

## 6 · Responsive & cross-viewport

Review the same surfaces at each viewport (mobile / tablet / desktop):

- No horizontal overflow or clipped content; layout reflows (no fixed desktop
  widths forcing a tiny mobile zoom).
- Nav collapses sensibly (hamburger/drawer) and is reachable.
- Touch targets and spacing comfortable on mobile (≥ ~44px, no crowding).
- Tables become cards/scrollers rather than overflowing.
- Critical actions remain visible (not pushed off-screen or under the fold
  without affordance).

Typical bands: 3–4 for content unreachable/broken on mobile; 2 for awkward but
usable reflow; 1 for minor mobile spacing.

---

## 7 · Information Architecture

- **Navigation structure** — is the menu/sidebar organised by user task, not by
  internal module? Is the nesting depth sane (≤3 levels)? Are there orphan pages
  with no nav entry?
- **Labeling clarity** — do nav labels match page titles? Do they use the user's
  vocabulary, not system terms? Are labels distinct (no "Settings" vs.
  "Configuration" side by side)?
- **Findability** — is there a search affordance? Breadcrumbs on nested pages?
  Does the current page highlight the active nav item?
- **Information scent** — do link/button labels promise what the destination
  delivers? A "Reports" link that lands on a raw table with no report-like
  formatting is broken scent.
- **Cross-surface navigation** — can the user move between related surfaces
  without going back to the root? Are related items linked (e.g. an order page
  links to its customer)?

⚠ live check

Typical bands: 3 for missing breadcrumbs on deep pages or duplicate nav labels;
2 for weak information scent; 1 for nav depth >3 but still usable.

Cross-refs: §1 (recognition over recall), §4 (labeling consistency), §9 (journey).

## 8 · Interaction Design

How the UI responds to what the user does — visible in screenshots where states
are captured, partially verifiable from static shots.

- **Affordances & signifiers** — do clickable things look clickable? Buttons
  look like buttons, links are underlined or coloured distinctively, cards with
  hover states have visible cues. Flag flat-design extremes where interactive
  and static elements are indistinguishable.
- **Click-target sizing** — interactive elements large enough (≥24px CSS, ≥44px
  on mobile). Flag tiny close buttons, dense table actions, overlapping
  tap targets.
- **Feedback visibility** — does a button press produce a visible state change
  (disabled-in-flight, spinner, toast)? Static shots of forms mid-submission
  should show *something* happening.
- **Gestures & touch** — swipe hints, pull-to-refresh indicators, long-press
  affordances visible on mobile surfaces. Desktop-only gestures (hover-reveal,
  right-click menus) flagged if missing mobile alternatives.
- **Form UX** — mobile keyboard matches field ($2→@, number→numeric, tel→phone); fields grouped logically; required vs optional signalled (never both); inline validation on blur; submit disabled-in-flight with spinner; multi-step with progress for long forms; smart defaults.

⚠ live check

Typical bands: 3 for indistinguishable interactive/static elements on primary
actions; 2 for missing feedback on a form submission; 1 for overlapping targets
on secondary actions.

Cross-refs: §2 (target size), §3 (motion feedback), §6 (mobile touch).

## 9 · User Journey / Flow

Coherence across screens in a task path. Requires capturing multiple sequential
surfaces.

- **Step-to-step consistency** — same concept, same name, same position across
  steps. A wizard that renames "Customer" to "Client" between step 2 and step 3
  is a finding.
- **Back-navigation integrity** — does the back button / breadcrumb return to
  the expected previous surface? Can the user exit a flow mid-way without data
  loss? (Cross-ref §12.)
- **Dead ends** — does every screen offer a next action? A "success" page with
  no "go back" or "next step" link strands the user.
- **Orphan pages** — are there pages reachable only via URL (no nav link,
  no in-context cross-link)? Flag them — they're invisible to navigation.
- **Task completion signalling** — after a multi-step action (checkout, form
  submission, import), does the user see a clear confirmation with a summary
  and a next step?

⚠ live check

Typical bands: 3 for dead-end pages or broken back-navigation on core flows;
2 for inconsistent naming across steps; 1 for missing post-completion guidance.

Cross-refs: §1 (user control), §7 (IA navigation), §12 (error recovery).

## 10 · Cognitive Load & Onboarding

- **Information density** — is the screen scannable, or a wall of undifferentiated
  text/fields? Are related items grouped (Gestalt proximity)? Flag screens
  where the user can't find the primary action within 5 seconds.
- **Chunking & progressive disclosure** — are complex forms broken into steps or
  sections? Are advanced options hidden behind an "Advanced" toggle rather than
  crowding the default view?
- **First-run / onboarding** — does a first-time user see guidance? A welcome
  state, a setup wizard, tooltips on first visit, or at minimum a helpful empty
  state? Flag a blank dashboard with zero guidance (severity 3).
- **Empty-state quality** — (cross-ref §5) but with a cognitive lens: does the
  empty state teach the user what will appear and how to make it appear?
- **Consistency as cognitive shortcut** — when the same pattern (table filter,
  modal layout, form structure) repeats, the user transfers knowledge. Flag
  surfaces that break the established pattern for no reason.

⚠ live check

Typical bands: 3 for a blank first-run dashboard with no guidance; 2 for
undifferentiated walls of fields; 1 for missing progressive disclosure on a
secondary feature.

Cross-refs: §1 (minimalist design), §5 (first-run states), §7 (IA clarity).

## 11 · Trust & Credibility

- **Social proof** — testimonials, customer logos, case-study links, user counts,
  ratings visible where they matter (landing, pricing, onboarding). Flag missing
  social proof on conversion surfaces (severity 2–3).
- **Security indicators** — padlock/HTTPS visible, "secured by" badges, password
  strength meter, 2FA setup prompts. Flag missing indicators on login/payment
  surfaces.
- **Privacy transparency** — cookie consent not hidden behind dark patterns,
  data-usage language in plain terms, unsubscribe/delete-account path findable.
- **Dark patterns** — confirm-shaming, hidden costs, forced continuity, preselected expensive options, fake urgency/scarcity, disguised ads. Flag at severity 3–4.
- **Brand credibility signals** — professional appearance (cross-ref §3), about
  page, contact info, physical address where expected, terms/privacy links in
  footer.
- **Error transparency** — does the UI admit when something went wrong in plain
  language (cross-ref §4, §12)?

⚠ live check

Typical bands: 4 for deceptive pricing or hidden cancellation; 3 for
confirm-shaming or forced continuity; 2 for missing social proof on a pricing
page; 1 for missing footer privacy link.

Cross-refs: §3 (brand consistency), §4 (transparent error copy), §12.

## 12 · Error Prevention & Recovery

What happens when things go wrong — before, during, and after.

- **Destructive-action confirmation** — delete, irreversible changes, logout
  during unsaved work — all need a confirmation dialog. Flag any destructive
  action without one (severity 3–4).
- **Undo affordance** — after an action completes, is there an "Undo" link/toast?
  Gmail-style undo on delete/archive is the gold standard. Flag missing undo on
  list-destructive actions (severity 2).
- **Input validation** — inline validation before submission, not a post-submit
  error dump. Flag forms that clear all fields on a single validation error.
- **Error message quality** — (cross-ref §4) but with recovery focus: does the
  error message say what to do? "Invalid email" → S2. "The email address is
  missing an @ — please enter a valid address" → S0. Flag unactionable errors
  (severity 2–3).
- **Graceful degradation** — does the UI handle missing optional data (no avatar,
  no description) without breaking layout? Flag layout breaks on missing data.
- **Network/offline handling** — is there a visible offline indicator? Does the
  UI prevent data loss on reconnect?

⚠ live check

Typical bands: 4 for no confirmation on account deletion; 3 for unactionable
error messages on core forms; 2 for missing undo on list operations; 1 for
minor validation UX.

Cross-refs: §1 (Nielsen error prevention/recovery), §4 (error copy quality),
§9 (flow dead-ends).

## 13 · Performance Perception

- **Skeleton & loading UX** — do async sections show skeletons or spinners
  immediately, or does the layout jump when content arrives? Flag layout shift
  (CLS) visible in screenshots — a button that moves after an image loads is a
  finding (severity 2–3).
- **Perceived speed signals** — does the UI acknowledge the user's action within
  ~100ms (button state change, spinner)? Flag actions with no visible feedback
  for >1s — the user assumes it didn't work.
- **Above-the-fold priority** — does the most important content render first?
  Flag pages where the hero/primary CTA loads last while secondary content
  (sidebar, footer, ads) renders first.
- **Optimistic UI** — where appropriate (likes, toggles, quick actions), does
  the UI update immediately and reconcile later, rather than waiting for the
  server round-trip?
- **Jank & stutter** — flag visible layout jumps between states, carousels that
  don't settle, scrolling that fights the user.
- **Progressive loading** — are large lists paginated or infinite-scrolled with
  a "loading more" indicator? Flag 1000-row tables without pagination on mobile.

⚠ live check

Typical bands: 3 for layout shift on primary CTA or content-jump on a core page;
2 for missing skeleton on a slow-loading section; 1 for non-optimistic toggle
that could be optimistic.

Cross-refs: §3 (motion/animation for transition quality), §5 (loading states),
§6 (responsive behaviour under load).

---

## Applying it

For each surface, walk all thirteen groups (most will be ✅ — note those too).
Don't invent problems; if a group is clean, say "clean" and move on.
Cross-surface findings (e.g. an inconsistency between two screens) belong to
Usability §1 (Nielsen heuristic 4, consistency), Content & Language §4 (i18n),
or Journey §9 — cite both screenshots.

Groups 7–13 (IA, Interaction, Journey, Cognitive, Trust, Error, Performance)
are the "UX depth" dimensions — they catch what a CSS/layout checker misses.
Give them the same weight as 1–6.
