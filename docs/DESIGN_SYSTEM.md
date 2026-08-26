# CTSMD Connect Design System

This document covers the Phase 2 token system, Phase 3A operational primitives, and Phase 3B shared UI primitives for surfaces, statuses, alerts, empty states, headers, and toolbars. Screen-family normalization, mobile CSS consolidation, navigation redesign, broad card-to-table conversions, and accessibility QA belong to later phases.

## Philosophy

CTSMD Connect should feel compact, mature, theatrical, calm, and professional. The interface should support the real work of families, members, production teams, volunteers, and staff without becoming decorative clutter.

Big visual moments are earned.

Sidebar = destinations. Hubs = tools.

Member experiences optimize for clarity and thumb reach. Staff experiences optimize for scanability and operational efficiency.

## Spacing

The canonical spacing scale uses an 8-point-oriented rhythm, with 4px available for fine component adjustments.

| Token | Value | Use |
| --- | ---: | --- |
| `--space-1` | `4px` | Fine adjustment, tight icon/text relationships |
| `--space-2` | `8px` | Small gaps inside compact components |
| `--space-3` | `12px` | Dense rows, compact controls, small card internals |
| `--space-4` | `16px` | Standard component padding and grouped content |
| `--space-5` | `24px` | Section/card padding and layout gaps |
| `--space-6` | `32px` | Page padding and larger layout gaps |
| `--space-7` | `48px` | Major layout separation |
| `--space-8` | `64px` | Earned hero or large page separation |

Component spacing should usually use `--space-2` through `--space-5`. Top-level layouts may use `--space-5` through `--space-8`. Avoid inventing new values unless the optical fit is clearly better.

## Typography

CTSMD uses system/Inter-style sans for most operational UI and Georgia selectively for expressive theatrical moments.

| Token | Value | Use |
| --- | --- | --- |
| `--font-sans` | Inter/system stack | App UI, forms, tables, rows, controls |
| `--font-serif` | Georgia stack | Earned hero headings, theatrical display moments |
| `--type-display-size` | `clamp(40px,5vw,64px)` | Rare display moments |
| `--type-h1-size` | `clamp(28px,3vw,40px)` | Page titles |
| `--type-h2-size` | `clamp(23px,2.4vw,32px)` | Section leads |
| `--type-h3-size` | `18px` | Compact panels and cards |
| `--type-body-size` | `14px` | Default app text |
| `--type-body-small-size` | `12px` | Supporting copy |
| `--type-label-size` | `11px` | Labels and dense metadata |
| `--type-micro-size` | `11px` | Uppercase eyebrows and status labels |

Essential text should not normalize below roughly 11px. Some legacy CSS still uses 8px to 10px for decorative labels, badges, and dense mobile affordances; those should be reviewed in later phases before replacement.

Helper classes exist for early adoption: `.type-display`, `.type-h1`, `.type-h2`, `.type-h3`, `.type-body`, `.type-body-small`, `.type-label`, and `.type-micro`.

## Radius

The radius scale is restrained so CTSMD avoids oversized, childish rounded rectangles.

| Token | Value | Use |
| --- | ---: | --- |
| `--radius-sm` | `8px` | Small controls and compact data cells |
| `--radius-md` | `12px` | Buttons, inputs, compact panels |
| `--radius-lg` | `16px` | Cards and standard surfaces |
| `--radius-xl` | `24px` | Earned feature panels, mobile sheets |
| `--radius-pill` | `999px` | Pills, chips, circular badges |

Legacy `--radius` remains `18px` for migration safety.

## Surface Hierarchy

| Token | Value | Use |
| --- | --- | --- |
| `--surface-page` | warm page neutral | App background |
| `--surface-card` | white | Cards, panels, row groups |
| `--surface-raised` | warm white | Slightly warmer raised surfaces |
| `--surface-muted` | light warm neutral | Rails, grouped sections |
| `--surface-subtle` | near-white warm neutral | Subtle fills |
| `--surface-dark` | charcoal | Sidebar and dark foundations |
| `--surface-dark-raised` | raised charcoal | Dark cards and feature panels |

Use borders, contrast, and spacing before elevation.

## Surfaces And Cards

Use shared surface classes for repeated panel appearance. Feature CSS should own placement, grid behavior, and domain-specific identity.

| Class | Use |
| --- | --- |
| `.surface` | Standard padded card/panel surface. |
| `.surface-card` | Card shell without default padding. |
| `.surface-compact` | Denser panel for admin tools and small cards. |
| `.surface-muted` | Muted grouped surface. |
| `.surface-inset` | Subtle nested/inset surface. |
| `.surface-raised` | Rare, low-elevation surface. |
| `.surface-dark` | Dark feature panel or dark card. |

### When To Use A Card

Use a card when the content is a meaningful object or task group: a dashboard module, a settings panel, a summary, a queue item that needs rich review context, or a member-facing moment that benefits from containment.

Do not use cards as the default shape for every row in operational datasets. Staff lists that need scanning, comparison, sorting, status review, or repeated row actions should start from `.op-table` and its row/group primitives. Broad card-to-table conversion is Phase 3C work and should be planned route by route.

Big visual moments are earned. A hero, dark panel, or display headline should clarify a user’s current context or next action; it should not be decoration around routine administration.

## Brand Colors

The brand palette is preserved, not redesigned.

| Token | Value | Role |
| --- | --- | --- |
| `--brand-primary` | burgundy red | Primary action, active state, emphasis |
| `--brand-primary-hover` | dark burgundy | Hover/pressed primary |
| `--brand-primary-soft` | soft red surface | Danger/attention background |
| `--brand-accent` | warm gold | Theatre accent, active sidebar marker |
| `--brand-accent-soft` | soft gold surface | Warm warning/accent background |

Legacy variables are aliased for migration: `--red`, `--red-dark`, `--red-soft`, `--gold`, and `--gold-soft`.

## Text Colors

| Token | Use |
| --- | --- |
| `--text-primary` | Main readable text |
| `--text-secondary` | Subheads and secondary emphasis |
| `--text-muted` | Supporting copy and metadata |
| `--text-inverse` | Text on dark surfaces |
| `--text-danger` | Destructive/danger text |
| `--text-success` | Success text |
| `--text-warning` | Warning text |
| `--text-link` | Links and text actions |

Legacy `--ink` and `--muted` remain aliased to the semantic text tokens.

## Borders

| Token | Use |
| --- | --- |
| `--border-subtle` | Hairline dividers and row separators |
| `--border-default` | Standard card/control border |
| `--border-strong` | Higher-contrast boundaries |
| `--border-focus` | Focus-visible emphasis support |

Legacy `--line` remains aliased to `--border-default`.

## State Colors

| Token Pair | Use |
| --- | --- |
| `--state-success`, `--state-success-soft` | Approved, ready, confirmed |
| `--state-warning`, `--state-warning-soft` | Due soon, draft, caution |
| `--state-danger`, `--state-danger-soft` | Error, missing, destructive |
| `--state-info`, `--state-info-soft` | Informational state |
| `--state-neutral`, `--state-neutral-soft` | Archived, inactive, neutral |

Legacy `--green`, `--green-soft`, `--blue`, and `--blue-soft` remain aliases.

## Shadows

CTSMD should use elevation sparingly.

| Token | Use |
| --- | --- |
| `--shadow-none` | Flat rows, tables, most cards |
| `--shadow-low` | Subtle raised controls/cards |
| `--shadow-medium` | Important floating panels or selected surfaces |
| `--shadow-overlay` | Drawers, popovers, modal-like layers |

Legacy `--shadow` remains unchanged for migration safety.

## Focus And Controls

| Token | Value | Use |
| --- | ---: | --- |
| `--focus-ring` | soft burgundy ring | Future focus-visible rules |
| `--focus-ring-offset` | `2px` | Focus offset |
| `--control-height` | `42px` | Standard desktop controls |
| `--control-height-compact` | `34px` | Dense staff controls |
| `--control-height-mobile` | `44px` | Touch-friendly mobile minimum |

Mobile controls must remain touch friendly. Density should not reduce tap targets below usability.

## Buttons

Buttons are defined in `assets/css/design-system.css` and are safe to adopt incrementally.

| Class | Use |
| --- | --- |
| `.button`, `.btn` | Primary action. Existing `.button` remains supported for legacy pages. |
| `.button.secondary`, `.btn.secondary`, `.btn-secondary` | Secondary action on light surfaces. |
| `.button.ghost`, `.btn-tertiary`, `.text-button` | Low-emphasis text/tertiary action. |
| `.button.danger`, `.btn-danger`, `.mod-danger` | Destructive action. |
| `.btn-compact` | Dense staff/admin row action. |

Buttons use the shared control heights, focus ring, brand colors, and disabled state. New migrations should avoid page selectors like `.some-card button` for visual button styling.

## Badges And Status

Badges provide compact state and metadata outside full operational-table rows.

| Class | Use |
| --- | --- |
| `.badge`, `.pill` | Neutral status/metadata badge. |
| `.badge-compact` | Dense status inside tables, cards, or review items. |
| `.badge-count` | Numeric count badge. |
| `.badge-success`, `.badge.is-success` | Approved, active, ready, verified. |
| `.badge-warning`, `.badge.is-warning` | Pending, caution, due soon, held for review. |
| `.badge-danger`, `.badge.is-danger` | Rejected, blocked, missing, critical. |
| `.badge-info`, `.badge.is-info` | Informational category/state. |
| `.badge-neutral`, `.badge-muted`, `.badge.is-neutral` | Inactive, archived, neutral metadata. |

`op-table-status` remains supported for table-local status cells. When migrating, pair it with `.badge` only when the same element should inherit the global badge contract too.

## Alerts And Empty States

Use shared alerts for flash messages, test results, and state-specific notices.

| Class | Use |
| --- | --- |
| `.alert` | Base alert layout. |
| `.alert-compact` | Dense flash/message variant. |
| `.alert-success` | Completed action or positive state. |
| `.alert-warning` | Caution, pending review, non-blocking concern. |
| `.alert-danger` | Error, rejected, blocked, destructive state. |
| `.alert-info` | Informational message. |
| `.alert-neutral` | Neutral notice. |
| `.alert-title`, `.alert-body` | Optional structured alert copy. |

Use `.empty-state` for a genuine no-content state with explanatory copy. Use `.empty-state-compact` inside tables, dashboards, and small panels. Empty states should say what is absent and, when appropriate, why it is fine.

## Page And Section Headers

Shared header classes standardize hierarchy while leaving route-specific composition in feature CSS.

| Class | Use |
| --- | --- |
| `.page-header` | Top-of-view title/action grouping. |
| `.page-header-compact` | Admin/detail page header scale. |
| `.page-header-feature` | Earned member/dashboard feature moment. |
| `.page-header-main` | Text stack inside a page header. |
| `.page-header-eyebrow` | Uppercase context label. |
| `.page-header-title` | Page title. |
| `.page-header-description` | Supporting copy. |
| `.page-header-actions` | Header action group. |
| `.section-header` | Module/card/list section heading. |
| `.section-header-main` | Text stack inside a section header. |
| `.section-header-actions` | Section action group. |

Feature CSS should avoid redefining typography and spacing already supplied by these classes unless the page has a clear product reason.

## Toolbars

Use toolbars for action and filter clusters.

| Class | Use |
| --- | --- |
| `.toolbar` | Responsive action/filter row. |
| `.toolbar-primary` | Primary left-side content or mode controls. |
| `.toolbar-filters` | Filter/search controls. |
| `.toolbar-actions` | Right-aligned action group. |

## Operational Tables And Lists

The first shared operational table/list primitive is available for compact staff datasets.

| Class | Use |
| --- | --- |
| `.op-table` | Outer table/list shell. |
| `.op-table-columns` | Desktop column header row. |
| `.op-table-group` | Grouped row section. |
| `.op-table-group-header` | Group header; add `.is-dark` for dark grouped headers. |
| `.op-table-row` | Data row. |
| `.op-table-primary` | Main row identity cell. |
| `.op-table-status` | Table-local status indicator; pair with `.is-success`, `.is-warning`, `.is-danger`, `.is-info`, or `.is-neutral`. |
| `.op-table-actions` | Row action container. |
| `.op-mobile-label` | Hidden desktop label shown in mobile stacked rows. |
| `.op-table-empty` | Empty row content. |

Feature styles should provide only dataset-specific column templates and specialized content styling. `/admin/channels` and `/admin/moderation/terms` are the reference implementations.

## Forms

The first form primitive covers standard labels, inputs, selects, textareas, checkboxes, hints, and grouped fields.

| Class | Use |
| --- | --- |
| `.form-stack` | Vertical form rhythm. |
| `.form-group` | Two-column desktop group that collapses to one column on mobile. |
| `.form-field` | Label wrapper for a single field. |
| `.form-input` | Text, number, email, password, and similar inputs. |
| `.form-select` | Select controls. |
| `.form-textarea` | Textareas. |
| `.form-check` | Checkbox/radio row. |
| `.form-hint` | Supporting hint copy. |
| `.form-actions` | Button/action grouping. |

Representative migrations exist on `/account`, `/admin/channels/edit`, and `/admin/moderation/terms/edit`.

## Layout Widths

These values reflect repeated dimensions already present in the repository.

| Token | Value | Use |
| --- | ---: | --- |
| `--content-narrow` | `960px` | Focused forms and detail pages |
| `--content-max` | `1440px` | Standard app content maximum |
| `--content-wide` | `1540px` | Very wide dashboards/operations |
| `--content-gutter-desktop` | `32px` | Standard desktop distance from the main-stage boundary |
| `--content-gutter-compact` | `24px` | Tablet/smaller desktop content gutter |
| `--content-gutter-mobile` | `14px` | Mobile content gutter |
| `--sidebar-width` | `264px` | Unified shell sidebar |
| `--sidebar-width-legacy` | `252px` | Legacy app shell sidebar |

Desktop app content is left-aligned within the main stage by default. The fixed sidebar owns the left rail; the page wrapper should begin roughly one gutter after the sidebar/main boundary instead of centering a capped container inside the remaining viewport.

Use `.content-shell` for ordinary operational pages that should consume available width. Use `.content-shell-wide` when a workspace needs a wide but capped measure. Use `.content-shell-narrow` for intentionally focused reading, account, or form experiences. Avoid `margin:0 auto` on default app page wrappers unless the experience is deliberately narrow.

Mobile behavior remains route-specific and should continue to use existing mobile breakpoints and safe-area handling.

## Member UI

- Calmer and simpler.
- Mobile-first.
- Comfortable touch targets.
- One dominant purpose per screen where possible.
- Use expressive serif and hero moments only when they clarify the experience.

## Staff UI

- Denser and desktop-optimized.
- Scanability over decoration.
- Tables and compact lists are preferred for operational data.
- Cards should be earned by meaningful object grouping, not used as the default row shape.
