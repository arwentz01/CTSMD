# CTSMD Connect: guarded automatic email delivery

## Scope
The CLI email worker supports `--auto` and requires `MAIL_AUTO_MIN_ID` as a positive integer. The worker will only claim queued jobs whose `email_queue.id >= MAIL_AUTO_MIN_ID`. Stale sending-job recovery uses the same cutoff. Earlier jobs remain untouched.

**This is a deployment-ready configuration procedure, not proof that the hosting cron is activated.** No production queue records should be edited or bulk-processed merely to enable new delivery.

## Before activation
1. Deploy the updated `src/MailService.php`, `bin/process-email-queue.php`, and the student-invitation mail eligibility fix to staging, then production after acceptance.
2. Inspect the live `email_queue` table and the seven previously deferred rows. Record their IDs and current statuses. Confirm *every* message below the proposed cutoff is explicitly deferred and *every* eligible message at or above it is approved to send. Do not blindly use ID 8 without this inspection.
3. Choose `MAIL_AUTO_MIN_ID` as one more than the greatest ID requiring quarantine. This is an **inclusive** minimum, so a message with that ID is eligible. If there are later historic messages that also need quarantine, use a greater cutoff; those messages will stay untouched.
4. Verify the SMTP/mail transport, APP_URL, and the dedicated account-security student email flow in an isolated environment. Test token expiration, replay, and suppressed recipients.
5. Ensure one scheduler owns the queue processing; use a host-level lock to avoid concurrent cron workers. The DB row transaction also prevents two workers from claiming the same queued row, but the lock reduces collisions.
6. Store any configurable environment variable in the host's protected environment, **not** committed application secrets.

## Scheduler example (Linux cron, every 5 minutes)
Replace absolute paths and approved cutoff based on the actual hosting configuration.

```cron
*/5 * * * * MAIL_AUTO_MIN_ID=<APPROVED_MIN_QUEUE_ID> /usr/bin/flock -n /tmp/ctsmd-email-auto.lock /usr/bin/php /ABSOLUTE/PATH/TO/CTSMD/bin/process-email-queue.php 25 --auto >> /ABSOLUTE/PRIVATE/LOG/PATH/ctsmd-email-worker.log 2>&1
```

The first automated run will **fail closed** if MAIL_AUTO_MIN_ID is absent, zero, or nonnumeric. The cutoff is not automatically recalculated because automatic shifting could strand valid recent invitations.

## Acceptance and rollback
- Confirm a newly queued student invitation is at or above the cutoff.
- Execute `MAIL_AUTO_MIN_ID=<APPROVED_MIN_QUEUE_ID> php bin/process-email-queue.php 25 --auto` once on an explicitly approved test queue. Verify a provider-accepted delivery, the audit/delivery log, and the student's actual inbox; provider acceptance alone is not inbox confirmation.
- Verify all intentionally quarantined queue rows retain their original IDs, statuses, attempts, and timestamps.
- Test repeat execution (no duplicate send), invalid cutoff (no send), disabled user, account_security for managed Student profiles, and SMTP failure/retry paths.
- Observe worker error output, failed/suppressed counts, and queue age during pilot.
- Rollback: remove/disable the scheduler entry. Do not delete old rows or rewind queue IDs.

## Important distinction
The Email Operations UI's existing **Process 25 now** action remains a legacy manual unrestricted operation; it does **not** use the auto cutoff. Do not click it while deferred messages remain queued. For an urgent individual invitation, CLI `--queue-id ID` targets only that message after explicitly approving its recipient and confirming the deployed worker.
