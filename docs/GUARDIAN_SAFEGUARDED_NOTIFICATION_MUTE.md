# Guardian mute for safeguarded conversation push alerts

**Feature:** Parents and guardians may mute optional push alerts for safeguarded conversations globally under `/notification-preferences`. This does **not** mute other Direct Message alerts unless the general messages switch is also off.

**Persistence:** Existing `notification_preferences` table gains `push_safeguarded_messages TINYINT(1) NOT NULL DEFAULT 1` via `database/migrations/089_guardian_safeguarded_notifications.sql`. No new data table is introduced. New preferences default to alerts on; accounts without a preference row retain that behavior.

**Eligibility:** Show the switch when a signed-in user has an active parent/guardian relationship. At delivery, only messages in `conversation_type='safeguarded'` in which this user has a *non-revoked `conversation_participants.participant_role='guardian'`* may be muted. A staff participant remains subject to regular message push settings even if the same account has a child elsewhere.

**Coverage:** Web-push and the shared push event queue (used by subscribed devices), including notification-derived push events pointing to a conversation. The queue worker rechecks preferences immediately before dispatch so preexisting queued alerts are suppressed. No new email notification behavior is added because conversation notifications currently use push. In-app message history and unread badges remain unchanged.

**Not affected:** Guardian participation requirements, safeguarding staff access, former-guardian revocation, message persistence, staff oversight, read state, conversation contents, system/account security notifications, or other notification categories. Turning off a push alert must never remove the guardian from the conversation.

**Discovery:** `/notification-preferences` has a guardian-only labeled checkbox. Safeguarded conversation threads link guardians to the settings page. An unchecked box is saved as disabled via the existing CSRF-protected preferences action.

## Required QA

1. Apply migration 089 and confirm existing preferences default to enabled, including accounts without a preferences row.
2. Guardian opts out, receives safeguarded message: conversation and unread count update; **no push alert** queued.
3. Guardian receives ordinary direct message: push unaffected if `push_messages` remains enabled.
4. Guardian opts back in: subsequent safeguarded messages queue push again.
5. Staff participant who also has a child relationship: guardian mute does not suppress staff-role safeguarded alerts.
6. Guardian is removed/revoked: existing conversation access revocation remains enforced; push is suppressed regardless of preference.
7. Queue a safeguarded alert while enabled, mute before sending: delivery worker suppresses queued push.
8. Verify notification-derived push for `/messages/thread?id=...` uses the same preference.
9. Confirm session CSRF and guardian-only option, plus keyboard/screen reader/mobile presentation.
10. Run PHP lint, migration validation and database-backed push-delivery tests before deployment.

**Status:** Changes committed; no PHP runtime, migrated database or live push delivery has been verified in this GitHub-only build.
