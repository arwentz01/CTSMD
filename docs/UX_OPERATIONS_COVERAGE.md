# Operations UX route coverage
## Evidence summary
## Evidence summary

121 registered route entries: 101 screen routes, 18 transport/download routes, one public program and one printable certificate. All 101 screen routes have explicit template adoption. **100 screen routes rendered at 1440, 768 and 390px without document overflow (300 route/viewport samples); Playbill Media is blocked before rendering.** Its separately named presentation fixture is not counted as a passing route.

The matrix distinguishes implementation from verification. `W` means the approved WorkspaceUI foundation and scoped Operations layer. Other names are exact rendered component markers when captured; W alone is not a claim that every possible subview/state was exercised. `P` means baseline PHP behavior/source contracts pass; `G` means an authenticated local GET rendered. `C` means no sampled text-contrast finding in the three Chromium probes, **not WCAG certification**. `K` refers to the limited interaction evidence described below. All screens still need physical-device/assistive-technology acceptance.

| Batch | Code commit | Screen routes |
|---|---|---|
| A — Operations foundation | `6e6d413` | 5 |
| B — Accounts and membership | `e2ac13f` | 11 |
| C — Family and safeguarding | `2aaf73a` | 13 |
| D — Production administration | `4bf7c57` | 17 |
| E — Participation operations | `2d9423a` | 25 |
| F — Content, communications and form administration | `a210404` | 30 |

### A — Operations foundation

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/staff` — Staff Overview | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations` — Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/community` — Community Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/resources` — Resource Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/forms` — Forms & Registration | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### B — Accounts and membership

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/admin/accounts` — Account & Access | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/accounts/view` — Account & Access | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations` — Registration Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/edit` — Edit Registration | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/view` — Registrants | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/intake` — Intake Review | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/people` — People & families | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/people/view` — Noah Rivera | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/teams` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/teams/view` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/private-channel` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### C — Family and safeguarding

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/student-profile` — Noah Rivera · Theatre Profile | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/student-profile/family` — Family & pickup | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/student-profile/family/review` — Family relationship requests | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/family/caregiver-schedule` — Caregiver schedule | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/theatre-history/resume` — Noah Rivera · Acting Résumé | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/theatre-history` — My Theatre History | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/late-pickups` — Late review | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Empty threshold list; populated incident/disclosure not exercised |
| `/safeguarding/conversations` — Conversation review | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/cases` — Safeguarding cases | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/case` — · CTSMD Connect | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding` — Safeguarding | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/review` — Credential review | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/audit` — Audit history | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### D — Production administration

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/archive` — Theatre archive | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Sparse synthetic history; full retention acceptance open |
| `/archive/production` — The Winter Showcase · UX history archive | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C; K | P; G | Sparse synthetic history; full retention acceptance open |
| `/archive/community` — #Historical rehearsal notices · UX fixture archive | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Sparse synthetic history; full retention acceptance open |
| `/admin/productions` — Productions & seasons | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/productions/view` — A Midsummer Night’s Dream · UX rehearsal | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Missing-artwork state; no completion/archive submission |
| `/production/casting` — Casting | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C; K | P; G | Roles drawer checked; assignment/release states need populated casting |
| `/production/readiness` — Production readiness | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/tasks` — Production tasks | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production` — Production workspace | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/groups` — Production Groups | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/groups/view` — Production Groups | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/people` — Production people | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/support-needs` — Participant support needs | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Empty confidential state only |
| `/admin/files` — File Library | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/admin/files/edit` — Rehearsal notes · UX fixture | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/admin/resources` — Resource Library | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/resources/edit` — Rehearsal notes · UX fixture | B2 adopted/refined | page, heading, form, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### E — Participation operations

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/calendar` — Calendar | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/history` — Volunteer service record | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/service-record` — Volunteer service record | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/verifications` — Service verification | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-verification` — Volunteer verification | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/training` — Training | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-development` — Volunteer requirements & development | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts` — Volunteer Operations | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts/new` — Volunteer Operations | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts/view` — Volunteer Operations | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/approvals` — Volunteer approvals | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-approvals` — Volunteer operations | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-approvals/review` — Volunteer operations | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer-readiness` — Volunteer readiness | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer-shifts` — Volunteer opportunities | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/shift` — Welcome desk · UX fixture | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/day` — Production day | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance` — Attendance | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance/take` — Take attendance | B2 adopted/refined | page | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance/report` — Report an absence | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/schedule/new` — New schedule item | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/notices` — Production updates | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/notice` — Rehearsal arrangements · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/schedule` — Schedule | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/edit` — Edit schedule item | B2 adopted/refined | page, heading, form, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### F — Content, communications and form administration

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/admin/email` — Email Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/notification-preferences` — Notification preferences | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | No provider/permission/delivery test |
| `/push-settings` — Mobile notifications | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | No provider/permission/delivery test |
| `/admin/member-resources` — Member Resource Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/member-resources/edit` — Rehearsal handbook · UX fixture | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/files` — Files | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/files/view` — File | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/resources` — Resources | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/resources/view` — Resource | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/terms` — Moderation terms | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/terms/edit` — New moderation rule | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/queue` — Moderation queue | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Empty queue; no moderation decision submitted |
| `/admin/channels` — Community Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/channels/edit` — Community Operations | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/videos` — Video Library | B2 adopted/refined | page, heading, table-region, form, panel | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/videos/view` — Ensemble rehearsal · UX fixture · CTSMD Videos | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/production/rehearsal-library` — Video Library | B2 adopted/refined | page, heading, table-region, form, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/playbill/media` — Playbill Media & Sponsors | Template implemented; controller BLOCKED | W; page/heading/panel (fixture) | Pre-render exception; fixture at 3 widths | Template-only C probe | P; GET fails | Backend sync blocker; template-only fixture |
| `/playbills` — Playbill | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/playbill` — Playbill | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/forms` — Forms | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/forms/view` — Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms` — Forms review | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/review` — Review Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/group-assign` — Group form assignment | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage` — Forms management | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage/edit` — Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage/assign` — Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/build` — Form builder | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/builder` — Form builder | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Empty field editor; no definition save |

### Public, printable and transport output — not app-shell screens

| Route | Output / category | Implementation and verification boundary |
|---|---|---|
| `/push/subscribe` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/push/unsubscribe` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/push/test` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/calendar/feed` | transport / E | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/student-profile/headshot` | transport / C | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/archive/file` | transport / D | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/member-resources/download` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/volunteer/verification` | print / E | Existing output retained; P. Synthetic certificate rendered; actual print dialog not invoked. |
| `/admin/productions/artwork-image` | transport / D | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/production/artwork-image` | transport / D | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/videos/play` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/videos/archive-download` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/videos/inventory` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/production/rehearsal-video` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/files/download` | transport / D | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/playbill` | public / F | Existing output retained; P. Public program not redesigned or republished. |
| `/playbill/asset` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/playbill/qr` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/playbill/sign` | transport / F | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
| `/safeguarding/conversations/attachment` | transport / C | Existing output retained; P. No successful delivery/transfer verified; notification permission and queue actions not exercised. |
121 registered route entries: 101 screen routes, 18 transport/download routes, one public program and one printable certificate. All 101 screen routes have explicit template adoption. **100 screen routes rendered at 1440, 768 and 390px without document overflow (300 route/viewport samples); Playbill Media is blocked before rendering.** Its separately named presentation fixture is not counted as a passing route.

The matrix distinguishes implementation from verification. `W` means the approved WorkspaceUI foundation and scoped Operations layer. Other names are exact rendered component markers when captured; W alone is not a claim that every possible subview/state was exercised. `P` means baseline PHP behavior/source contracts pass; `G` means an authenticated local GET rendered. `C` means no sampled text-contrast finding in the three Chromium probes, **not WCAG certification**. `K` refers to the limited interaction evidence described below. All screens still need physical-device/assistive-technology acceptance.

| Batch | Code commit | Screen routes |
|---|---|---|
| A — Operations foundation | `6e6d413` | 5 |
| B — Accounts and membership | `e2ac13f` | 11 |
| C — Family and safeguarding | `2aaf73a` | 13 |
| D — Production administration | `4bf7c57` | 17 |
| E — Participation operations | `2d9423a` | 25 |
| F — Content, communications and form administration | `a210404` | 30 |

### A — Operations foundation

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/staff` — Staff Overview | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations` — Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/community` — Community Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/resources` — Resource Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/forms` — Forms & Registration | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### B — Accounts and membership

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/admin/accounts` — Account & Access | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/accounts/view` — Account & Access | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations` — Registration Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/edit` — Edit Registration | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/view` — Registrants | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/intake` — Intake Review | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/people` — People & families | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/people/view` — Noah Rivera | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/teams` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/teams/view` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/private-channel` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### C — Family and safeguarding

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/student-profile` — Noah Rivera · Theatre Profile | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/student-profile/family` — Family & pickup | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/student-profile/family/review` — Family relationship requests | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/family/caregiver-schedule` — Caregiver schedule | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/theatre-history/resume` — Noah Rivera · Acting Résumé | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/theatre-history` — My Theatre History | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/late-pickups` — Late review | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Empty threshold list; populated incident/disclosure not exercised |
| `/safeguarding/conversations` — Conversation review | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/cases` — Safeguarding cases | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/case` — · CTSMD Connect | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding` — Safeguarding | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/review` — Credential review | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/audit` — Audit history | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### D — Production administration

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/archive` — Theatre archive | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Sparse synthetic history; full retention acceptance open |
| `/archive/production` — The Winter Showcase · UX history archive | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C; K | P; G | Sparse synthetic history; full retention acceptance open |
| `/archive/community` — #Historical rehearsal notices · UX fixture archive | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Sparse synthetic history; full retention acceptance open |
| `/admin/productions` — Productions & seasons | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/productions/view` — A Midsummer Night’s Dream · UX rehearsal | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Missing-artwork state; no completion/archive submission |
| `/production/casting` — Casting | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C; K | P; G | Roles drawer checked; assignment/release states need populated casting |
| `/production/readiness` — Production readiness | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/tasks` — Production tasks | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production` — Production workspace | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/groups` — Production Groups | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/groups/view` — Production Groups | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/people` — Production people | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/support-needs` — Participant support needs | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Empty confidential state only |
| `/admin/files` — File Library | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/admin/files/edit` — Rehearsal notes · UX fixture | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/admin/resources` — Resource Library | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/resources/edit` — Rehearsal notes · UX fixture | B2 adopted/refined | page, heading, form, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### E — Participation operations

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/calendar` — Calendar | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/history` — Volunteer service record | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/service-record` — Volunteer service record | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/verifications` — Service verification | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-verification` — Volunteer verification | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/training` — Training | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-development` — Volunteer requirements & development | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts` — Volunteer Operations | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts/new` — Volunteer Operations | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts/view` — Volunteer Operations | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/approvals` — Volunteer approvals | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-approvals` — Volunteer operations | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-approvals/review` — Volunteer operations | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer-readiness` — Volunteer readiness | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer-shifts` — Volunteer opportunities | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/shift` — Welcome desk · UX fixture | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/day` — Production day | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance` — Attendance | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance/take` — Take attendance | B2 adopted/refined | page | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance/report` — Report an absence | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/schedule/new` — New schedule item | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/notices` — Production updates | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/notice` — Rehearsal arrangements · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/schedule` — Schedule | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/edit` — Edit schedule item | B2 adopted/refined | page, heading, form, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### F — Content, communications and form administration

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/admin/email` — Email Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/notification-preferences` — Notification preferences | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | No provider/permission/delivery test |
| `/push-settings` — Mobile notifications | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | No provider/permission/delivery test |
| `/admin/member-resources` — Member Resource Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/member-resources/edit` — Rehearsal handbook · UX fixture | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/files` — Files | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/files/view` — File | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/resources` — Resources | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/resources/view` — Resource | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/terms` — Moderation terms | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/terms/edit` — New moderation rule | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/queue` — Moderation queue | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Empty queue; no moderation decision submitted |
| `/admin/channels` — Community Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/channels/edit` — Community Operations | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/videos` — Video Library | B2 adopted/refined | page, heading, table-region, form, panel | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/videos/view` — Ensemble rehearsal · UX fixture · CTSMD Videos | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/production/rehearsal-library` — Video Library | B2 adopted/refined | page, heading, table-region, form, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/playbill/media` — Playbill Media & Sponsors | Template implemented; controller BLOCKED | W; page/heading/panel (fixture) | Pre-render exception; fixture at 3 widths | Template-only C probe | P; GET fails | Backend sync blocker; template-only fixture |
| `/playbills` — Playbill | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/playbill` — Playbill | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/forms` — Forms | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/forms/view` — Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms` — Forms review | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/review` — Review Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/group-assign` — Group form assignment | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage` — Forms management | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage/edit` — Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage/assign` — Rehearsal agreement · UX fixture | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/build` — Form builder | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/builder` — Form builder | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Empty field editor; no definition save |

### Public, printable and transport output — not app-shell screens

| Route | Output / category | Implementation and verification boundary |
|---|---|---|
| `/push/subscribe` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/push/unsubscribe` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/push/test` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/calendar/feed` | transport / E | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/student-profile/headshot` | transport / C | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/archive/file` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/member-resources/download` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/volunteer/verification` | print / E | Existing output retained; P. Synthetic certificate rendered; actual print dialog not invoked. |
| `/admin/productions/artwork-image` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/production/artwork-image` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/videos/play` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/videos/archive-download` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/videos/inventory` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/production/rehearsal-video` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/files/download` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/playbill` | public / F | Existing output retained; P. Public program not redesigned or republished. |
| `/playbill/asset` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/playbill/qr` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/playbill/sign` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/safeguarding/conversations/attachment` | transport / C | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
Baseline: `c6c7852`, existing `audit/board-pilot-readiness`. Inventory resolved against `front.php` dispatch order, handlers and Operations/contextual links before editing. A parent page never proves a detail view. This is a presentation pass, not release approval.

## Evidence summary

121 registered route entries: 101 screen routes, 18 transport/download routes, one public program and one printable certificate. All 101 screen routes have explicit template adoption. **100 screen routes rendered at 1440, 768 and 390px without document overflow (300 route/viewport samples); Playbill Media is blocked before rendering.** Its separately named presentation fixture is not counted as a passing route.

The matrix distinguishes implementation from verification. `W` means the approved WorkspaceUI foundation and scoped Operations layer. Other names are exact rendered component markers when captured; W alone is not a claim that every possible subview/state was exercised. `P` means baseline PHP behavior/source contracts pass; `G` means an authenticated local GET rendered. `C` means no sampled text-contrast finding in the three Chromium probes, **not WCAG certification**. `K` refers to the limited interaction evidence described below. All screens still need physical-device/assistive-technology acceptance.

| Batch | Code commit | Screen routes |
|---|---|---|
| A — Operations foundation | `6e6d413` | 5 |
| B — Accounts and membership | `e2ac13f` | 11 |
| C — Family and safeguarding | `2aaf73a` | 13 |
| D — Production administration | `4bf7c57` | 17 |
| E — Participation operations | `2d9423a` | 25 |
| F — Content, communications and form administration | `a210404` | 30 |

### A — Operations foundation

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/staff` — Staff Overview | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations` — Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/community` — Community Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/resources` — Resource Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/operations/forms` — Forms & Registration | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### B — Accounts and membership

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/admin/accounts` — Account & Access | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/accounts/view` — Account & Access | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations` — Registration Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/edit` — Edit Registration | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/view` — Registrants | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/registrations/intake` — Intake Review | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/people` — People & families | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/people/view` — Noah Rivera | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/teams` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/teams/view` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/private-channel` — Teams | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### C — Family and safeguarding

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/student-profile` — Noah Rivera · Theatre Profile | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/student-profile/family` — Family & pickup | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/student-profile/family/review` — Family relationship requests | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/family/caregiver-schedule` — Caregiver schedule | B2 adopted/refined | page | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/theatre-history/resume` — Noah Rivera · Acting Résumé | B2 adopted/refined | page, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/theatre-history` — My Theatre History | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/late-pickups` — Late review | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/conversations` — Conversation review | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/cases` — Safeguarding cases | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/case` — · CTSMD Connect | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding` — Safeguarding | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/review` — Credential review | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/safeguarding/audit` — Audit history | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### D — Production administration

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/archive` — Theatre archive | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Sparse synthetic history; full retention acceptance open |
| `/archive/production` — The Winter Showcase · UX history archive | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C; K | P; G | Sparse synthetic history; full retention acceptance open |
| `/archive/community` — #Historical rehearsal notices · UX fixture archive | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Sparse synthetic history; full retention acceptance open |
| `/admin/productions` — Productions & seasons | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/productions/view` — A Midsummer Night’s Dream · UX rehearsal | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Missing-artwork state; no completion/archive submission |
| `/production/casting` — Casting | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/readiness` — Production readiness | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/tasks` — Production tasks | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production` — Production workspace | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/groups` — Production Groups | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/groups/view` — Production Groups | B2 adopted/refined | page, heading, panel, form | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/people` — Production people | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/support-needs` — Participant support needs | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Empty confidential state only |
| `/admin/files` — File Library | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/admin/files/edit` — Rehearsal notes · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/admin/resources` — Resource Library | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/resources/edit` — Rehearsal notes · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### E — Participation operations

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/calendar` — Calendar | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/history` — Volunteer service record | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/service-record` — Volunteer service record | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/verifications` — Service verification | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-verification` — Volunteer verification | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/training` — Training | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-development` — Volunteer requirements & development | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts` — Volunteer Operations | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts/new` — Volunteer Operations | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-shifts/view` — Volunteer Operations | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/approvals` — Volunteer approvals | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-approvals` — Volunteer operations | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/volunteer-approvals/review` — Volunteer operations | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer-readiness` — Volunteer readiness | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer-shifts` — Volunteer opportunities | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/volunteer/shift` — Welcome desk · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/day` — Production day | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance` — Attendance | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance/take` — Take attendance | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/attendance/report` — Report an absence | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/schedule/new` — New schedule item | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C; K | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/notices` — Production updates | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/notice` — Rehearsal arrangements · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/schedule` — Schedule | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/production/edit` — Edit schedule item | B2 adopted/refined | page, heading, form, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |

### F — Content, communications and form administration

| Route / page title | Modernization | Shared components | Responsive evidence | Accessibility | Regression | Remaining review |
|---|---|---|---|---|---|---|
| `/admin/email` — Email Operations | B2 adopted/refined | page, heading | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/notification-preferences` — Notification preferences | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | No provider/permission/delivery test |
| `/push-settings` — Mobile notifications | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | No provider/permission/delivery test |
| `/admin/member-resources` — Member Resource Operations | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/member-resources/edit` — Rehearsal handbook · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/files` — Files | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/files/view` — File | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/resources` — Resources | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/resources/view` — Resource | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/terms` — Moderation terms | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/terms/edit` — New moderation rule | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/moderation/queue` — Moderation queue | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Empty queue; no moderation decision submitted |
| `/admin/channels` — Community Operations | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/channels/edit` — Community Operations | B2 adopted/refined | page, heading, form | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/videos` — Video Library | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/videos/view` — Ensemble rehearsal · UX fixture · CTSMD Videos | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Metadata only; binary/playback untested |
| `/production/rehearsal-library` — Video Library | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/playbill/media` — Playbill Media & Sponsors | Template implemented; controller BLOCKED | W; page/heading/panel (fixture) | Pre-render exception; fixture at 3 widths | Template-only C probe | P; GET fails | Backend sync blocker; template-only fixture |
| `/playbills` — Playbill | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/playbill` — Playbill | B2 adopted/refined | page, heading, panel | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/forms` — Forms | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/forms/view` — Rehearsal agreement · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms` — Forms review | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/review` — Review Rehearsal agreement · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/group-assign` — Group form assignment | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage` — Forms management | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage/edit` — Rehearsal agreement · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/manage/assign` — Rehearsal agreement · UX fixture | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/build` — Form builder | B2 adopted/refined | W (feature layout retained) | 1440/768/390; no page overflow | C | P; G | Device/AT review; communicating/destructive saves not UI-tested |
| `/admin/forms/builder` — Form builder | B2 adopted/refined | page, form | 1440/768/390; no page overflow | C | P; G | Empty field editor; no definition save |

### Public, printable and transport output — not app-shell screens

| Route | Output / category | Implementation and verification boundary |
|---|---|---|
| `/push/subscribe` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/push/unsubscribe` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/push/test` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/calendar/feed` | transport / E | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/student-profile/headshot` | transport / C | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/archive/file` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/member-resources/download` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/volunteer/verification` | print / E | Existing output retained; P. Synthetic certificate rendered; actual print dialog not invoked. |
| `/admin/productions/artwork-image` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/production/artwork-image` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/videos/play` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/videos/archive-download` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/videos/inventory` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/production/rehearsal-video` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/files/download` | transport / D | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/playbill` | public / F | Existing output retained; P. Public program not redesigned or republished. |
| `/playbill/asset` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/playbill/qr` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/playbill/sign` | transport / F | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |
| `/safeguarding/conversations/attachment` | transport / C | Existing output retained; P. No worker, permission request or transfer invoked for visual review. |

## Embedded and absent surfaces

### Batch B evidence

Accounts (directory and detail), People (directory/detail), Teams (directory/membership/private room), and registrations (opportunities/edit/submissions/intake) explicitly adopt page/header/panel/form roles. All 11 route samples rendered at 1440/768/390px without page overflow or PHP errors. Desktop and phone screenshots reviewed for account detail, registration edit/intake and team membership. Ten comparable pre/post form inventories match actions, methods and named field/type contracts. Intake baseline initially used the wrong query key; its corrected populated review was checked independently, not claimed as a matching baseline. The PHP behavior contract and closed-form/roster boundary tests pass. Communicating account/registration decisions were not submitted.

### Batch C evidence

Student profile, family/pickup, relationship requests, caregiver-only calls, theatre history/résumé, Late pickup, safeguarding queues/case detail/conversation review/audit explicitly adopt the shared presentation. All 13 route samples rendered at 1440/768/390px with no page overflow or PHP errors after correcting the conversation template's base-path argument. The caregiver view was tested with an actual synthetic active caregiver relationship, not administrator access. Credential review was rechecked with a populated synthetic pending credential after its missing-record fallback was tested. Screenshot review includes restricted case detail and phone conversation review. Gold-on-white panel labels were replaced with readable muted text. Résumé styles are conditional on non-print mode; public letters are not opted in. PHP behavior contracts, safeguarding review regression and lifecycle presentation tests pass. No case decision, statement invitation or pickup relationship was submitted through the UI.

### Batch D evidence

Production directory/settings/artwork/lifecycle, workspace/casting/readiness/tasks, roster/groups, restricted support needs, file/resource administration and archive renderers explicitly adopt the standard. All 17 route samples render at 1440/768/390px without page overflow or PHP errors. Reviewed desktop group membership/settings and phone archive production screenshots. Neutralized dark-banner leftovers including an unreadable white return link, pale archive labels and roster status text. Existing working-production hero artwork and casting interaction model remain intact; no confidential summary was added. New-file form and resource edit were sampled; binary file/version and artwork-present samples require dedicated assets. PHP behavior contract and lifecycle boundary tests pass. Completion/archive/destructive actions were not submitted.

### Batch E evidence

25 screen routes adopt or refine existing B2 participation components, including schedule edit/create, unsent notice review, attendance take/report, production day, volunteer approval detail, shifts, eligibility/training and verification/coordinator administration. Each was captured at 1440/768/390px with no document overflow. Reviewed desktop populated notice draft and phone shift creation/date controls. Fixed inherited white return links and pale eligibility/coverage/status text. Service record styling is conditional on non-print mode; `/volunteer/verification` is a printable certificate, not an app-shell screen, and is intentionally retained. Synthetic metadata/unsent drafts were added only to the isolated local schema; no publishing, notification permission request, worker or real certificate issuance occurred. Lifecycle/closed-form and PHP behavior checks pass. The separate backend suite covers its named form/volunteer actions and authorization assertions; these screenshots do not prove attendance saves or volunteer approval decisions.

### Batch F evidence and functional blocker

Email operations, notification preferences, delegated `PushExperience` settings, member resources, shared file/resource libraries, channel management, moderation terms/queue, videos and Playbill controls adopt explicit components. Phone/desktop screenshots reviewed for channels, Playbill editing and notification diagnostics. Public programs, QR/sign/asset streams and printable service letters retain their own rendering. Video inventory and `/production/rehearsal-video` are transport/download routes, not unstyled screens. Media fixtures are metadata-only: no playback, upload or binary delivery claim.

**Blocked actual route:** `/admin/playbill/media` fails before its template in the existing cast-profile synchronization query (`cr.role_title` references an undefined alias). Fully migrated isolated schema reproduces this. Backend correction is outside the authorized UX scope and tracked in TODO. To review the template without misrepresenting controller success, a temporary authenticated, GET-only loopback presentation fixture invoked the existing `adminPage` renderer without synchronization. It rendered at 1440/768/390px with no page overflow; screenshots are explicitly named `playbill-media-fixture-*`. These are template-only evidence, not a passing route or successful sponsor/cast/artwork save. Production code has no fixture route or bypass.

Keyboard interaction review identified and fixed Volunteer Leads focus restoration/trapping and a narrow-screen Casting drawer sizing issue. No notification permission was requested, device subscribed, test notification queued, delivery worker activated or publication submitted. PHP behavior contracts include delegated Push and existing form administration rendering (52 renderers); source contracts are not a claim that every workflow was executed.

- Account detail owns contact editing, roles, invitations, account lifecycle and relationship links; no separate roles/settings screen is invented. People detail retains staff relationship/roster context.
- Registrations owns opportunity edit, submitted applications and person/household intake review. Teams owns member editing and private-channel creation.
- Production detail owns artwork, lifecycle configuration, typed completion/archive confirmations. Casting drawers, attendance drawers and late-pickup review remain separate interaction checks.
- Playbill media owns sponsors, approved biographies, cast profiles, edition/publication controls; public pages and protected asset routes remain separate.
- There is no standalone general organization-settings, staff-access, message-administration or notification-delivery-administration route. Existing accounts, safeguarding conversation review, email operations and personal notification settings remain their real destinations. No feature is invented.
- `ScheduleImportExperience` exists but `/production/schedule/import` is not registered in the dispatch table; record the navigation dependency, do not activate an unregistered feature during UX work.
- Member Home/Family/Community/Messages/Notifications retain prior scoped modernization; shared resources/history/print views are checked explicitly when an operational flow reaches them.

## Secondary views and interaction evidence

These are independently rendered states, not inferred from their parent route. Each additional view was captured at 1440/768/390px: 27 extra viewport samples with no document overflow, PHP error or sampled text-contrast finding.

| View | Actual path / interaction | Verification boundary |
|---|---|---|
| Denied memberships | `/admin/accounts?view=denied` | Empty denied queue; no membership decision submitted |
| Registration submissions | `/admin/registrations?view=submissions` | Synthetic submitted application |
| Registration intake list | `/admin/registrations?view=intake` | List inspected separately from opportunity and person intake detail |
| Calendar week | `/calendar?view=week` | Existing view; no new navigation or schedule write |
| Calendar agenda | `/calendar?view=agenda` | Existing view; Month is already covered by the route matrix |
| Archived production settings | `/admin/productions/view?id={archivedProduction}` | Closed-production settings presentation inspected; no lifecycle action submitted |
| Past Productions disclosure | `/admin/productions`, expanded native disclosure | Historical directory entries visible; not a full retention rehearsal |
| Private statement request disclosure | `/safeguarding/case?id={case}`, expanded request | Recipient/context/expiry controls rendered; no invitation sent |
| Conversation review reason disclosure | `/safeguarding/conversations`, expanded review | Reason/case fields rendered; no conversation-content review POST or audited download performed |

The separate browser interaction script passes **76 checks**: three drawers at three widths (open, initial focus, forward Tab wrap, bounds, Escape/focus return), three date-control geometry checks, three intercepted group-form submit events, 24 low-role denials, and one read-only archive assertion. It does not submit a group change. Casting assignment/release drawers need populated casting fixtures; Late pickup review needs a threshold/incident fixture; pending/denied membership decisions, populated moderation reports, and real artwork/sponsors/attachments remain additional state acceptance. No invisible state is marked visually verified.

## Measured results versus design judgment

Measured on code `1b02b97` with synthetic users in a complete isolated local schema:

- 186 PHP files passed syntax checks; the web safety test set and browser JavaScript syntax checks pass.
- 52 renderers preserve baseline non-presentation PHP **and form/control protocol attributes**. This includes action/method, named controls, hidden values and existing validation/disabled markup, independent of class styling. Dispatch ownership is checked against `front.php`, including shadowed aliases and delegated Push rendering.
- 100 actual screens × three widths have no document overflow and no sampled visible-text contrast finding. The blocked Playbill route is excluded. This is a computed-style probe, not a complete WCAG audit.
- All 267 sampled enabled visible text/select/textarea controls on phone were at least 44px high. Their computed edge is `rgb(141,125,117)`, measuring **3.95:1 against white**. Checkbox/radio label targets, every conditional state and assistive-technology behavior are not certified by that measurement.
- 76 browser interaction/role checks pass. Local real HTTP/API acceptance passes 120 assertions; 36 safeguarding checks pass in a rolled-back database transaction. The backend suite proves its named scenarios, not every screen save.
- [Web safety CI](https://github.com/arwentz01/CTSMD/actions/runs/40803725183) and [isolated RC1 CI](https://github.com/arwentz01/CTSMD/actions/runs/40803725199) passed for the verified UI code. Native app files were not expanded or changed.

Subjective judgment: the quiet ivory frame, compact headings, stronger field edges and burgundy actions feel like one operational system. Lists, review panels, long forms and editorial Playbill previews retain their appropriate layouts rather than becoming identical card grids. The three design skills informed those choices; none of these judgments is a measured usability score or proof of user acceptance.

Sanitized results are committed in `tests/fixtures/operations-browser-evidence.json`. Raw authenticated screenshots and measurement JSON remain local under `/tmp/ctsmd-ops-after/`, `/tmp/ctsmd-ops-interactions/` and `/tmp/ctsmd-ops-subviews/`; they are not uploaded as public artifacts. Representative screenshots actually reviewed include the Operations hub, account detail, registration edit/intake, team membership, restricted case/reason forms, group membership, archive production, notice draft, shift creation, channel administration, Playbill edit and notification settings. All other route captures have geometry/contrast evidence, not a claim of equivalent human visual scrutiny.

## Environment and remaining acceptance

The default local `ctsmd` demo was inspected **read-only** and still fails 45 declared schema contracts, including safeguarding statement/video tables and registration/notification columns. No migration was applied. This is separate from the Playbill alias defect, which also reproduces in the complete isolated schema. Do not interpret successful isolated screenshots as proof the default demo or hosted installation is migrated.

Remaining acceptance: physical iOS/Android and Safari/Firefox, screen readers, 200% zoom/large text, populated conditional states, binary upload/download/playback, provider delivery, actual communicating/destructive saves, concurrency and full historical retention. Existing RC1 NO-GO gates remain open. Production was not contacted or deployed; no production data, authorization rules, routes or schemas changed. No delivery queue worker was started. Existing browser service-worker registration is not a delivery test.

## Repeating the local browser checks

The checked-in scripts only allow an explicit loopback HTTP port, require an `isolated:true` fixture declaration, and reject identities whose visible test email does not end in `@example.test` or `@rc1.invalid`. They do not provision a database or real accounts. Use a fully migrated disposable local schema and synthetic fixture IDs; never point the local server at a live database. The fixture JSON contains environment-specific IDs for admin/guardian/student/volunteer, current/archived production, group, event, team, channels, credential/case, registration/intake, form/assignment/submission, shift/approval, resources, file/video, notice and verification. The current local fixture file is `/tmp/ctsmd-ops-browser-fixture.json`; it is not portable to a different database.

```sh
export CTSMD_BROWSER_LIB=/Users/Andrew/.agents/skills/design-taste-frontend/scripts/lib
export CTSMD_UX_BASE_URL=http://127.0.0.1:8096
export CTSMD_UX_FIXTURE=/tmp/ctsmd-ops-browser-fixture.json
export CTSMD_UX_OUTPUT=/tmp/ctsmd-operations-repeat
node tests/ux_operations_browser.mjs all
node tests/ux_operations_interactions.mjs
```

Run from the repository root. Choose `A`–`F` instead of `all`, or use `CTSMD_UX_ROUTES` with comma-separated screen paths for targeted checks. The render script currently exits **2** for the known Playbill pre-render blocker, **1** for unexpected render/adoption/overflow failures, and **0** for a selected subset without blockers. The interaction script exits nonzero for failed assertions. Neither script treats a compiled page as a successful workflow save. Test identity/production-context POSTs are local setup; business form submission is intercepted or not invoked. Raw evidence can contain authenticated context, so keep the output directory outside Git.

## Playbill Studio follow-up

The historical Playbill Media pre-render blocker above is superseded by the unified Studio implementation; `/admin/playbill/media` now delegates to it. All seven stages pass authenticated HTTP rendering in the isolated schema. See [PLAYBILL_STUDIO.md](PLAYBILL_STUDIO.md). Historical screenshots/measurements above have not been rerun and do not describe the new Studio.
