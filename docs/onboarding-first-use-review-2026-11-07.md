# CTSMD Connect — First-use onboarding review (2026-11-07)

Branch: `audit/board-pilot-readiness`. This is a source-code review and implementation backlog, **not** a signed-off browser/device acceptance test. No production changes or deployments were made as part of this review.

## Shared first-use principles

1. **Account activated** means the person can sign in. It does not imply general CTSMD membership approval.
2. **CTSMD membership approved** is separate from **production assignment**. An approved parent or student can still have no assigned show.
3. Child profiles are connected to parent/guardian accounts; an optional student email enables an invitation to activate the child's **existing** profile.
4. A participant's options must follow current role, family relationship, age, production access, and safeguarding restrictions.
5. A saved casting decision is not necessarily a communicated result; a saved schedule edit is not necessarily a published update.

## Verified in repository (not device acceptance)

| Journey | Existing surfaces | Important first-use message |
| --- | --- | --- |
| Parent signup | `/join`, `/verify-email`, `/onboarding`, `/family/manage` | Verify email, then set up household; CTSMD membership approval is separate |
| Family follow-up | `/app`, `/family-hub`, `/forms` | Show what is pending, then direct families to the next relevant task |
| Student invitation | `/student/activate` with private invitation token | Student sets a password for an existing managed profile; expired links require help from family/staff |
| Staff workspace | `/production`, Working Production selector, `/production/casting`, `/attendance` | Choose the correct show, then find the appropriate workspace and action |
| Account decisions | `/admin/accounts#pending-membership-reviews` | Only staff with `accounts.manage` should act |
| Moderation | `/admin/moderation/queue`, `/admin/moderation/terms` | Human review is still required; safety escalation follows a separate process |
| Help | `/help`, native `/help` handoff | Searchable task guides are permission-filtered; native help opens web instructions |

## Highest-priority follow-up work

### P0 — Before wider rollout
- **End-to-end onboarding acceptance:** Test a completely new parent, a student activated through an invitation, and a new staff account with only the intended permissions. Verify redirects, email arrival, expired invitations, missing shows, and denied/pending membership states.
- **Staff access handoff:** Verify who grants staff permissions and production assignments, how a staff member knows access is ready, and what they should do when an expected tool is missing. Avoid suggesting that all staff have administrator rights.
- **One clear first action:** On each person's first sign-in, confirm the current Home/Family screen answers "What should I do now?" and distinguishes an empty show from a loading or permission error.
- **Safety checks:** Test a safeguarded student's messaging and Community visibility using real guardian relationships. Never use another account as a workaround.
- **Guide accuracy:** Walk every Help article using the exact current page labels. Verify save/publish/send boundaries and deep links.

### P1 — Make first-use easier
- Add an optional, dismissible **Getting started** checklist to Home that reflects real progress (verify email, complete household, approve membership, assign production), rather than a fake percentage.
- Offer **role-specific introductions** for first-time staff, parents and students that can be reopened from Help. No forced tour on every login.
- Improve empty states: no children, no shows assigned, no forms, no available channels, and no volunteer shifts should explain *why* the page is empty and one safe next action.
- Make the Student invitation and Parent account screens explicitly explain "safeguarded" with clear language about guardian involvement.
- Provide contextual "How do I...?" links from the main workspaces to their specific Help guide, particularly Casting, Attendance, Accounts, and Moderation.

### P2 — Reduce ongoing support burden
- Add a short **contact CTSMD staff** path for missing production assignments, invalid invitations and incorrect household relationships. Avoid exposing personal staff contact details without authorization.
- Maintain guide content as part of feature acceptance: any UI action name or workflow change should trigger a Help article review.
- Evaluate optional illustrated walkthroughs and screenshots after UI layout settles; screenshots must be sanitized of real member details.
- Collect non-sensitive feedback on whether Help articles resolve the issue, without exposing confidential student/account information.

## First-use acceptance matrix

| Persona | Must complete | Should not see |
| --- | --- | --- |
| New parent, pending membership | Verify, sign in, add 0/1/many children, understand pending state, return later | General membership content presented as already approved |
| Approved parent with assigned child | Family schedule, relevant forms, casting offer response where eligible, appropriate safeguarded conversations | Unrelated families' information |
| Invited student | Activate existing profile, log in, find authorized shows, forms and channels | Student-to-student private messages or staff-only tools |
| Production staff | Select show, schedule, attendance, casting responsibilities allowed by role | Administrator functions beyond role |
| Account reviewer | Find pending queue, review approval/denial, see shared pending count clear | Approval actions for unauthorized roles |
| Moderator | Open pending post queue, manage terms if authorized, escalate safety concerns | Private safeguarding report details outside granted scope |

## Documentation and release status

Three new Getting started guides were added to `src/HelpGuideCatalog.php`: first day for staff, first steps for parents, and student account activation. Both native and public What's New **Next App Update** previews were updated. These are previews, not store release approvals.

No browser, physical-device, accessibility, email deliverability or production-db migration checks were executed in this session. Each remains outstanding before a release sign-off.
