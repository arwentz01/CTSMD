# CTSMD Connect mobile V1 follow-ups

## Calendar navigation (deferred during Community build)
- [ ] Tapping any calendar item on Home, Theatre, or a production page opens the **specific event detail**, not the Theatre landing page or production overview.
- [ ] Event detail shows title, date/time, venue, notes, production context, and available member actions, using authorized backend data.
- [ ] **View calendar** opens a true complete-calendar view, not Theatre. Preserve production and family visibility restrictions.
- [ ] Do not confuse an optional visible event with a confirmed personal commitment.

## Community in progress
- [x] Feed / Channels switch and production filter.
- [x] Feed read-state isolation.
- [x] Channel-level Include in Community Feed setting (migration 071; deploy migration before PHP code).
- [x] Initial native post detail route and moderated replies (migration 072; not yet tested or deployed).
- [x] Initial native single-photo selection and multipart upload via existing CTSMD storage (caption required; not tested).
- [ ] Authenticated inline photo retrieval and galleries; reply moderation review and post pagination.
- [ ] Install SDK 58 image picker and regenerate mobile lockfile (`cd mobile && npx expo install expo-image-picker`), then test Android/iOS uploads.
- [ ] Full Expo/Android and PHP lint/test pass.

## Release note
The branch is not deployed or device-verified. Run migrations 071 and 072 before deploying code reading channels.include_in_feed or channel_post_replies; verify historic announcements, explicit opt-in for discussion channels, channel ACLs, and no unintended read-state changes.
