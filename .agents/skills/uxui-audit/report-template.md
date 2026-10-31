# Report template

The Report phase output. Triageable, screenshot-anchored, honest. Adapt the
shape, keep the discipline: every finding has a **severity**, a **screenshot**,
and a **concrete fix**; strengths are listed too; coverage gaps are stated.

---

```markdown
# UX/UI audit — <app / surface set> (<date>)

**Scope**: <N> surfaces × <M> viewports (<list>). Language yardstick: <e.g. "IT
end-to-end">. Capture: <Playwright | MCP | provided>. Not covered: <skipped
surfaces; a11y deferred to axe; states not reachable>.

## Verdict
<2–3 sentences: overall impression + the single most important thing to fix.>

## Summary
| Severity | Count |
|---|---|
| 4 catastrophic | 0 |
| 3 major | 3 |
| 2 minor | 5 |
| 1 cosmetic | 2 |

By dimension:

| Dimension | Findings |
|---|---|
| Usability | ·· |
| Accessibility | ·· |
| Visual Design | ·· |
| Content & Language | ·· |
| State & Data | ·· |
| Responsive | ·· |
| Information Architecture | ·· |
| Interaction Design | ·· |
| User Journey / Flow | ·· |
| Cognitive Load & Onboarding | ·· |
| Trust & Credibility | ·· |
| Error Prevention & Recovery | ·· |
| Performance Perception | ·· |

## ✅ Strengths (don't touch)
- <what already works — calibrates the review>

## Findings (sorted by severity)

### 🔴 [S4] <title> — <surface> · <dimension>
- **Screenshot**: `dashboard-desktop.png`
- **Issue**: <what's wrong, concretely>
- **Fix**: <the specific change>
- **Verified**: <how the refutation attempt failed — workflow.md Analyze>

### 🟡 [S3] <title> — <surface> · <dimension>
- **Screenshot**: `users-desktop.png`
- **Issue**: the page title shows the class name "Users Page"; the nav says
  "Utenti" → mixed language + leaked internal name (Content & Language).
- **Fix**: set an Italian page title "Utenti".

### 🟢 [S2] <title> — <surface> · <dimension>
- ...

## Needs a live / automated check (not provable from a screenshot)
- <contrast ratio on X → run axe-core>
- <focus order / keyboard nav on the form → live pass>
- <gesture fluidity on mobile → live pass>
- <hover state transitions → live pass>
- <LCP/CLS/INP metrics → run Lighthouse / PageSpeed Insights>

## Suggested next step
- <e.g. "fix the S3/S4 set, then add the regression guards in
  regression-guards.md", or "hand this list to /forge-flow TDD">
```

---

## Conventions

- **Sort by severity** (4 → 1); within a severity, group by surface or dimension.
- **One finding = one issue.** Don't bundle "fix the whole page".
- **Screenshot ref is mandatory** — name the exact file.
- **Dimension tag is mandatory** — findings can span multiple dimensions; list
  the primary one.
- **Fix is mandatory and specific** — a reviewer who can't say the fix hasn't
  finished analysing.
- **Strengths are mandatory** — an all-red report is not trustworthy.
- **Every S4 carries a Verified line** — how the refutation attempt failed
  (workflow.md Analyze). No Verified line → not provable from pixels: cap at S3
  and list it under "Needs a live / automated check".

## Handoff protocol (for whoever fixes)

Findings are hypotheses, not directives. Verify each against the live app
before changing code. Fix **one at a time**; after each fix, re-capture the
same `<surface>-<viewport>[-<state>]` shot and compare — the visual
red→green (workflow.md Guard). Disagreement → reject **with evidence** (cite
the screenshot or live behavior), never skip silently. Record every
outcome: accepted trade-offs and rejections go to
`.uxui-audit/accepted.tsv` (workflow.md Analyze) so the next audit doesn't
resurface them.
- **State coverage gaps** — what you didn't review, and what needs axe/live.
- Use the severity emoji set 🔴 S4 / 🟡 S3 / 🟢 S2 / 🟢 S1 (the rubric in
  `dimensions.md`); the `[S*]` label keeps S2/S1 distinct.
