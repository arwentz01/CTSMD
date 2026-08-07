# CTSMD Connect

CTSMD Connect is the visual-first community and operations platform prototype for the Children’s Theatre of Southern Maryland.

**Build principle:** Experience defines the product. Background logic exists to support the approved experience.

The prototype is server-rendered PHP with a lightweight MySQL data layer. It does **not** yet implement production authentication, push notifications, or full compliance verification.

## Canonical product backlog

The approved product wishlist, near-term build order, architectural notes, and platform debt are tracked in [`docs/TODO.md`](docs/TODO.md). That document is the source of truth for “what comes next” rather than older build-checkpoint notes.

## Non-negotiable demo-data rule

Demo records are seeded into the local database. They are not hardcoded into controllers, views, or PHP fixture arrays.

Anything pretending to be application data belongs in database seed files, including:

- people and roles
- productions
- announcements
- schedule items
- channels and posts
- conversations and messages
- volunteer requirements and credentials
- volunteer shifts and signups
- forms and assignments
- Playbills
- operational counts/statuses that can be derived from those records

Views may contain interface copy, labels, headings, and explanatory text. They should not contain fake people, fake records, fake statuses, or fake operational metrics.

The current demo seed is `database/seeds/001_demo.sql`.

## Local development

Target environment: MAMP-compatible PHP 8.x + Apache + MySQL/MariaDB.

1. Clone this repository into your MAMP document root, for example `/Applications/MAMP/htdocs/CTSMD`.
2. Start Apache and MySQL in MAMP.
3. Create a MySQL database named `ctsmd`.
4. Import `database/schema.sql` into `ctsmd`.
5. Apply the versioned migrations under `database/migrations/` in numeric order for the features present on `main`.
6. Import `database/seeds/001_demo.sql` into `ctsmd` when a seed refresh is appropriate.
7. Local development defaults are documented in `.env.example`:
   - Database: `ctsmd`
   - Username: `andrew`
   - Password: `password`
8. Open the project using your MAMP host/port or configured virtual host.

For an existing local database, apply only newly introduced migrations. Do not rerun non-idempotent migrations blindly.

If you use a local virtual host that maps directly to the project directory, `APP_BASE_PATH` can be blank. If you run the repository from a subdirectory, use the detected project subdirectory as the base path.

## Product boundaries

CTSMD Connect is a private, organization-managed platform, not a public social network. Guardian visibility and student safety are structural requirements rather than optional UI settings.

The most important messaging rule is non-negotiable: any adult/student safeguarded conversation must automatically include the student’s approved guardian(s), and the server determines the final participant list.

Multiple productions may be active concurrently. A session-selected **working production** controls the staff/member workspace without deactivating other active productions.

General-purpose **Teams** and show-operational **Production Groups** are separate concepts. Production Groups drive targeted calls/schedules and are designed to support future attendance, forms, resources, call sheets, and reporting.

## Responsive strategy

Member-facing experiences are mobile-first: parents, students, volunteers, channels, messages, schedule, forms, Playbills, and quick actions.

Staff/admin experiences are desktop-optimized: people/family management, compliance review, volunteer operations, schedule building, reporting, safeguarded-message review, imports/exports, and audit workflows. They must still have usable tablet/mobile fallback layouts.

## Development rule going forward

Visual-first does not mean disposable data architecture.

When a new screen needs realistic content:

1. add or extend the smallest appropriate schema needed for that approved experience;
2. add representative records to a versioned seed file when seed data is actually required;
3. load records through the database/read model;
4. render the screen from that data;
5. do not paste fake domain records directly into the view “just for now.”

Historical records should normally be archived/deactivated rather than hard-deleted, and access must be enforced server-side rather than only hidden in the UI.

## Documentation

- [`docs/TODO.md`](docs/TODO.md) — canonical backlog and future build order
- [`docs/PRODUCT_CHARTER.md`](docs/PRODUCT_CHARTER.md) — product purpose and governing principles
- [`docs/VISUAL_FIRST_ARCHITECTURE.md`](docs/VISUAL_FIRST_ARCHITECTURE.md) — intended technical/design path
- [`docs/NAVIGATION_IA.md`](docs/NAVIGATION_IA.md) — information architecture
- [`docs/WORDPRESS_INTEGRATION.md`](docs/WORDPRESS_INTEGRATION.md) — public-CMS integration direction
- [`docs/BUILD_001.md`](docs/BUILD_001.md) — original visual checkpoint/history
