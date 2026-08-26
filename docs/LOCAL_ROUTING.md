# Local Routing Notes

## MAMP `/production` Directory Collision

The repository contains a physical `production/` directory for the schedule ICS importer at `/production/schedule/import/`.

On Bluehost, `mod_rewrite` routes `/production` and `/production/` through `front.php` before Apache treats `production/` as a normal directory. On the inspected MAMP install, `rewrite_module` is commented out in Apache config, so the root `.htaccess` rewrite rule is not available. `FallbackResource` handles missing paths, but it does not intercept an existing physical directory. Apache therefore resolves `/production` as the physical directory.

To keep local routing aligned with production, `production/index.php` is a minimal bridge for only the directory index case. It preserves the original `REQUEST_URI`, normalizes `SCRIPT_NAME`/`PHP_SELF`/`SCRIPT_FILENAME` to the project `front.php`, then delegates to the canonical front controller. This prevents `APP_BASE_PATH` from becoming `/ctsmd/production`.

Do not remove `production/schedule/import/index.php`; that remains the importer entrypoint.
