# Safeguarding conversation oversight

## Purpose
Authorized safeguarding leads can locate a direct or safeguarded conversation by either participant, or by both participants together, without becoming a participant in the thread. Historical participants remain searchable to support investigations after an account or guardian relationship is removed.

## Routes
- `GET /safeguarding/conversations`: paginated conversation metadata, maximum 50 per page.
- `POST /safeguarding/conversations`: CSRF-protected request to begin a content review with a documented reason (minimum 12 characters) and optional safeguarding case ID.
- `GET /safeguarding/conversations?conversation_id=...`: read the current review, with 50 recent messages per page; pages are re-audited.
- `GET /safeguarding/conversations/attachment?... `: download an associated file within an active review; downloads are separately audited.

## Authorization and privacy boundaries
- This console requires the explicit `safeguarding.manage` permission from current auth-role permissions. Generic `isStaff` and the legacy permission fallback must not grant access.
- Reviewer identity and current permission are revalidated on each request, including attachment download.
- Content requires a reason, stored in the authenticated PHP session rather than the URL. Review authorization expires after 15 minutes and is tied to one reviewer and one conversation.
- Reviews are **read-only**: no conversation participation changes, last-read changes, or outgoing messages.
- An audit event is written before retrieving message bodies or releasing an attachment. If the audit write fails, the body or attachment must not be returned.
- The timeline includes hidden historical messages for case evidence; it never changes historical retention or participant access.
- Messages page now includes an explicit notice that safeguarding personnel may review reported conversations. Native mobile notice and the organization's participant/parent policy should be aligned before launch.
- Audit metadata records actor, conversation, case link if supplied, reason, page, and timestamp. Review reasons may contain sensitive details; audit access must remain appropriately restricted.

## Search
- Participant 1 and participant 2 filters use independent SQL EXISTS conditions, so two distinct people can be required in the same conversation.
- Each filter accepts name/email text, or an exact numeric user ID. Names are a convenience, IDs are safer for common names.
- Results include participant names, conversation type, last message timestamp and message counts, but no message body excerpts.
- Search results and message history are paginated. No unrestricted array of every conversation is loaded into PHP.

## QA / staging release gate
- Permission test: a production staff member without `safeguarding.manage` gets 403, including direct URL and attachment paths.
- Revocation test: removing the permission immediately blocks the console and previously authorized sessions.
- Reason and CSRF tests: empty/short reason, invalid case ID, mismatched CSRF and an expired 15-minute review cannot disclose content.
- Audit outage test: simulate an audit insert failure and confirm no text or file content is returned.
- Safeguarding case test: filter a reported conversation by one or two participants and optionally attach the case ID to the audit event.
- Historic conversation test: review an old safeguarded thread with a revoked former guardian without regranting future messaging access.
- Paging and scale test: at least 5,000 conversations, long message histories and large participant datasets.
- Verify text escaping, attachments, mobile-browser layout, and that the console does not update read receipts.
- Run PHP lint, targeted tests and database-backed integration tests. No staging verification or CI run has been completed from this connection.

## Future enhancements
- Direct attachment evidence preservation and downloadable case bundles with hashes/chain of custody.
- Automatic link from user-submitted abuse reports into a review case and precisely scoped message context.
- Reviewer workload assignment, case-specific access and dual-review for particularly sensitive requests.
