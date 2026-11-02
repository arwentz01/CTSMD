# CTSMD Connect Web Push

CTSMD Connect uses standards-based Web Push for installed iPhone/iPad and Android web apps. Push is an additional delivery channel; in-app notification state remains canonical.

## Database

Apply in order:

1. `database/migrations/039_web_push_notifications.sql`
2. `database/migrations/040_push_event_cursors.sql`

Migration 040 baselines existing Messages, Community posts and app notifications so enabling push does not replay historical activity.

## Generate VAPID keys

Run once from the project root:

```bash
php bin/generate-vapid-keys.php
```

Copy the three generated values into the runtime `.env`:

```env
PUSH_VAPID_PUBLIC_KEY=...
PUSH_VAPID_PRIVATE_KEY_B64=...
PUSH_VAPID_SUBJECT=mailto:notifications@ctsmd.org
```

Do not commit real private VAPID key material.

## Requirements

The PHP runtime used by the queue worker needs OpenSSL and cURL enabled.

A real phone/tablet needs to reach CTSMD Connect over HTTPS. A phone cannot use the Mac's `localhost`; use the deployed HTTPS site or an HTTPS development endpoint reachable from the device.

## Register a device

### iPhone / iPad

1. Open Connect in Safari.
2. Use Share → Add to Home Screen.
3. Launch Connect from its Home Screen icon.
4. Open Notification preferences → Mobile notifications.
5. Tap Enable notifications and approve the system prompt.

### Android

1. Open Connect in Chrome and visit Mobile notifications once so the web-app service worker is registered.
2. Use Chrome's Install app / Add to Home screen action.
3. Launch the installed Connect app.
4. Open Notification preferences → Mobile notifications.
5. Tap Enable notifications and approve the system prompt.

## Deliver queued push notifications

For a local test:

```bash
php bin/process-push-queue.php
```

The processor first bridges new CTSMD activity into `push_queue`, then delivers queued items to active device subscriptions.

For deployment, schedule this command with the hosting cron facility at the shortest practical interval. Push delivery latency is bounded by that interval.

## Sources currently bridged

- New direct/safeguarded Messages → all other conversation participants.
- New published Community posts → users who are currently authorized to read that Channel.
- New `app_notifications` → category inferred from the destination path.

Message push content deliberately avoids the message body. The lock-screen notification identifies the sender and conversation subject, then deep-links into the authenticated conversation.

Community push content identifies the Channel and author without exposing the post body.

## Test

1. Register a phone from `/push-settings`.
2. Use **Queue a test notification**.
3. Run `php bin/process-push-queue.php`.
4. Confirm the system notification arrives while Connect is backgrounded/closed.
5. Tap it and verify `/notifications` opens.
6. Send the user a Message from another account, run the processor, and verify the notification opens the correct thread.
7. Publish a Community post in an authorized Channel, run the processor, and verify the notification opens that Channel.
8. Verify a user without Channel access receives no Community push.

## Future native apps

The application queues CTSMD notification intent independently from browser subscriptions. Future native clients can add APNs and FCM device-token adapters behind the same notification domain without changing the member-facing event model.

## Pilot acceptance: OS presentation and authorized deep links (October 2026)

**Status: NOT YET VERIFIED on device.** FCM HTTP 201 is transport-provider acceptance only.
Do not enable unrestricted production queue processing for these tests. Use the existing
isolated fake-account queue workflow described in BOARD_PILOT_READINESS.md.
Leave the seven pre-existing pending email rows untouched.

1. Deploy `service-worker.js` from the pilot branch; confirm the served file matches that commit.
   Open the browser's service-worker panel, update the worker, and verify it is **active**
   under the CTSMD origin. A file existing on disk is not proof an older worker stopped controlling the app.
2. Use a supported device, a dedicated test browser profile, and a disposable fake
   CTSMD account with known permissions. Record device OS/browser versions and the
   exact timestamp. Enable site/system notification permission and subscribe.
3. From `/push-settings`, use the browser notification test and the isolated queued
   push test. Verify an OS-visible banner or Notification Center entry in the
   background, then when the application is not in the foreground. Record separate
   results for foreground, background, and app/browser closed states.
4. Tap an isolated notification pointing to `/notifications`. Verify the exact
   CTSMD destination opens with authentication enforced. If signed out, the login
   flow must not leak private content.
5. Send a fake-account Message to an authorized recipient. Process only the
   isolated push item and tap it: verify the correct thread opens. Repeat for an
   authorized Community channel.
6. Remove the recipient's access **before delivery** and confirm no push to
   that recipient. Also remove access **after an already-delivered notification**:
   tapping the old notification must not expose the revoked conversation or channel.
7. Revoke notification permission or unsubscribe; verify subsequent push delivery is
   suppressed and no new subscription remains active for that device.
8. Record OS-displayed evidence, destination, pass/fail, and failure logs without
   message bodies, tokens, private child information, or real-user identifiers.
   Clean up every fake user, subscription, isolated queue row and associated state.
9. Run `node tests/web_push_click.js` locally to verify notification-click routing.
   This checks navigation mechanics; it does **not** replace real-device presentation
   or authorization acceptance.

**Stop condition:** Any unexpected unauthorized content, unexpected real-user
notification, or ambiguous active service-worker version stops the acceptance run.
Native Floot/APNs/FCM delivery is a separate unproven track and must not be signed off
on the basis of browser Web Push.

## Additional delivery-time safety checks (repository changes, pending deployment)

- Queue processor suppresses Message-category items unless their destination is an expected
  `/messages/thread?id=N` path **and** the recipient retains live conversation access.
- Queue processor suppresses Community-category items unless their destination is an expected
  `/channels/view?id=N` path **and** the recipient retains effective channel access.
- Service-worker subscription rotation no longer silently registers an unassociated endpoint.
  The notification-settings client displays a refresh prompt if an open CTSMD tab receives
  a subscription-change signal. A closed browser may require the user to revisit settings.

Acceptance: execute the normal isolated queue fixture, add one malformed-message and one
malformed-community action-path case, prove both are suppressed without sending, and verify
valid authorized destinations still deliver. Record a before/after fingerprint of existing
queue items; do not run the queue processor against unrelated production rows.

### Isolated PHP regression (no outbound pushes)

Run `php tests/pilot_push_destination_guard.php`. The PDO test double permits
account and preference lookups but deliberately rejects unexpected SQL, avoiding
any real database or network connection. Four malformed Message/Community paths
must be suppressed before subscription or delivery lookup. This is a code-level
regression check, not evidence of OS notification delivery or live authorization.
