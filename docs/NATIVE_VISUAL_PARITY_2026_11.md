# CTSMD Connect native app reconciliation

**Source review:** November 3, 2026 · branch `audit/board-pilot-readiness`  
**Application:** Expo SDK 58, `mobile/`, existing PHP authorization and `/api/mobile/*` identity contract. This is a source-level parity inventory, not a device acceptance or release approval.

## Product decision: native daily work, browser for specialized operations

The app is the everyday CTSMD Connect companion, **not a second copy of every web administration screen**. Prioritize personally relevant tasks, communications, calendar, current production, family logistics and notifications as native screens. Keep deeper authoring, advanced staff administration, complex scheduling and financial processing on the responsive web until separately prioritized and supported by purpose-built mobile APIs. A website handoff does **not** count as native parity or as single-sign-on. Always preserve server-side RBAC and safeguarding.

**Live customer ticket sales remain on Ludus:** `https://ctsmd.ludus.com/index.php`. The self-hosted `/tickets` storefront is an **internal restricted pilot** and must not replace the mobile Tickets link until an explicit migration decision and hosted acceptance. Commerce administrative shortcuts may open the authenticated web workspace for authorized administrators, but no Stripe Checkout or seat-map purchase implementation is required in Expo now.

## Existing native UI and navigation

- Five persistent destinations: **Home**, **Theatre**, **Community**, **Messages**, **More**. Forms and Volunteer remain reachable as secondary native destinations rather than overcrowding the tab bar.
- Shared `Screen` now has a compact CTSMD monogram/wordmark and consistent titles/spacing. More groups member destinations separately from conditional staff/admin tools; web handoffs are marked with the external-link icon and an explicit website description.
- The native calendar is now a compact grouped agenda with Upcoming/All visible controls. It uses event records already authorized by `/api/mobile/theatre`; there is no new schedule-write permission or inference from production date ranges.
- Website destinations are centralized and allowlisted in `mobile/src/navigation/website.ts`; current member access tokens do not automatically authenticate the separate website session. Non-CTSMD resource and Ludus destinations remain explicit external URLs.
- Visual work is an initial shared-shell pass. It is **not** a complete implementation of every web UI token, dark mode, tablet navigation pattern or accessibility acceptance.

## Surface-by-surface inventory

| Surface | Native state | Current mobile route / safe fallback | Next step |
| --- | --- | --- | --- |
| Login, profile, secure session | Native | `/login`, `/account`, SecureStore + backend token | Device and account-switching acceptance; web profile editing still separate |
| Personal Home / Action Center | Native partial | Home and `/action-center`, typed daily tasks | Reconcile latest web Today cards, family-aware prioritization and actionable completion |
| Productions / cast / crew | Native member view | `/(tabs)/theatre`, `/theatre/[id]`; staff editing via production website | Align cards and lifecycle statuses with web; staff working-production selection not native |
| Personal calendar | Native agenda | `/calendar`, `/events/[id]`; advanced schedule editing on website | Device layout/accessibility; future member filtering and schedule-change states |
| Community feeds/channels | Native | Community tab, channel/post details | Web parity for richer post authoring, native video attachments and notifications |
| Direct / safeguarded messaging | Native | Messages tab, thread and new conversation | Device safeguarding, mute and revocation acceptance; no client bypass |
| Assigned simple forms | Native partial | Forms tab and form detail | Dynamic/structured forms and parent-completed forms need secure native API; website handoff meanwhile |
| Volunteer signups/cancel/approval | Native partial | Volunteer secondary route | Readiness, lead workflows, history/training need improved native API or web handoff |
| Notifications, action routing | Native partial | `/notifications`, `/settings` | Actual APNs/FCM delivery, OS taps and real-device consent testing still open |
| Video library/player | Native | `/videos`, `/videos/[id]` | Physical device playback/accessibility and secure media lifecycle testing |
| Family relationships | Native read-only | `/family`; management website | Native family hub and guardian-managed pickup controls need separate secure API |
| Caregiver logistics | **Native source implemented; device acceptance open** | `/caregiver-schedule` reads the existing scoped mobile API; caregiver-only Home, Family and More link to it | Verify identity gating, dynamic type, real-device behavior and relationship revocation |
| Personal Theatre history/performer consent | Website handoff | More → Theatre history / Performer profile | Prioritize native read/edit only after scoped API and authorization review |
| Student audition/registration | Website handoff | More → Auditions & registration | Complex builder/registration web-first for this phase |
| Production staff workspace | Native read-only shortcuts plus website handoff | Authorized production detail → Tasks, People, Production day, Readiness; More → workspace | Staff working-production selection, operational read dashboard and write actions remain native gaps |
| Production day/attendance | Website handoff | More → Production day & attendance | Candidate for native Phase 2, needs mutation/role/clock consistency contract |
| Production tasks and people | Website handoff | More → Production tasks/people | Native assigned work exists; staff create/edit, roster and casting management missing |
| Readiness/production notices | Website handoff | More → Readiness & notices | Native staff read/action summaries in next phase |
| Playbill editing | Website handoff | More → Playbills | Keep editing on web; optional native preview/QR links later |
| Operations, permissions, safeguards | Website handoff for authorized staff | More → Administration | No broad native administration until audited APIs; do not expose unauthorized links |
| Commerce, promo codes, Box Office | Website admin handoff only | More → Administration → Commerce & Box Office | No native financial transaction implementation now |
| **Ticket purchase** | **Ludus (current live vendor)** | More → Buy tickets, Theatre → Tickets | Preserve Ludus until explicit controlled transition; CTSMD `/tickets` remains pilot |
| Policies, resources, help | Native directory plus website | More → Resources/Policies, `/help` | Verify URLs, label external content and link accessibility |

## Native screen structure and UX goals

1. **Navigation clarity:** use the existing five-tab shell consistently; avoid introducing a second sidebar or elevating staff-only tools into every member's tabs. More organizes destinations by task rather than as a flat 20-item list.
2. **Compact and scannable:** fewer oversized cards; clear section headings, subdued help text, strong contrast, accessible 48dp minimum touch areas; meaningful empty/error/retry states.
3. **Shared identity, not duplicated RBAC:** the native role-controlled menu is convenience only. `MobileApi`, web authorization, production membership and safeguarding remain the actual access decision.
4. **Explicit browser handoffs:** differentiate native detail from website workflow; don't claim single sign-on. Keep the customer purchase link on Ludus until CTSMD commerce is actually approved.
5. **No false time conversions:** preserve backend venue-local schedule semantics; use CTSMD Eastern default for business context and avoid treating naive local strings as UTC.

## Next implementation sequence

**Phase A: Member visual parity and completeness (in progress).** Audit Home, Theatre detail, Community, Messages, assigned Forms, Volunteer, Action Center, Notifications, Family, and Videos at common phone widths. Apply shared design components and practical navigation/empty states. Recent source updates cover shared masthead, More grouping, web handoff allowlist and agenda presentation.

**Phase B: Caregiver and staff operational essentials.** Caregiver-only native schedule and limited navigation are implemented in source, and production detail offers permission-aware website shortcuts for Tasks, People, Production day and Readiness. Next add a native production staff overview, production selector, assigned tasks and production day read view before enabling mutations. Create/extend specific mobile APIs with role, production, lifecycle and family guards. Do not imitate staff permission with client-only checks.

**Phase C: Full lifecycle parity and high-value writing actions.** Evaluate native staff attendance, schedule acknowledgment, task assignment, volunteer lead workflow, family/pickup management and structured forms only after server authorization tests and user acceptance. Complex editing and financial products can stay web-first.

**Phase D: Device acceptance and release.** Confirm iOS/Android build, Expo package compatibility, dynamic type, VoiceOver/TalkBack, real photo/video upload, signed-in and denied roles, student/guardian/caregiver household boundaries, cold starts, token revocation, OS notifications, offline privacy and browser handoffs. Retain no-go until device and hosted backend evidence is recorded.

## Verification and limitations

The caregiver-only `/api/mobile/me` response now provides a `caregiver_only` UI hint, derived from active caregiver relationships and independent participant/staff classification; it does **not** replace the backend route gate. Home uses a distinct minimal logistics view and Theatre/Community tabs are hidden for this restricted account. The existing API returns only first names and call logistics, not student IDs/last names. This still requires denied-role and relationship-revocation device acceptance.

The updated route/module sources are tracked in `mobile/tests/integration.cjs`, `mobile/README.md` and CI's `CTSMD Expo mobile checks` workflow. Expo dependency compatibility currently reports SDK 58 patch drift (see its CI log): the TypeScript and bundle results must not be represented as an entirely green workflow until `npx expo install --check` passes on a coherent lockfile. Existing [native Phase 4 evidence](NATIVE_PHASE4_ACCEPTANCE.md) remains dated, and physical-device acceptance is still outstanding.

**No native store purchase, web-surface reimplementation, live-storefront switch, hosted deployment or new permission grants were performed in this reconciliation.**
