# Homepage identity

The public `/` homepage uses an editorial playbill composition. Its layout, typography and navigation labels belong to the platform; organization identity comes from `OrganizationBranding`, using the application's existing environment loading. No database changes or tenant isolation are introduced. The authenticated `/app` dashboard and shared application shell are unchanged; the initial mistaken `/app` redesign was reverted.

Configure these values in the deployment environment or existing `.env` (examples are in `.env.example`):

| Setting | Default / behavior |
|---|---|
| `ORGANIZATION_NAME` | CTSMD; the organization name appears in the masthead and page title |
| `ORGANIZATION_LOGO` | Optional image URL; absolute HTTP(S) or root-relative, including the installation path if needed |
| `ORGANIZATION_PRIMARY_COLOR` | `#8b233b`; six-digit hex; readable black/white control text is derived automatically |
| `ORGANIZATION_ACCENT_COLOR` | `#b58a33`; six-digit hex; restrained visual accent |
| `ORGANIZATION_TAGLINE` | Optional text; omitted when empty |
| `ORGANIZATION_WELCOME` | Optional introductory copy; otherwise uses platform theatre copy |
| `ORGANIZATION_URL` | Optional organization website link; omitted when empty |

Production imagery is independent of branding. The homepage lists only explicitly published production announcements and the existing public registration opportunities. Playbills must be publicly enabled and published/archived, matching their public reader/asset policy. The hero uses only approved public Playbill cover artwork tied to a currently announced upcoming/current production, delivered by `/playbill/asset`; it never requests member-only `/production` artwork or private file paths. Images retain their natural proportions, including portrait covers and wide artwork. If no public cover is available, the typographic composition is used. A private poster upload alone does not authorize public homepage display. Multiple productions, upcoming-only, no-artwork and no-production states use the same layout without invented activity.

Future integration point: a real organization context can supply the same small branding value array in place of `fromEnvironment()` at the public homepage call site. Organization-scoped data and authorization must come from that future backend; configurable branding alone provides neither. No theme editor, tenant authentication, layout builder or organization-specific selectors are included.

Verification is code-only. After deployment, review the headline/action hierarchy, poster versus banner proportions, the type-only fallback, phone wrapping, and your configured logo/colors. The deployed public root will change after this correction is deployed; `/app` keeps its existing dashboard.
