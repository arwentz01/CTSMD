# Commerce design system

Scope: Commerce staff workspace and restricted ticket storefront only. The shared CTSMD application navigation is retained.

Burgundy (#702a3f) identifies actions and selected seats; ivory (#faf7f1) frames the paper work surface (#fffdfa); gold provides warnings and visible keyboard focus. Flat surfaces, quiet borders, 4–8px corners, inherited application type, 14px labels, 20–26px task headings and tabular amounts keep theatre operations readable.

Four primary workspaces use a local vertical rail. Only the current workspace exposes secondary tools. At tablet sizes the rail folds above the work surface. All controls keep 44px touch targets. Wide order tables scroll within their own frame. Dialogs use native focus containment, Escape and focus return; ordinary server routes remain available without JavaScript.

Selling uses a map pane and purchase pane, stacked on tablets and phones. Seat lists remain accessible alternatives. Shared map allocation supports single-show and reviewed multi-show operations. Money fields produce the existing integer-cents payload; advanced zone/category JSON remains available.

Status messages use text as well as color. Sales readiness, provider status and delivery claims come from existing services; no live payment or public sales controls are introduced.


## Production sales and performance management

Overview and Productions share production cards with chronological date groups and individual showtime rows. Sold / physical capacity, sellable inventory, holds, allocations and net settled revenue remain separate values. Closed or unready shows have zero sellable inventory; an allocation or temporary hold is not a sold-out ticket. UTC offsets accompany venue-local showtimes, including repeated daylight-saving hours.

Add Performance, production pricing and individual management use native drawers with focus return and normal-route fallbacks. Date/time rows support multiple same-day shows and retain input after review errors. Pricing uses editable category/zone rows and decimal currency fields, including the calendar workflow. Existing performances remain overrides until staff explicitly choose inheritance. Default revisions affect only future inheriting performances; existing orders and historical pricing remain frozen.
