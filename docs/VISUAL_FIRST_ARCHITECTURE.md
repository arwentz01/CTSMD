# Visual-First Architecture

## Build 001 intent

Build 001 is a functional visual prototype, not a production backend. It proves the navigation, responsive behavior, information hierarchy, safety language, and operational workflows before database and API decisions become expensive to change.

## Current technical shape

- PHP 8.x
- Apache + `.htaccess`
- One front controller (`index.php`)
- Shared mocked theatre data (`src/mock-data.php`)
- Server-rendered HTML
- CSS variables and responsive component styles
- Minimal vanilla JavaScript
- No framework dependency
- No required database connection for Build 001

This intentionally keeps local development MAMP-friendly and future deployment compatible with typical shared-hosting PHP environments.

## Planned evolution

### Phase 1: visual contract

Build screens with mocked data. Validate:

- role-specific information density
- member vs staff navigation
- mobile vs desktop priorities
- safeguarded messaging presentation
- volunteer eligibility states
- schedule hierarchy
- admin workflows
- accessibility patterns

### Phase 2: thin application foundation

After visual approval, extract the prototype into clearer application layers:

```text
public/index.php
src/
  Http/
  Domain/
  Application/
  Infrastructure/
views/
config/
database/
storage/
```

Keep routing and dependency management lightweight. Do not introduce a heavy framework unless an actual requirement justifies it.

### Phase 3: persistence by approved workflow

Add database tables and services only as required by approved screens. The likely order is:

1. users, profiles, roles, memberships
2. students, guardians, guardian/student relationships
3. productions/groups and memberships
4. channels, posts, memberships
5. conversations and safeguarded participant enforcement
6. volunteer profiles, requirements, credentials, training
7. shifts, eligibility, signups, hours/check-ins
8. schedules/events/attendance/absence
9. forms/acknowledgments
10. Playbills/assets/sponsors
11. notifications, audit logs, reports, settings

## Safeguarded messaging boundary

The future conversation creation flow must work like this:

1. The client submits the intended people or context for a conversation.
2. The server loads each participant’s effective organization roles.
3. The server determines whether any student/adult relationship exists in the proposed conversation.
4. For each student/adult pairing, the server loads required approved guardian relationships.
5. The server constructs the final participant set.
6. The server rejects any invalid state where required guardians cannot be included.
7. The server stores the conversation and final participant list atomically.
8. Removal/change operations repeat the same invariant checks.

The client must never be considered authoritative for the final safeguarded participant list.

## Volunteer eligibility boundary

Shift signup should eventually use requirement rules rather than hard-coded role names.

A future eligibility evaluator should compare:

- shift requirements
- volunteer credentials
- training completion
- expiration dates
- organization status
- production/group membership where applicable
- age/adult-only restrictions where applicable
- manual restriction flags

The result should explain *why* a shift is open or locked. Admin overrides must require a reason and generate an audit record.

## Authorization

Future authorization must be server-side and context-aware.

A user's role alone may not be sufficient. Access may also depend on:

- organization membership
- production/group membership
- guardian/student relationship
- staff assignment
- safeguarding role
- record sensitivity
- ownership/assignment of a workflow

UI hiding is convenience, not authorization.

## Security path after visual approval

Planned baseline:

- password hashing using PHP password APIs
- secure sessions and session rotation
- CSRF protection
- output escaping
- prepared database statements
- centralized authorization checks
- file-upload validation and storage boundary
- privacy-aware notifications
- soft deactivation for accounts/sensitive records
- auditable admin actions
- least-privilege access for safeguarding/incident data

## Responsive contract

### Member/community

Primary target: phone.

- Today/dashboard
- channels
- messages
- schedule
- forms
- volunteer signup
- Playbills
- notifications

### Staff/admin

Primary target: desktop, with tablet/mobile fallback.

- people/families
- compliance review
- shift management
- schedule building
- channel/admin management
- reporting
- safeguarding review
- audit tools

Do not compress every desktop workflow into tiny cards just to claim it is mobile-first. Mobile admin views may reorder, collapse, simplify, or defer secondary information while preserving essential actions.

## Hosting evolution

### Initial

- MAMP local development
- Bluehost/shared-hosting compatible PHP + MySQL/MariaDB
- standard request/response model
- polling for near-real-time areas if required

### Later

The application should be movable to VPS/cloud hosting without rewriting domain logic. Real-time delivery, background jobs, push notifications, object storage, and richer APIs can be added behind stable application boundaries.

## Mobile application path

A future Flutter app should consume an API that follows the same domain rules as the web application. The web prototype is therefore a UX reference, not a separate source of business truth.
