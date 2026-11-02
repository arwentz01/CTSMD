# CTSMD Connect UI Optimization Audit

Phase 1 inventory for a structured UI-system optimization. This is an audit, not a redesign plan or a sweeping rewrite.

## Scope Reviewed

- `assets/css/` feature and mobile stylesheets.
- Global browser-facing entrypoint: `assets/css/app.css` and `public/assets/css/app.css`.
- Shared shell/navigation: `src/AppNavigation.php` and `assets/css/unified-navigation.css`.
- Representative member screens: `src/HomeExperience.php`, `src/CommunityExperience.php`.
- Representative staff screens: `src/AccountManagementExperience.php`, `src/CommunityManagementExperience.php`.
- Common patterns visible across CSS: buttons, forms, cards, grouped rows/tables, badges, alerts, headers, tabs/subnavigation, mobile bottom navigation, mobile override layers.

## Existing Strengths

- The product already has a recognizable CTSMD identity: burgundy/red, warm gold, charcoal, warm neutral surfaces, and selective Georgia serif headings.
- The major navigation model is clear and should be preserved: sidebar for destinations, section pages/hubs for tools, mobile bottom navigation for major member destinations.
- `public/assets/css/app.css` already defines early global variables such as `--red`, `--gold`, `--ink`, `--muted`, `--line`, `--shadow`, `--radius`, and `--sidebar`.
- `assets/css/unified-navigation.css` establishes the current app shell, sticky header, drawer, persistent mobile tabs, safe-area padding, and touch-friendly mobile baseline.
- Some admin work is already moving in the desired direction. `CommunityManagementExperience` and `community-admin.css` use compact grouped table rows for `/admin/channels`.
- Member-facing screens generally respect mobile-first behavior and use clear destinations such as Home, Calendar, Channels, Messages, and More.
- Server-rendered PHP pages keep functionality explicit and avoid a heavy frontend stack.

## Design Debt

- There are many page-specific CSS files in `assets/css/`, including large accumulated layers such as `mobile-native-experience.css`, `mobile-theatre-polish.css`, `mobile-forms-notifications.css`, `mobile-messaging-stream.css`, `community-chat-viewport.css`, `communication-implementation.css`, `product-polish.css`, and `app-shell-responsive.css`.
- File names containing `implementation`, `polish`, `reset`, and `hotfix` indicate accumulated UI corrections that should be audited before consolidation.
- Raw color values are repeated heavily across feature CSS. Common examples include red variants such as `#a6192e`, dark charcoal values such as `#171419`, neutral borders such as `#e9e3e6`, and many one-off muted text colors.
- Button implementations are duplicated. `.button` exists globally, but many files restyle buttons directly through page selectors such as `.acct-panel button`, `.cal-event footer button`, `.att-report button`, `.registration-form-card button`, and similar.
- Form controls are repeatedly styled per page: inputs, selects, and textareas commonly redefine border, radius, padding, focus outlines, background, and font inheritance.
- Card and panel styles are repeated with small variations: white background, subtle border, radius between 14px and 24px, padding between 18px and 34px, sometimes shadows.
- Radius values are inconsistent. Common values include 8px, 9px, 10px, 11px, 12px, 13px, 14px, 16px, 17px, 18px, 20px, 22px, 24px, 28px, 999px, and many circular avatars.
- Spacing is mostly coherent by instinct but not systematic. Repeated values include 7px, 9px, 10px, 11px, 13px, 14px, 15px, 18px, 20px, 22px, 26px, 28px, 30px, 34px, 38px, and larger hero padding.
- Typography has many one-off sizes, including very small 8px and 9px microcopy. Some of this is intentional for labels, but essential text should avoid dropping below roughly 11px.
- Status badges/pills are inconsistent. Global `.pill` exists, while feature-specific classes such as `.ca-status`, `.acct-list strong`, `.att-chips span`, `.approval-status`, and `.fm-roster article>span` duplicate state styling.
- Alerts/flash messages are page-specific (`.acct-flash`, `.att-flash`, `.cal-flash`, `.fm-flash`, `.emailops-flash`, etc.) with repeated success/error styling.
- Cards are sometimes used as the default layout container even for operational datasets that are better as compact rows or grouped tables.
- Mobile-specific CSS has many `!important` rules, especially in chat and messaging viewport files. This suggests specificity collisions and late-stage overrides.

## Shared-Pattern Candidates

- Design tokens:
  - Spacing scale using 4px/8px increments.
  - Restrained radius scale.
  - Semantic typography tokens for display, h1, h2, h3, body, body-small, label, and micro.
  - Later phases should add semantic color and shadow tokens while mapping existing `--red`, `--gold`, `--ink`, `--muted`, and `--line`.
- Buttons:
  - Primary, secondary, tertiary/text, destructive, icon, compact, disabled, and loading states.
  - Should replace per-page direct `button` styling over time.
- Forms:
  - Shared labels, hints, input/select/textarea controls, field groups, fieldsets, checkbox/radio rows, error states, and form actions.
- Data rows/tables:
  - The `/admin/channels` grouped table is the strongest existing model for compact operational datasets.
  - Candidate future primitive: grouped data table/list with mobile label blocks.
- Surfaces:
  - Page shell, panel/card, raised surface, muted surface, dark feature surface, empty state, and alert/banner.
- Status:
  - Neutral, success, warning, danger, info, inactive.
- Page structure:
  - Page wrapper, page heading, section heading, toolbar, earned hero/header, empty state, flash/alert, tabs/subnavigation.

## High-Risk Refactors

- Mobile navigation and drawer behavior in `assets/css/unified-navigation.css`, `assets/js/unified-navigation.js`, and `assets/js/mobile-engagement.js`.
- Chat/message viewport overrides in `community-chat-viewport.css`, `mobile-messaging-reset.css`, `mobile-messaging-stream.css`, `communication-implementation.css`, and related JS.
- Safeguarding, permissions, volunteer eligibility, production context, and forms workflows. UI changes must not alter server-side authorization or business rules.
- Replacing page-specific form styles globally without testing dense admin forms and member mobile forms.
- Removing `implementation`, `polish`, `reset`, or `hotfix` files before confirming every rule is obsolete or migrated.
- Converting operational rows into cards or member cards into dense tables without respecting the product direction.

## Files Involved

- Global and shell:
  - `assets/css/app.css`
  - `public/assets/css/app.css`
  - `assets/css/unified-navigation.css`
  - `src/AppNavigation.php`
- Representative member UI:
  - `src/HomeExperience.php`
  - `assets/css/home-experience.css`
  - `src/CommunityExperience.php`
  - `assets/css/community-chat.css`
  - `assets/css/community-chat-viewport.css`
- Representative staff UI:
  - `src/AccountManagementExperience.php`
  - `assets/css/account-access.css`
  - `src/CommunityManagementExperience.php`
  - `assets/css/community-admin.css`
- Mobile debt layers:
  - `assets/css/mobile-native-experience.css`
  - `assets/css/mobile-native-hotfixes.css`
  - `assets/css/mobile-theatre-polish.css`
  - `assets/css/mobile-forms-notifications.css`
  - `assets/css/mobile-messaging-reset.css`
  - `assets/css/mobile-messaging-stream.css`
  - `assets/css/mobile-calendar-disclosure.css`
  - `assets/css/mobile-community-curtain.css`

## Phase 3A Components Available

The first reusable component primitives now live in `assets/css/design-system.css`.

- Buttons: primary `.button`/`.btn`, secondary, tertiary/text, destructive, compact, disabled, hover, and focus-visible states.
- Operational tables/lists: `.op-table`, grouped headers, desktop columns, responsive stacked rows, mobile labels, row actions, table statuses, and empty rows.
- Forms: `.form-stack`, `.form-group`, `.form-field`, `.form-input`, `.form-select`, `.form-textarea`, `.form-check`, `.form-hint`, and `.form-actions`.

Reference migrations completed in Phase 3A:

- `/admin/channels` now uses the shared operational table/list system while retaining channel-specific column sizing and grouping context.
- `/admin/moderation/terms` now uses the shared operational table/list system while retaining moderation-specific severity colors and category grouping.
- `/account` now uses shared form controls and buttons for profile and password forms.
- `/admin/channels/edit` now uses shared form controls, grouped fields, checkbox rows, and the shared primary button.
- `/admin/moderation/terms/edit` now uses shared form controls, grouped fields, textareas, and buttons, including the rule tester control.
- Representative button migrations include channel create/save/settings/archive/restore, moderation add/save/test/reject/approve/edit/toggle, and account save/change password.

## Phase 3A Debt Eliminated

- Removed local table shell styling from `assets/css/community-admin.css` and `assets/css/moderation.css`; those files now mainly provide page layout, grid templates, and domain-specific content styling.
- Removed local account form/button control styling from `assets/css/my-account.css`.
- Removed local moderation form control styling from `assets/css/moderation.css`.
- Removed local channel form control styling from `assets/css/community-admin.css`.
- Preserved the legacy `.button` class so older screens continue to render against the shared primary button primitive.

## Remaining Duplicate Patterns

- Several older app wrappers had systemic centered-container behavior: `margin: 0 auto` plus fixed `max-width` inside the main stage. On wide desktop this created a large gutter between the fixed sidebar and actual content. The shared contract is now left-aligned desktop content with tokenized gutters and opt-in width caps for deliberately narrow or wide-capped experiences. A desktop-only admin/operations normalization now covers the known admin wrappers while preserving intentionally narrow member/public flows.
- Many page-specific button rules remain outside the migrated screens, especially direct selectors like page-card `button` rules in admin/member feature CSS.
- Form controls remain duplicated across registration, attendance, account/admin access, calendar, notification, and messaging stylesheets.
- Status badges/pills are still intentionally out of scope for this pass except table-local `.op-table-status`.
- Alerts/flash messages remain page-specific.
- Cards, panels, page headers, tab/subnav patterns, and mobile-specific override files still need later phased treatment.
- Mobile navigation, drawer behavior, and chat/messaging viewport layers were not changed in Phase 3A.

## Next Recommended Migrations

1. Migrate the next compact admin dataset to `.op-table` after validating `/admin/channels` and `/admin/moderation/terms` in browser.
2. Migrate high-traffic member forms, likely notification preferences and registration/profile-adjacent workflows, to `.form-*`.
3. Move remaining direct page-level button styling to `.btn` variants where markup changes are low risk.
4. Start a separate Phase 3B pass for status badges/pills, alerts, and empty states.
5. Keep mobile CSS consolidation for a later phase after component primitives are proven across more screens.

## Recommended Migration Sequence

1. Add canonical design tokens without mechanically replacing all existing values.
2. Map existing global variables to semantic tokens so old and new CSS can coexist.
3. Create shared component primitives in a later phase, starting with buttons, forms, status badges, alerts, and data rows.
4. Normalize one screen family at a time, beginning with global shell/navigation, then member Home, then compact admin datasets.
5. Use `/admin/channels` as the first operational table/list model before applying the pattern to other admin screens.
6. Audit mobile-specific files after shared primitives are stable; remove overrides only after confirming behavior.
7. Document the design system after primitives and at least one screen family have proven the approach.

## Phase 2 First Three Highest-Value Items

For this scoped pass, the first three Phase 2 items are:

1. Spacing tokens.
2. Radius tokens.
3. Typography tokens and semantic type helper classes.

Colors, shadows, shared components, and screen normalization are intentionally left for later phases.

## Raw Value Debt Inventory

Measured during the Phase 2 completion pass across `assets/css/*.css` and `public/assets/css/app.css`. These are approximate static counts intended to guide migration, not a mandate for mechanical replacement.

- Raw hex colors: about 2,660 occurrences. The most common are `#fff`, `#a6192e`, `#211a1d`, `#76696e`, `#171315`, `#171419`, `#ded5d8`, and `#ddd`.
- Border radius declarations: about 937 occurrences. Common values include `12px`, `18px`, `999px`, `10px`, `14px`, `50%`, `16px`, `11px`, `9px`, `20px`, `24px`, `22px`, and `13px`.
- Padding declarations: about 1,326 occurrences. Margin declarations: about 720 occurrences. These include many near-scale but arbitrary values such as 9px, 10px, 11px, 13px, 15px, 18px, 22px, 26px, 28px, 30px, 34px, and 38px.
- Box shadow declarations: about 158 occurrences. Many are `none` or `none!important`, with a smaller set of custom shadows for drawers, mobile cards, and feature panels.
- Font-size declarations: about 1,331 occurrences. Common values are 11px, 10px, 9px, 12px, 13px, 18px, 8px, 14px, 20px, 34px, and 30px.
- Tiny font-size patterns: about 254 likely occurrences at or below roughly 10px. Some are decorative labels, but essential text needs review before normalization.
- Duplicated control-height-like values: about 84 occurrences in the 36px to 58px range, including 36px, 38px, 40px, 42px, 44px, 46px, and 48px.
- `!important` rules: about 2,355 occurrences, concentrated in mobile and viewport correction layers. This is a high-signal area for later consolidation, especially after shared components exist.

Do not broad-replace these values during Phase 2. The design tokens now provide the migration target; Phase 3 and Phase 4 should migrate one component or screen family at a time.

## Phase 3C Operational Density Log

### Navigation Stabilization

- Added `AppNavigation::productionSubnav()` as the shared production section navigation source.
- Migrated production workspace, schedule, attendance, casting, people, groups, readiness, production day, resources, files, schedule notices, schedule import/create, Playbill, and Playbill media routes to the shared production subnav.
- Retained functional destinations while preventing the production tabs from changing shape on each click.

### Batch 1: People / Account Access

Converted card-heavy record collections:

- `/people`: staff directory now uses the shared operational table/list pattern with columns for person, role, student links, guardian links, and action.
- `/admin/accounts`: account inventory now uses the shared operational table/list pattern with columns for account, roles, membership, sign-in status, last login, and action.

Retained cards/surfaces and why:

- `/people/view`: detail-level family relationship panels remain grouped surfaces because the page is about one person and relationship editing, not a comparable record list.
- `/admin/accounts/view`: membership, role, lifecycle, family, and volunteer readiness panels remain grouped surfaces because they are distinct control areas for one account.

Responsive risks:

- Role summaries on `/admin/accounts` can be long; they are truncated on desktop and allowed to wrap on mobile.
- People/account table rows rely on the shared mobile stacked-row behavior and should be visually reviewed with realistic names and role strings.

### Batch 2: Production People / Groups

Converted card-heavy record collections:

- `/production/people`: production membership roster now uses grouped operational rows for Students/Cast, Guardians, Production Staff, and disabled-account cleanup.
- `/production/groups`: production group inventory now uses a compact operational table/list with group, type, member count, status, and actions.

Retained cards/surfaces and why:

- `/production/people`: production hero, add-person panel, and guardian coverage panel remain surfaces because they are context/control areas, not comparable row collections.
- `/production/groups/view`: group membership editor remains a structured checkbox editor. It is an input workflow rather than a passive record list, so a table conversion would make selection harder.
- `/production/groups`: the create-group panel and guardian inheritance note remain surfaces because they are focused controls/context.

Responsive risks:

- Production membership rows include names, production role, and account role; long role labels should be reviewed on tablet widths.
- Production group descriptions truncate in the desktop table. Full group detail remains available from the Manage link.
