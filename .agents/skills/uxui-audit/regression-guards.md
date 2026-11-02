# Regression guards — lock the fix (framework-agnostic)

Optional, on request. A review that ends at a report decays — the same issues
creep back. For each high-severity finding, suggest a **guard**: a small test in
the **user's own stack** that fails if the issue returns. This skill does not
write the app's tests blindly — it tells the user what to assert and where; the
user (or a TDD flow) implements it in their framework.

Do **not** assume a framework. Detect what the project uses (Playwright,
Cypress, Vitest+Testing-Library, Jest, Pest/PHPUnit feature tests, RSpec system
specs, Selenium, axe-core CLI…) and phrase the guard for that.

---

## What kind of guard fits which finding

| Finding type | Guard | How (any stack) |
|---|---|---|
| Wrong/leaked **title** ("Users Page", "ListUsers") | assert the rendered title text | render the page, assert it contains the correct title and **not** the class-name string |
| **Mixed language / untranslated** default | assert the localized string is present, the foreign one absent | assert page contains "per pagina" (or whatever) and not "Per page" |
| **Internal jargon leak** (codes, file/script names, config keys, UUIDs, stack traces) | assert the forbidden substring is absent in user-facing output | `assertDontSee("DEPLOY-2-F")`, `not toContain(".php")`, regex for ticket codes |
| **Contrast / ARIA / focus** | run an automated a11y engine in CI | `axe-core` / `pa11y` assertion on the route, fail on violations of the chosen impact level |
| **Error-shaped empty state** | assert the neutral copy renders | seed zero rows, assert the empty-state heading is the guiding text, not "No records" |
| **Render regression (the 500 class)** | a render/smoke test per page | render each key route as the right user, assert status < 500 |
| **Overflow / responsive break** | a visual or layout assertion | Playwright/Cypress screenshot-diff at the breakpoint, or assert no horizontal scroll |

## Principles

- **Target the smallest stable signal.** Prefer asserting the corrected text /
  the absence of a forbidden substring over brittle pixel diffs. (E.g. assert
  the `<title>` tag content, not a whole-page screenshot, when the issue is a
  title.)
- **Avoid false-positives from non-user-facing strings.** A class name like
  `ListUsers` can legitimately appear in a component/snapshot path — scope the
  assertion to the visible title / a specific element, not the whole HTML.
- **Put a11y in CI, not eyeballs.** Contrast/ARIA/focus are what a screenshot
  can't prove — encode them as an axe/pa11y check that runs on every push.
- **Render-smoke is cheap insurance.** If the project has no per-page render
  test, recommend one: it catches the blank/500/error-page class of regression
  that a green unit suite misses.

## Handoff

Rather than fixing inline, hand the prioritised finding list (S4/S3 first) to a
planning or TDD flow — e.g. a `devplan` skill: "write the fixes test-first, one
guard per finding". Keep **review** and **remediation** as separate steps so the
review stays an honest assessment, not a self-graded change.
