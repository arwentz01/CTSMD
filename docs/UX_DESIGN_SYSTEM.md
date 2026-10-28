# CTSMD Connect UX design system

## Direction and adoption

Calm Backstage Operations uses the existing warm CTSMD foundation. This first implementation is scoped to `.ux-workspace` on Staff Overview, Production Workspace and Casting. Other routes and native `mobile/` keep their current presentation. The global navigation destinations, permissions and working-production selector remain intact.

## Shared tokens

`assets/css/ux-workspaces.css` owns `--ux-*` tokens: warm ground, white paper, charcoal ink, readable muted text, wine action, gold accent, quiet rule, strong control edge, spacing, radius and focus. Existing global tokens remain compatible and are not silently redefined.

Typography uses the current local/system sans stack for operating labels, forms, numbers and navigation; Georgia is reserved for the production title. No external font requests are added. Body and navigation are 14px, metadata 12–13px, section headings 16–18px and page titles 24–28px. Counts use tabular numerals.

## Reusable patterns

- `WorkspaceUI::pageHeader()` renders an escaped heading, scope description and optional real contextual action.
- `WorkspaceUI::workflow()` renders a named sequence of workflow concepts without implying that a linear step is completed.
- `.ux-workspace` provides the shared application shell and interaction layer.
- Staff review rows retain their permission-filtered data and real queue links.
- `.pw-tool-group` groups existing production tools; tool descriptions remain visible and the same authorization checks stay at each link.
- Casting keeps private decisions, roster finalization, family results and publication distinct. Guidance describes those existing boundaries, without inventing a new backend state machine. Release forms use a native `details` disclosure after the working board; all submit contracts are retained.

The new stylesheet is linked in the initial HTML and moved after existing dynamically loaded shell layers by `unified-navigation.js`. This avoids a JavaScript-only styling dependency while preserving the legacy layer order elsewhere. `ux-workspaces.js` progressively adds outline SVG icons to existing destinations, current-page semantics, keyboard tabs and selection of the populated Cast list when callbacks are empty. It never submits production changes.

## States and accessibility

Use text alongside all semantic colors. One primary action has the burgundy fill; secondary actions use neutral controls. Gold is an identity accent, not a success or warning replacement. Form edges remain visible. Visible keyboard focus, pointer press feedback and reduced-motion behavior are shared. Mobile controls have a 44px minimum target height. Horizontal overflow belongs inside a wide table, never on the page.

Production imagery is served through the existing authorized endpoint; the title remains readable on a solid panel beside the image rather than relying on arbitrary image brightness. Missing images retain the same usable title and context.

## Audience variation

Staff operations use compact rows and warm neutral chrome. Family/student screens can inherit the warm palette with more generous touch spacing once their multi-production behavior has been verified. Public production pages can use larger editorial typography and artwork. They share identity but need not share operational density. PLAYBILL protected branding is not copied.

## Implementation boundary

This visual system does not own authorization, notifications, publication visibility, lifecycle state, roster membership, or family relationships. Document a backend dependency before changing any of those meanings.
