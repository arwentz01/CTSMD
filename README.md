# CTSMD Connect

CTSMD Connect is the private, invitation-only community platform for the Children’s Theatre of Southern Maryland. Its guiding product rule is simple: student safety and guardian visibility are enforced by the server, never left to client behavior.

This repository currently contains **Build 015**, a board-demo-ready MVP with persona previews: a framework-free PHP front controller, small router, responsive branded pages, a database-aware health endpoint, first-owner setup, login/logout, invitation acceptance, admin-created invitations, live admin people counts, guardian-student linking, server-created safeguarded conversations, participant-only message threads, admin-created channels, channel posts, channel posting permissions, session-backed JSON endpoints, content reports, moderation review, notification outbox records, a member dashboard, app-style mobile demo, parent/student/instructor web and mobile persona previews, architecture notes, and the first MySQL schema.

## Requirements

- PHP 8.1 or newer (PHP 8.3 recommended)
- MySQL 8+ or MariaDB 10.6+ for future database-backed builds
- No Composer or Node.js dependencies are required for Build 015

## Run locally

1. Copy `.env.example` to `.env` and adjust values if needed.
2. From the repository root, run:

   ```bash
   php -S localhost:8080 -t public public/index.php
   ```

3. Visit `http://localhost:8080`.

Available routes:

- `/` — product landing page
- `/admin` — admin foundation preview
- `/health` — JSON application health response

The schema is a draft for the next builds; the placeholder pages do not require a database connection.

## Verify the foundation

Run the local integrity checks before extending the application:

```bash
php tools/test.php
```

The script lints core PHP files and verifies that the foundation schema still contains the required safeguarding tables and constraints.

## Apply the local schema

After creating a local database and setting `.env`, run:

```bash
php tools/migrate.php
```

The MVP schema is intentionally database-backed before messaging work begins, because guardian-visible conversation rules need to be enforced by server transactions and persisted audit records.

## Seed demo data

For a board demo or staging smoke test, run:

```bash
php tools/demo_seed.php
```

It creates a deterministic demo owner, parent, student, instructor, channels, posts, a safeguarded conversation, a moderation report, and notification outbox row.

## Project map

```text
config/          Application configuration
database/        Versioned SQL migrations
docs/            Product, architecture, and deployment decisions
public/          Web root and static assets
src/             Application and HTTP code
storage/logs/    Runtime logs (kept out of source control)
tools/           Local verification and maintenance scripts
```

See [docs/PRODUCT_CHARTER.md](docs/PRODUCT_CHARTER.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), and [docs/DEPLOYMENT_BLUEHOST.md](docs/DEPLOYMENT_BLUEHOST.md) before extending the platform.
