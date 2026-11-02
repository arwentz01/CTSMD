# CTSMD Connect Email Delivery

CTSMD Connect uses a durable database queue for outbound email. Web requests create `email_queue` records; a CLI worker performs delivery.

## Local MAMP

Keep:

```env
MAIL_DRIVER=log
```

Process queued messages manually:

```bash
php bin/process-email-queue.php
```

For an explicitly authorized acceptance row, process only that row without touching older queued mail:

```bash
php bin/process-email-queue.php --queue-id 123
```

The scoped mode limits both stale-claim recovery and queue selection to that ID and processes at most one row. The normal cron command remains unchanged.

Messages are written to:

```text
storage/logs/mail.log
```

Generate form, volunteer-shift and credential reminders manually:

```bash
php bin/queue-email-reminders.php
php bin/process-email-queue.php
```

## Production transport

Supported drivers:

- `log` — writes email to the CTSMD log and does not contact a mail server.
- `mail` — uses PHP `mail()` when the hosting environment has reliable outbound mail configured.
- `smtp` — connects to a configured SMTP server, with optional STARTTLS or SSL.

Recommended production configuration is authenticated SMTP where available.

Example:

```env
MAIL_DRIVER=smtp
MAIL_FROM_ADDRESS=no-reply@ctsmd.org
MAIL_FROM_NAME=CTSMD Connect
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

Never commit production SMTP credentials to Git.

## Bluehost cron

A verified Bluehost setup is:

```text
*/5 * * * * /usr/bin/flock -n /home1/wentzmdc/ctsmd-cron-locks/email-queue.lock /usr/local/bin/php /home1/wentzmdc/public_html/ctsmd/bin/process-email-queue.php 50 >> /home1/wentzmdc/ctsmd-cron-logs/email-queue.log 2>&1
7 * * * * /usr/bin/flock -n /home1/wentzmdc/ctsmd-cron-locks/email-reminders.lock /usr/local/bin/php /home1/wentzmdc/public_html/ctsmd/bin/queue-email-reminders.php >> /home1/wentzmdc/ctsmd-cron-logs/email-reminders.log 2>&1
```

The lock and log directories are outside `public_html` and use mode `0700`. As of October 21, 2026, both normal entries are installed in the account crontab as comments and therefore do not run. Production SMTP has host, port, TLS, sender, username and password values configured. Seven pre-existing due rows (`id` 1-7) remain pending and require an administrator disposition before normal processing is enabled. Do not run the unscoped worker or enable either normal cron line merely to test SMTP, and do not enable push scheduling as part of an email test.

## Production acceptance evidence

Production acceptance on October 21, 2026 used an explicitly authorized inbox and one isolated queue row (`id` 8):

- The optional `--queue-id` worker mode was first verified against a disposable local MySQL row using the log transport. The row was sent once, all pre-existing queue rows retained the same fingerprint, and the disposable row and delivery log were removed.
- The deployed worker and mail service matched the reviewed source hashes and both passed `php -l`.
- The production row was accepted by SMTP on its first attempt and recorded one `smtp` / `sent` delivery-log entry. The recipient confirmed receipt in Gmail.
- A repeat scoped invocation processed zero rows, confirming that the sent row was not duplicated.
- The seven pre-existing production rows retained the same before-and-after SHA-256 fingerprint (`93948691076080efb20c54b0a8cf4adcf28421da62efc39c1f9193ed23c3a398`).
- A temporary serialized cron entry, scoped only to the already-sent acceptance row, ran twice and processed zero rows both times. It was then removed. The normal queue and reminder cron entries remain commented.

The acceptance queue and delivery-log records are retained as audit evidence. Normal cron activation remains an operational decision because it would process the seven pre-existing live rows.

The queue worker automatically reclaims a message left in `sending` for more than ten minutes and retries failed deliveries up to three attempts with a delay between attempts.

## Security and preferences

Account-security mail (activation invitations and password resets) is transactional and is not disabled by ordinary notification preferences.

Members can manage non-security email at `/notification-preferences`.

Administrators can review queue/delivery state at `/admin/email`.

Queued messages support deduplication keys so scheduled reminder scans can be run repeatedly without sending the same reminder again.
