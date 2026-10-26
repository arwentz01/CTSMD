# CTSMD Connect — Master To-Do

**Current working branch:** `audit/board-pilot-readiness`  
**Updated:** October 26, 2026  
**Purpose:** One concise, actionable dashboard. This is the **day-to-day task list**; detailed history and specifications stay in linked documents.

## P0 — Before real-family pilot

- [ ] Verify safeguarding revocation across web/mobile, including former guardians, casting offers, student tasks and notices.
- [ ] Complete live email and push acceptance: actual delivery, deep links, retries, recipient authorization and queue operation.
- [ ] Validate HTTPS, secure cookies, secret-file protection, sensitive uploads and production hosting incident response.
- [ ] Assign backup/restore owners, retention, RPO/RTO and run a documented restore drill.
- [ ] Obtain policy-owner approval for privacy notice, terms/acceptable use, guardian consent and retention wording.
- [ ] Run staging regression suite and record real-device/mobile acceptance evidence. **Do not treat GitHub commits as deployment proof.**

## P1 — Finish and verify current workflows

- [ ] Deploy/apply pending migrations **079–084 as applicable** and verify live schema/version before updating code.
- [ ] Test casting roles, manual participants, callback/cast publication, offer accept/decline, and Playbill import.
- [ ] Test production Tasks, grouped assignment, student/guardian completion, owner roles and Volunteer Lead designation.
- [ ] Verify Attendance arrival-time corrections and late-pickup occurrence review/guardian notices, including no-send-before-approval.
- [ ] Finish native Expo staff-side parity for casting and Tasks; test native Action Center and permissions.
- [ ] Accessibility/responsive QA for desktop, mobile drawers, forms, attendance and keyboard navigation.
- [ ] Run link checks, performance baseline, media upload/optimization and server-side form validation audit.

## P2 — Public website / polish

- [ ] Audit public-only titles, descriptions, social previews, favicons and accessible image alternatives.
- [ ] Create approved public-only sitemap; verify robots/noindex handling on deployed host.
- [ ] Add branded safe 404 handling and check redirects.
- [ ] Decide whether any analytics or cookie consent is warranted after tracking/privacy inventory.
- [ ] Review public calls to action for Tickets, Volunteer, Join and digital Playbill.

## Pending specifications / deliberately deferred

- [ ] Define broader **Volunteer Lead** authority, training prerequisites and safeguards once CTSMD supplies the role specification.
- [ ] Review long-term acting résumé/history and additional Playbill publication enhancements after current workflows stabilize.

## Where the details live

- [Platform launch and release gates](PLATFORM_LAUNCH_READINESS.md)
- [Pilot acceptance and evidence](BOARD_PILOT_READINESS.md)
- [Product wishlist and history](TODO.md)
- [Documentation map](INDEX.md)
- [Native parity](../mobile/docs/CASTING_TASKS_PARITY.md)
- [Late pickup workflow](LATE_PICKUP_WORKFLOW.md)

**Maintenance rule:** Update this file whenever an actionable item is completed, blocked or added. Keep it short; move specifications, testing evidence and release notes to their dedicated documents. Do not mark an item complete without actual verification.
