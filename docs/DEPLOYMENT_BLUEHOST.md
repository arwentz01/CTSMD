# Bluehost deployment assumptions

## Hosting profile

Build 001 assumes Bluehost shared hosting with Apache, PHP 8.1+, `mod_rewrite`, and MySQL/MariaDB. It does not require Composer, Node.js, a persistent process, a WebSocket server, or shell access at runtime.

## Recommended document root

Point the domain or subdomain document root directly at the repository's `public/` directory. This keeps `.env`, application source, logs, and migrations outside the web root.

If the hosting plan cannot target `public/`, copy only the contents of `public/` into `public_html` and adjust the `BASE_PATH` in `index.php` to the private application directory. Do not place `.env`, `config/`, `database/`, `docs/`, `src/`, or `storage/` under a publicly browsable directory.

## Deployment outline

1. Select a supported PHP 8.x version and enable required extensions as they are introduced (`pdo_mysql`, `mbstring`, `json`, `openssl`).
2. Upload the project over SFTP or through a controlled deployment workflow.
3. Create a production `.env` from `.env.example`; use a unique database account with access only to this database.
4. Import versioned migrations in order after reviewing them and taking a backup.
5. Ensure `storage/logs/` is writable by PHP but not web-accessible.
6. Force HTTPS at the Bluehost/domain layer and configure production session cookies as Secure.
7. Confirm `/health` returns JSON. This endpoint deliberately does not disclose configuration or credentials.
8. When notifications arrive, configure a Bluehost cron job to run the bounded notification processor every minute or at the shortest supported interval.

## Production assumptions to verify before launch

- Exact PHP and MariaDB/MySQL versions
- Ability to change the subdomain document root
- Availability of cron jobs and their minimum interval
- Outbound email limits and authenticated SMTP options
- Backup retention and restore procedure
- Log storage limits
- Whether Apache security headers in `.htaccess` are permitted

Health checks should eventually distinguish application liveness from protected operational diagnostics. Public health output must remain minimal.

