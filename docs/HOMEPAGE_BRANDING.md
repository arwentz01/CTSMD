# Homepage identity

The authenticated `/app` landing page uses an editorial playbill composition. Its layout, typography and navigation labels belong to the platform; organization identity comes from `OrganizationBranding`, using the application's existing environment loading. No database changes or tenant isolation are introduced. The shared application shell retains its existing identity and is outside this homepage pass.

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

Production imagery is independent of branding: only the current viewer's existing authorized production list is queried, posters take preference, banners retain their natural proportions, and image requests use the existing protected delivery endpoint. Multiple accessible productions can supply a lead image and supporting production links. Upcoming productions supply artwork only when existing access rules permit it; otherwise the same typographic fallback covers upcoming-only, no-artwork and no-production accounts.

Future integration point: a real organization context can supply the same small branding value array in place of `fromEnvironment()` at the homepage call site. Organization-scoped data and authorization must come from that future backend; configurable branding alone provides neither. No theme editor, tenant authentication, layout builder or organization-specific selectors are included.

Verification is code-only. After deployment, review the headline/action hierarchy, poster versus banner proportions, the type-only fallback, phone wrapping, and your configured logo/colors. Existing screenshots of the previous dashboard do not represent this landing redesign.
