# Fresh Browser Startup Audit

Date: 2026-10-08

## Scope

Audit the reported fresh-browser startup behavior: first load shows `ERR_TIMED_OUT`, then the browser quickly lands on the CTSMD homepage. The audit used the current local repository state and focused on clean requests with no cookies, no local storage, no service-worker state, and no existing session.

## Findings

### F1 - Local Apache is not currently serving this repository for `/CTSMD`

- Evidence: `curl -i http://localhost/CTSMD/app` returns a PHP fatal error from `/Applications/MAMP/htdocs/Operations/app/Core/Container.php`, not from this repository.
- Impact: Local browser testing through `http://localhost/CTSMD/...` is not a trustworthy reproduction of CTSMD Connect until the active Apache virtual host/document root is corrected.
- Recommended fix: Point the local Apache vhost/document root to `/Applications/MAMP/htdocs/CTSMD`, or use a dedicated host name for CTSMD. Re-test fresh-browser startup after Apache is serving this repo.
- Classification: Environment blocker.

### F2 - `.htaccess` fallback path used the wrong folder casing

- Evidence: `.htaccess` used `FallbackResource /ctsmd/front.php`; the repository path and `.env.example` use `/CTSMD`.
- Impact: When `mod_rewrite` is unavailable or disabled, Apache may route fallback requests to the wrong URL path. On a case-sensitive host this can produce 404s, unexpected fallback behavior, or a bounce into whatever handles the lowercase path.
- Fix applied: Updated `.htaccess` to `FallbackResource /CTSMD/front.php`.
- Classification: Fixed low-risk routing defect.

### F3 - Service worker is unlikely to be the direct cause of a first fresh load timeout

- Evidence: `service-worker.js` registers `install`, `activate`, `push`, `notificationclick`, and `pushsubscriptionchange`; it has no `fetch` event handler. A service worker without a fetch handler cannot intercept normal navigation requests.
- Impact: A previously installed service worker could affect notification deep links, but it should not stall a first navigation to the app.
- Recommended fix: No code change for startup. Keep the worker notification-only unless offline caching is intentionally added later.
- Classification: Ruled out for first-load navigation.

### F4 - Fresh unauthenticated protected routes redirect through the front controller

- Evidence: `front.php` redirects unauthenticated protected routes to `/login?return_to=...` with HTTP 303 after `RuntimeSchemaGuard` passes.
- Impact: The expected fresh-browser behavior for `/app` and `/production` is login, not the public homepage. The homepage is intentionally served only for `/`.
- Recommended fix: After Apache is correctly pointed at this repo, capture the exact address bar URL that times out and the final URL it lands on. If the final URL is `/`, the redirect source is likely outside this app, because this app's unauthenticated protected-route redirect target is `/login`.
- Classification: Needs environment-level reproduction.

## Clean Request Evidence

Local Apache currently serves the wrong app for `http://localhost/CTSMD/...`, so these results are separated from repository behavior:

- `http://localhost/CTSMD/app`: HTTP 200 containing a fatal error from `/Applications/MAMP/htdocs/Operations/...`.
- `http://localhost/CTSMD/production`: HTTP 200 containing the same unrelated fatal error.
- `http://localhost:8888/CTSMD/health`: connection refused; MAMP is not listening on port 8888 in this environment.

Repository-level code checks:

- `service-worker.js`: no navigation/fetch interception.
- `front.php`: public `/` renders the landing page; `/login` renders the login page; authenticated routes redirect unauthenticated users to login.
- `RuntimeSchemaGuard`: passes against the local database.

## Redirect Map

- `/` -> public CTSMD Connect landing page.
- `/login` -> login page.
- `/app` when logged out -> `303 /login?return_to=%2Fapp`.
- `/production` when logged out -> `303 /login?return_to=%2Fproduction`, assuming Apache routes the request through `front.php`.
- `/production/` when `mod_rewrite` is disabled -> physical `production/index.php` bridge delegates to `front.php`.

## Next Diagnostic Step

Before changing application redirects, fix the active Apache host mapping and repeat a clean browser or curl test against the actual CTSMD repository. The most useful capture is:

```sh
curl -i -L -b /dev/null -c /tmp/ctsmd-cookies.txt --max-time 30 http://localhost/CTSMD/app
```

Record the first status line, every `Location` header, the final URL, and whether the response body comes from this repository or another MAMP project.
