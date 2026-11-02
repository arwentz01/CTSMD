# Capture — getting the screenshots

Three methods, pick by what's installed. All feed the same Analyze phase.

---

## Method A — bundled Playwright script (recommended, generic)

Works against any running web app via a JSON config. No project coupling.

**Requirements**: Node ≥ 18 and Playwright.

```bash
npm i -D playwright           # or: npm i -g playwright
npx playwright install chromium
```

**Run**:

```bash
cp scripts/capture.config.example.json uxui-audit.config.json
# edit uxui-audit.config.json — baseUrl, routes, viewports, (optional) auth
node scripts/capture.mjs uxui-audit.config.json
# → screenshots in ./.uxui-audit-runs/<timestamp>/
```

Config keys:

| key | meaning |
|---|---|
| `baseUrl` *(required)* | the running app, e.g. `http://localhost:3000` |
| `routes[]` | strings (`"/settings"`) or `{ "name", "path" }` objects |
| `viewports[]` | `{ "name", "width", "height" }`; default mobile/tablet/desktop |
| `storageState` | path to a Playwright auth state for gated UIs (see below) |
| `fullPage` | capture below-the-fold (default `true`) |
| `waitMs` | settle delay after load (default `600`) |
| `waitUntil` | goto wait (`networkidle` default; `load`/`domcontentloaded`) |
| `outDir` | parent dir; a timestamped run folder is created inside |

The script keeps going on per-page errors and **keeps the screenshot of a
broken/error page** — a 500 or a login-bounce is itself a finding, not a
capture failure to hide.

### Auth-gated UIs — `storageState`

Generate a logged-in browser state **once**, then reuse it:

```bash
# interactive: log in by hand, state is saved on close
npx playwright codegen --save-storage=storageState.json http://localhost:3000/login
```

Point `"storageState": "./storageState.json"` in the config. Do **not** commit
it (the `.gitignore` already excludes it). Never put real passwords in the
config or the repo.

---

## Method B — Playwright MCP (no script)

If a Playwright MCP server (e.g. `@playwright/mcp`) is connected to your agent,
the agent can navigate and screenshot through MCP tools directly — no Node
script. Drive it the same way: for each surface × viewport, navigate, settle,
screenshot, save to a run folder. Use this when you want the agent to also
*explore* (click into states) while capturing.

---

## Method C — bring your own screenshots (zero dependencies)

No tooling at all: drop PNGs into a folder (name them
`<surface>-<viewport>.png`) and point the skill at it. The heuristic analysis
runs unchanged. Good for designs, Figma exports, or environments where you
can't run a browser.

> **Analysis fallback**: if the model isn't multimodal, see the Analyze phase
> in `workflow.md` for the image-description MCP fallback path.

---

---

## Viewport presets (reasonable defaults)

| name | size | represents |
|---|---|---|
| mobile | 390 × 844 | modern phone (portrait) |
| tablet | 768 × 1024 | tablet (portrait) |
| desktop | 1440 × 900 | laptop |

Override per the product's real audience (e.g. add `1920×1080`, or a small
`360×640` for low-end Android).
