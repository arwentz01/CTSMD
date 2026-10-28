# CTSMD Connect UX design system

## Phase 3 operational and native adoption

Operational routes add `.ux-operational` and a domain scope to `.ux-workspace`. `ux-operational.css` consumes the same `--ux-*` values; it is an adoption layer, not another palette or design system. Both shared stylesheets are kept after inherited shell layers. Operational headings are compact sans, forms retain visible labels/control edges, status is expressed in text, and phone controls keep 44px targets. Existing information/data/POST boundaries remain authoritative.

Forms preserve subject, production, assignment and submission meanings. Closed-production family/detail, management and builder controls receive presentation locks from the existing lifecycle guard; review actions are suppressed for closed form definitions. These locks do not secure direct POSTs. Missing lifecycle enforcement is explicitly tracked as a backend release blocker.

Completion and archival are separate deliberate decisions. Readiness wording describes the existing finding's count/severity: Blocked for a positive blocker, Warning for positive nonblocking unfinished work, Ready for zero. It never computes new eligibility or turns warnings into blockers.

Native uses the matching semantic values in `mobile/src/theme/colors.ts`, system UI type, Expo tab/stack navigation, shared Screen/Item/LoadState/TouchTarget components, 48dp minimum pressable targets and explicit retry. No desktop sidebar is copied. Production artwork remains domain content rather than decorative chrome. Native APIs, auth and policy are not owned by these components. Device acceptance, expanded tablet navigation and full dark-mode mapping remain open; TypeScript is not a native visual test.

## Phase 2 adoption

Family, personal home, Community, Messaging and Notifications adopt the existing `.ux-workspace` shell with route-specific `.ux-family`, `.ux-student`, `.ux-community`, `.ux-messages`, and `.ux-notifications` scopes. These reuse the same `--ux-*` values; they do not replace authorization, delivery or lifecycle services. The personal-home scope applies to the existing role-aware `/app` renderer, with student and guardian controls determined by its existing role data.

Family attention places forms and pending offers before child summaries. Detailed tasks appear once; child summaries give context and counts. Households keep all authorized children and productions. Forms link by assignment ID to the existing viewer-authorized form route. The default is a complete household, not a selected-show filter.

Community keeps organization/production channel scope and permissioned composition. Reactions use Like/Liked text and pressed semantics; pinned posts have a text label. Messaging uses neutral bubbles and explicit participant context. Native safeguarding/privacy disclosure keeps monitoring and current-relationship explanations reachable on mobile without changing server-controlled send permission. Composers/search/file inputs have accessible names; attachment routes remain guarded.

Notifications separate pending actions, unread/read informational updates and completed-form history. Always show subject/production and the stored due/status text. Never treat marking a notification read as completion. Closed-production forms do not belong in current work; historical completed forms must remain labeled as records.

## Direction and adoption

Calm Backstage Operations uses the existing warm CTSMD foundation. The scoped layer covers Staff Overview, Production Workspace, Casting and the five Phase 2 experiences above. Other routes and native `mobile/` keep their current presentation. The global navigation destinations, permissions and working-production selector remain intact.

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
