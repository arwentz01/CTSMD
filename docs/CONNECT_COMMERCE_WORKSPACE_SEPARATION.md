# Commerce workspace separation and Seussical poster fix

> **Current Commerce documentation:** [CONNECT_COMMERCE_CURRENT.md](CONNECT_COMMERCE_CURRENT.md) records the subsequent storefront-header reuse, public-production ticket links, production drawer refresh, scheduling redesign, promo-code manager and restricted checkout. This report records the earlier administrative/public poster separation finding; its hosted observation is not evidence of a later code rollout.

## Confirmed cause

Read-only inspection of the authenticated hosted site confirmed that **Seussical Jr. (production 2) has an uploaded poster that visibly loads in production administration**, while Production discovery says **Not published**. No hosted settings, artwork or publication state were changed.

The previous Commerce `card()` required `has_poster && announcement_published_at` before emitting an image. Its URL was `/production-poster`, whose publication check correctly rejects unpublished artwork. Consequently, valid uploaded artwork was suppressed by the administrative template; simply removing the template condition would still leave the image blocked at the public endpoint.

`CommerceProductionService::productions()` already identifies uploaded poster metadata without a publication requirement. Uploads are linked through `production_artwork` to versioned `stored_files`/`stored_file_versions`; the normal private production-artwork endpoint proves the hosted Seussical bytes are readable. The local seed database does not mirror that uploaded artwork, so the hosted read-only check was needed to establish the actual cause rather than infer a missing upload from local data.

## Fix

Production management now requests `/admin/commerce?tab=poster&id=…`. This route retains normal authentication, Commerce organization/access gates, inventory permission, and the same production visibility boundary as the management list. It resolves the latest active stored poster version, accepts only the existing JPEG/PNG/WebP image types, and streams through `StorageService` with `private, no-store` caching. It does not publish a production or expose a storage path.

The existing `/production-poster` endpoint and its public publication checks are unchanged. Unpublished artwork remains unavailable to unauthenticated visitors. A storefront tester cannot use the administrative route; explicitly authorized Commerce inventory staff can use it without receiving unrelated production-administration privileges. Missing files, missing metadata and decoding/network failures have a text fallback. No upload repair or publication is required to display Seussical's existing poster after this code is installed.

## Distinct workspaces

- **Overview:** production-grouped sales reporting, aggregate tickets/capacity/occupancy/net revenue, native accessible showtime progress bars, held/allocated/sellable inventory, backend-derived sold-out and closed states, upcoming performances, filtering and collapsed past sales. No Add Performance, pricing, publication or other production setup controls are rendered. Unscheduled productions are excluded; the empty view explains when sales reporting becomes available.
- **Productions & Performances:** artwork, title, dates, venue, announcement status and compact showtime management rows. Add Performance, production pricing, artwork/publication and existing management drawers remain available according to permissions. No revenue/occupancy reporting block is duplicated. No-show productions have compact management cards, an explicit scheduling empty state and no `0/0` capacity. Secondary controls on these cards sit under More.

Both use the existing financial/inventory projection. Order calculations, pricing inheritance, refund accounting, reservation behavior and public/payment gates are unchanged. No migration or deployment was performed.

## Files changed

- `src/CommerceProductionExperience.php`: separate `overview()` and `productions()` renderers, compact cards, private poster URLs, sales bars and empty states; removed the shared `hub()` rendering path.
- `src/CommerceProductionService.php`: guarded poster metadata retrieval, reusing the management production scope. Existing totals and readiness calculations are unchanged.
- `src/CommerceExperience.php`: distinct route dispatch and private poster streaming; cache versions updated.
- `assets/js/commerce-productions.js`: missing/failed image fallback, including cached failures.
- `assets/css/commerce.css`: separate reporting/management layouts, accessible progress styling, compact empty cards and poster frames.
- New `tests/commerce_workspace_http.php`, `tests/commerce_workspaces_browser.mjs` and guarded `tests/fixtures/commerce-workspace-browser.php`; existing production-browser selectors updated for compact management rows.
- `.github/workflows/rc1-acceptance.yml`: added HTTP/browser coverage and explicit private temporary artwork storage with cleanup.
- `DESIGN.md` and this report/evidence.

## Verification

All mutating tests used synthetic fixtures in the existing disposable loopback database. The hosted site was inspected read-only; no private hosted artwork is included in Git evidence.

- **33 existing Commerce PHP/MySQL/HTTP/concurrency suites passed**, including pricing inheritance, financial snapshots/refunds, permissions and competing purchases.
- **18 new HTTP assertions passed**: published/unpublished poster bytes, authentication, role grants, private caching, unchanged public publication protection, missing physical files, distinct workspace content and no-show/empty states.
- **43 new browser assertions passed** at 1440/768/390/320px: no document overflow, retained navigation, production/showtime bars, no setup controls on Overview, no reporting duplication in management, real image decoding for unpublished private artwork, missing-file fallback visibility, no `0/0` metrics and working Add Performance drawer navigation. No JavaScript exceptions.
- Existing browser suites passed: **34 production**, **59 staff**, and **34 storefront** assertions, including actual pricing/scheduling submissions and ticket purchase/admission flows.
- All **10 pilot safety suites**, geometry checks, 12 Apache camera-policy cases, changed PHP/JS syntax and whitespace checks passed.

[Regression results](evidence/commerce-workspaces/regressions.json), [artwork HTTP assertions](evidence/commerce-workspaces/artwork-http.txt), [workspace browser assertions](evidence/commerce-workspaces/workspace-browser.json), [safety](evidence/commerce-workspaces/safety.json).

Screenshots show synthetic test data, including an intentionally missing artwork file and a minimal synthetic image used to test decoding. They do not reproduce unpublished Seussical artwork:

- [Overview desktop](evidence/commerce-workspaces/overview-1440.png) / [mobile](evidence/commerce-workspaces/overview-390.png)
- [Productions desktop](evidence/commerce-workspaces/performances-1440.png) / [mobile](evidence/commerce-workspaces/performances-390.png)

No new migration, deployment, public-sales activation or live-payment activation. Hosted visual verification of the new UI awaits an authorized deployment; physical-device and screen-reader testing were not performed.
