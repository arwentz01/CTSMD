# CTSMD Connect

CTSMD Connect is the visual-first community and operations platform prototype for the Children’s Theatre of Southern Maryland.

**Build 001 principle:** Experience defines the product. Background logic exists to support the approved experience.

Build 001 intentionally uses mocked data and server-rendered PHP. It does **not** implement real authentication, messaging delivery, database persistence, production authorization, push notifications, or compliance verification yet.

## Local development

Target environment: MAMP-compatible PHP 8.x + Apache.

1. Clone this repository into your MAMP document root, for example `/Applications/MAMP/htdocs/CTSMD`.
2. Start Apache and MySQL in MAMP.
3. Create a MySQL database named `ctsmd`.
4. Local development defaults are documented in `.env.example`:
   - Database: `ctsmd`
   - Username: `andrew`
   - Password: `password`
5. Build 001 does not query the database yet, so the prototype can be viewed before schema work begins.
6. Open the project using your MAMP host/port, typically `http://localhost:8888/CTSMD/` or your configured virtual host.

If you use a local virtual host that maps directly to the project directory, `APP_BASE_PATH` can be blank. If you run the repository from a subdirectory, use `/CTSMD` as the base path when environment loading is added in a later build.

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
├── index.php                 # Front controller + Build 001 routes/views
├── src/
│   └── mock-data.php         # Realistic theatre prototype data
├── public/
│   └── assets/
│       ├── css/app.css       # CTSMD visual system + responsive layouts
│       └── js/app.js         # Minimal prototype interactions
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

## What comes next

Build 002 should stay primarily visual. Recommended work:

- Refine the CTSMD logo/brand treatment with approved organization assets.
- Add stronger role-specific dashboard variations.
- Polish mobile navigation and admin mobile fallbacks.
- Design empty, loading, error, and no-permission states.
- Add announcement composer and channel-management mockups.
- Expand volunteer eligibility and staff-review workflows.
- Add parent/student schedule-detail and absence-reporting flows.
- Improve accessibility semantics, keyboard behavior, focus states, and contrast review.
- Lock the visual component system before introducing meaningful persistence.

See `docs/BUILD_001.md` for the Build 001 checkpoint and `docs/VISUAL_FIRST_ARCHITECTURE.md` for the intended technical path.
