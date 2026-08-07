# CTSMD Connect

CTSMD Connect is the visual-first community and operations platform prototype for the Children’s Theatre of Southern Maryland.

**Build principle:** Experience defines the product. Background logic exists to support the approved experience.

The prototype is server-rendered PHP with a lightweight MySQL data layer. It does **not** yet implement production authentication, messaging delivery, production authorization, push notifications, or full compliance verification.

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
5. Import `database/seeds/001_demo.sql` into `ctsmd`.
6. Local development defaults are documented in `.env.example`:
   - Database: `ctsmd`
   - Username: `andrew`
   - Password: `password`
7. Open the project using your MAMP host/port, typically `http://localhost:8888/CTSMD/` or your configured virtual host.

The demo seed is intended to be rerunnable during local development. It clears and rebuilds the seeded prototype records so the visual environment is predictable.

If you use a local virtual host that maps directly to the project directory, `APP_BASE_PATH` can be blank. If you run the repository from a subdirectory, use `/CTSMD` as the base path.

## Build 001 routes

| Route | Prototype screen |
| --- | --- |
| `/` | Public CTSMD Connect landing page |
| `/app` | Main parent/member “what do I need today?” dashboard |
| `/parent` | Parent/member dashboard variation |
| `/staff` | Desktop-optimized staff operations dashboard |
| `/channels` | Channel list + production discussion feed |
| `/messages` | Direct/safeguarded messaging concept |
| `/volunteers` | Volunteer operations dashboard |
| `/volunteer-shifts` | Member-facing shift signup + eligibility states |
| `/volunteers/profile` | Volunteer profile, compliance, training, eligibility |
| `/admin/shifts` | Desktop shift creation/management preview |
| `/schedule` | Production schedule/events preview |
| `/playbills` | Digital Playbill archive/detail preview |
| `/forms` | Forms and acknowledgments preview |
| `/admin` | Administration/module navigation preview |
| `/wordpress` | WordPress integration concept |
| `/health` | JSON health check |

## Current structure

```text
CTSMD/
├── index.php
├── src/
│   ├── Database.php
│   ├── PrototypeDataRepository.php
│   └── mock-data.php          # Compatibility loader; contains no fixture records
├── database/
│   ├── schema.sql
│   └── seeds/
│       └── 001_demo.sql
├── public/
│   └── assets/
│       ├── css/app.css
│       └── js/app.js
├── docs/
│   ├── PRODUCT_CHARTER.md
│   ├── VISUAL_FIRST_ARCHITECTURE.md
│   ├── WORDPRESS_INTEGRATION.md
│   └── BUILD_001.md
├── .env.example
└── .htaccess
```

## Product boundaries

CTSMD Connect is a private, organization-managed platform, not a public social network. The future production architecture must make guardian visibility and student safety structural requirements rather than optional UI settings.

The most important messaging rule is non-negotiable: any adult/student conversation must automatically include the student’s approved guardian(s), and the server must determine the final participant list.

## Responsive strategy

Member-facing experiences are mobile-first: parents, students, volunteers, channels, messages, schedule, forms, Playbills, and quick actions.

Staff/admin experiences are desktop-optimized: people/family management, compliance review, volunteer operations, schedule building, reporting, safeguarded-message review, imports/exports, and audit workflows. They must still have usable tablet/mobile fallback layouts.

## Development rule going forward

Visual-first does not mean disposable data architecture.

When a new screen needs realistic content:

1. add or extend the smallest appropriate schema needed for that approved experience;
2. add representative records to a versioned seed file;
3. load those records through a repository/read model;
4. render the screen from that data;
5. do not paste a fake record directly into the view “just for now.”

This keeps the prototype visually fast without creating a cleanup hunt later.

## What comes next

Build 002 should stay primarily visual while continuing to move remaining prototype scenarios onto seeded read models. Recommended work:

- Refine the CTSMD logo/brand treatment with approved organization assets.
- Add stronger role-specific dashboard variations.
- Polish mobile navigation and admin mobile fallbacks.
- Design empty, loading, error, and no-permission states.
- Add announcement composer and channel-management mockups.
- Expand volunteer eligibility and staff-review workflows.
- Add parent/student schedule-detail and absence-reporting flows.
- Improve accessibility semantics, keyboard behavior, focus states, and contrast review.
- Lock the visual component system before deeper persistence and write workflows.

See `docs/BUILD_001.md` for the Build 001 checkpoint and `docs/VISUAL_FIRST_ARCHITECTURE.md` for the intended technical path.
