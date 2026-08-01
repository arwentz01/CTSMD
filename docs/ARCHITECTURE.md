# Architecture notes

## Build 001 shape

CTSMD Connect begins as a modular monolith: one PHP deployment and one MySQL/MariaDB database, divided into clear application boundaries. This is the simplest fit for shared hosting and remains portable to a VPS or container later.

```text
Browser / future Flutter app
            |
      Web or /api/v1
            |
   HTTP controllers + middleware
            |
 Application services and policies
            |
 Repositories + MySQL transactions
            |
 Audit log / notification outbox
```

Build 001 implements only the web entry point, router, view layer, and configuration seam. Future code should be grouped by domain (`Identity`, `Families`, `Channels`, `Messaging`, `Safeguarding`, `Audit`, `Notifications`) rather than by database table.

## Request lifecycle

Apache rewrites non-file requests to `public/index.php`. The front controller loads environment configuration, registers application routes, and emits an HTML or JSON response. Future middleware will initialize secure sessions, verify CSRF tokens for browser mutations, authenticate the account, and authorize each action.

Only `public/` should be web-accessible. Configuration, SQL, source, documentation, and logs must remain outside the document root.

## Safeguarded conversation invariant

The application must never accept a client-supplied participant list as final. Conversation creation will use a transaction and a single server-side service:

1. Lock and load the requested active organization members and their roles.
2. Reject student-to-student direct conversations.
3. If the pair is adult-to-adult, create a normal direct conversation.
4. If one participant is a student, load **all active, approved guardian relationships** for that student.
5. Reject creation if the student has no approved active guardian.
6. Create a safeguarded conversation and insert the student, requested adult, and calculated guardians. Guardian rows are marked `is_required = 1`.
7. Write an audit event and notification outbox records in the same transaction.

Participant removal follows the same locked policy evaluation. A required guardian cannot be removed while their student remains. The schema adds useful constraints, but MySQL cannot express this entire cross-table invariant declaratively; the application transaction is the enforcement boundary. All messaging writes must go through this service—never a generic participant repository exposed directly to controllers.

## Roles and identity

Accounts and organization membership are separate. A person can belong to more than one organization in the future. Roles attach to memberships, and a membership may have several roles. `users.is_student` is an identity classification used by safety policy; roles alone do not determine whether someone is a minor.

Invitations use a hashed token, expiration, intended email, and organization. Plain invitation tokens must never be stored. Passwords use PHP `password_hash()` and `password_verify()` with the current recommended algorithm.

## Web and API boundaries

- Server-rendered browser routes remain under `/` and `/admin`.
- Future mobile-compatible endpoints live under `/api/v1`.
- JSON endpoints return stable status codes and an envelope with `data`, `error`, and optional `meta`.
- Controllers translate HTTP only. Authorization and safety decisions belong to application policies/services.
- Polling uses `updated_after` or cursor pagination; clients do not need WebSockets.

## Security baseline for Build 002+

- Secure, HTTP-only, SameSite session cookies; rotate session IDs after login
- CSRF tokens for all state-changing browser forms
- Prepared statements only; output escaped by default
- Authorization on every object read and mutation, including channel visibility
- Login and invitation rate limiting
- Generic authentication errors to prevent account discovery
- Soft deletion for messages and sensitive domain records
- Append-only audit events with actor, target, request correlation ID, and safe metadata
- Content reports retained for safeguarding review
- Production errors logged privately; no stack traces returned to users

## Notifications

Domain actions create `notifications` records transactionally. A scheduled PHP task can process pending records and later hand them to email or Firebase Cloud Messaging adapters. This outbox-style seam avoids requiring persistent workers or WebSockets on shared hosting.

## Scaling path

The application can first move unchanged to a VPS. Later steps may separate background notification processing, add Redis for sessions/rate limiting, store uploads in object storage, and add read replicas. Clear service boundaries and stateless HTTP handling prevent a full rewrite.

