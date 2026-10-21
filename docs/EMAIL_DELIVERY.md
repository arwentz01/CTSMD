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

The lock and log directories are outside `public_html` and use mode `0700`. As of October 21, 2026, both entries are installed in the account crontab as comments and therefore do not run. Production contains six queued non-test recipients, so the jobs must remain disabled until an approved recipient is supplied, a harmless fake-account message is queued, and receipt is observed. Enable only the two command lines above; do not enable push scheduling as part of the email test.

The queue worker automatically reclaims a message left in `sending` for more than ten minutes and retries failed deliveries up to three attempts with a delay between attempts.

## Security and preferences

Account-security mail (activation invitations and password resets) is transactional and is not disabled by ordinary notification preferences.

Members can manage non-security email at `/notification-preferences`.

Administrators can review queue/delivery state at `/admin/email`.

Queued messages support deduplication keys so scheduled reminder scans can be run repeatedly without sending the same reminder again.
