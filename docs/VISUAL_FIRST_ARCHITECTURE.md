# Visual-First Architecture

## Build intent

CTSMD Connect uses a visual-first process, but visual-first does not mean disposable implementation. Screens should be fast to build and easy to change while still consuming realistic data through stable read boundaries.

The governing principle remains:

> Experience defines the product. Background logic exists to support the approved experience.

## Current technical shape

- PHP 8.x
- Apache + `.htaccess`
- One front controller (`index.php`)
- MySQL/MariaDB-backed seeded prototype records
- PDO-based database access
- Prototype read repository (`src/PrototypeDataRepository.php`)
- Server-rendered HTML
- CSS variables and responsive component styles
- Minimal vanilla JavaScript
- No framework dependency

This intentionally keeps local development MAMP-friendly and future deployment compatible with typical shared-hosting PHP environments.

## Demo data policy

Prototype data must be seeded, not hardcoded.

Anything that represents a record or system state belongs in the database and versioned seed files. Examples include users, productions, schedules, announcements, posts, conversations, messages, forms, volunteer credentials, shifts, signups, Playbills, statuses, and operational counts derived from those records.

Views may hardcode stable interface language such as headings, button labels, help text, empty-state copy, and safety explanations. They may not embed fictional application records simply to make a screen look populated.

The prototype should fail clearly when its seed data is unavailable rather than silently swapping in a second hardcoded fixture source.

Current local sources:

```text
database/schema.sql
database/seeds/001_demo.sql
src/Database.php
src/PrototypeDataRepository.php
```

`src/mock-data.php` remains temporarily as a compatibility entry point for Build 001, but it contains no demo records. It opens the database and delegates to the repository.

## Visual-first data workflow

When a new visual workflow needs representative content:

1. Design the experience and identify the records the screen needs.
2. Reuse existing domain tables where appropriate.
3. Add only the smallest new schema required to support the approved experience.
4. Add realistic local records to a versioned seed.
5. Expose those records through a repository/read model.
6. Render the screen from that read model.
7. Keep write logic mocked or disabled until the interaction itself is approved.

This gives us realistic, relational demo behavior without prematurely building the full production service layer.

## Planned evolution

### Phase 1: visual contract with seeded reads

Build screens and validate:

- role-specific information density
- member vs staff navigation
- mobile vs desktop priorities
- safeguarded messaging presentation
- volunteer eligibility states
- schedule hierarchy
- admin workflows
- accessibility patterns

Seeded relational records support the screens, but production-grade write workflows are intentionally deferred.

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

Expand database tables and services only as required by approved screens. The likely order is:

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

The Build 001 schema establishes only the subset needed to seed the current visual experiences. It is not permission to prematurely fill in every future entity.

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

The seeded safeguarded conversation in Build 001 demonstrates the intended record shape only. It does not yet constitute server-side enforcement.

## Volunteer eligibility boundary

Shift signup should use requirement rules rather than hard-coded role names.

Build 001 already seeds volunteer requirements, volunteer credentials, shift requirements, and signups so the prototype read model can determine whether the demo user is eligible for each shift.

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
