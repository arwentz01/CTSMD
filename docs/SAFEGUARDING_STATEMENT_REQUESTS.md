# Safeguarding case-linked statement requests

**Status:** Initial implementation committed on `audit/board-pilot-readiness`. **Not deployed or pilot-validated.** Apply migration 090 before using the new case controls. No existing safeguarding case data is deleted or overwritten.

## Concept and privacy boundary

A safeguarding reviewer can send a private, one-time statement form to a specific person. The recipient sees only a **snapshot** of:

- Case name (staff must check that the title itself is suitable for disclosure)
- Incident date/time, when recorded
- Production name, if associated with the case
- A manually written **brief description safe to share**

Recipients cannot access the case's internal factual summary, priority, statuses, other named participants, other responses, internal notes, or staff timelines. The recipient-facing description is stored separately as `safeguarding_cases.shared_incident_description`; an editable **per-invitation snapshot** is stored on each request, so subsequent changes to a case do not retroactively alter what each person saw.

The email subject/body contain no case title, incident details or other private case information; the message supplies a single-use secure link. The link itself is a sensitive bearer credential, not proof of the recipient's identity. Staff must carefully verify the destination email before sending, and use a guardian contact rather than sending a statement request directly to a child.

## Workflow

1. Safeguarding staff open a specific `/safeguarding/case?id=...`.
2. Under **Statements & Perspectives**, staff edit/save the brief shareable description and enter recipient name/email.
3. They review the **exact description** to appear in the invitation, select a 3/7/14-day expiry, and send a private email invitation. Sending queues email through existing `MailService`; it does not prove delivery.
4. The recipient opens `/incident-statement?token=...` without needing a CTSMD account. They see the limited contextual facts and respond to prompts about what happened, others present, and relevant follow-up.
5. They enter their name and attest that the account is their own, accurate to the best of their knowledge. On submission, the request becomes `submitted`, with server-side `submitted_at`, response text, and attested name.
6. The form cannot be resubmitted using that link. Staff see every response in a restricted **read-only history** on that case, along with invitation time, status and submission timestamp. An `audit_events` entry records receipt without copying statement content into the audit metadata.
7. Staff may revoke **pending** links. For corrections or further information, send a new request; do not overwrite the original submitted account.

## Technical implementation

- `database/migrations/090_safeguarding_statement_requests.sql`: adds the single `shared_incident_description` column to the existing case record and creates **one request table** for the required one-to-many invitation/response history, rather than creating a separate table per incident or person. The case-column DDL checks for prior existence.
- `src/SafeguardingStatementService.php`: staff permission gate, snapshots, random 256-bit token with SHA-256 token hash stored in database, expiry, revocation, one-submit transaction and audit.
- `src/SafeguardingStatementExperience.php`: token-scoped public form with no case lookup endpoints, separate CSRF check, no-cache/noindex/referrer restrictions and escaped output.
- `src/SafeguardingCaseExperience.php`: permission-gated staff invite and response management.
- `front.php`: public form route before normal authentication gate.

Statement responses are **not editable through the application**. A database administrator can still alter database records; this is not cryptographic or WORM immutability. Stronger evidence preservation (signed digests, append-only storage, independent evidence custody) should be scoped separately if CTSMD needs formal legal-grade evidentiary handling.

## Recommended follow-up features

- **Recipient preview & disclosure review:** make it easy to review exactly what the recipient will see before sending, including a neutral or anonymized case title if the internal one contains identifying details.
- **Guardian-specific delivery:** use relationship-aware recipient selection and verify that no invitation is sent directly to minors.
- **Reminders and status tracking:** see pending, expired, and returned requests; send manually triggered reminders with no incident details in email.
- **Attachments:** optional *only* after secure upload scanning, permission controls, audit and retention policy are implemented.
- **Supplemental statements:** issue a linked addendum request, never edit the original.
- **Retention and incident response:** define retention periods, confidential exports, legal hold, and emergency escalation outside this data collection form.
- **Bias reduction:** use neutral wording, avoid leading questions and avoid sharing others' statements before individuals respond.

## Mandatory verification before launch

- Run PHP lint on all modified/new classes and existing test suite; run new migration with MySQL/MariaDB.
- Send invite from an authorized safeguarding account; verify email queue, transport, link, expiry, one-time use and no duplicated email on retry.
- Test cases with and without production or incident timestamp.
- Verify all recipient-visible details are from the shared snapshot, not private case notes or internal case summary.
- Test expired/revoked/submitted links, token tampering and POST replay, especially concurrent duplicate submissions.
- Test HTML/script input is escaped in both the public form and internal read-only case history.
- Test unauthorized users cannot issue, revoke, or read requests or results.
- Confirm sensitive data does not appear in notifications, search indexing, public casts or APIs. Protect access logs that may contain URL tokens.
- Ensure emailing a guardian rather than a minor is the staff workflow and test accidental misaddressing procedures.
- Validate accessibility, mobile layouts, form abandonment/session expiry and production site HTTPS.
- Verify an invitation's saved context remains unchanged if the private case or its public-description draft is edited later.
- Preserve immutable responses during case status changes and across deployment.

**Pilot go/no-go:** Do not rely on this for actual incident investigations until migrations, live email, security boundary checks and browser validation pass.
