# Manual production participants

Manual participants represent cast, crew, creative, or other production people who do not have CTSMD Connect accounts. They are deliberately separate from `users`, authentication roles, family relationships, production memberships, messaging, forms, and notification recipients.

## Data model

- `manual_participants` stores the reusable person profile, private staff notes, biography, and secured headshot reference.
- `manual_participant_credits` stores production-specific role, type, casting state, order, publication approvals, and transfer history.
- `playbill_manual_profiles` is the production Playbill snapshot. Public name, biography, and headshot each retain an explicit approval boundary.

One profile can have credits in multiple productions. Removing a credit from an active listing does not erase its audit history or an already-published edition snapshot.

## Staff workflow

Production staff add and edit manual participants from Casting. Manual records are clearly labelled and cannot receive family messages or be finalized into an authenticated roster. Approved names can enter callback/cast publication snapshots, production directories, and mobile responses through the same publication data used by registered participants.

If the person later registers as a Student, staff may transfer selected name, biography, headshot, and production-credit fields to that existing active Student. The operation never creates an account, role, guardian relationship, or membership. The transferred manual credit is retired from active listings and retained for audit.

## Publication rules

- Private notes are never selected by public or mobile publication queries.
- A manual name must be approved before the record appears publicly.
- Biography and headshot approvals are independent; unapproved fields are returned as `NULL`.
- Archiving or transferring a manual credit removes it from live public queries without deleting historical records.
- Completed and archived productions remain protected by the existing production lifecycle write guard.
