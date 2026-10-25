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
- [x] Initial authenticated inline photo retrieval and small grids in Feed, Channels, and post detail (not device-tested).
- [x] Full-screen authenticated image viewer with previous/next controls (source implementation, not device verified).
- [x] Initial per-user mobile heart reactions with channel ACL checks (migration 073; not deployed or tested).
- [x] Expose and toggle shared heart reactions on web Community pages (source committed; not validated).
- [ ] Verify concurrent toggles, CSRF, read-only channels, and audit policy in migrated staging.
- [ ] Reply moderation review and post pagination.
- [ ] Verify photo tap inside Feed post card opens viewer without triggering parent post navigation.
- [ ] Install SDK 58 image picker and regenerate mobile lockfile (`cd mobile && npx expo install expo-image-picker`), then test Android/iOS uploads.
- [ ] Full Expo/Android and PHP lint/test pass.

## Release note
The branch is not deployed or device-verified. Run migrations 071, 072, and 073 before deploying code reading channels.include_in_feed or channel_post_replies; verify historic announcements, explicit opt-in for discussion channels, channel ACLs, and no unintended read-state changes.

## October 24 source functional review
- [x] Serialized web and mobile heart changes by locking the parent published post inside each transaction; confirm via concurrent database integration tests.
- [x] Volunteer action success triggers an immediate shift-list reload instead of instructing members to navigate away.
- [ ] Run mobile `npm run typecheck` and PHP lint after these commits; not performed in the connected GitHub source-edit environment.
- [ ] Validate My Family returned results against inactive relationships, blocked accounts, guardian revocation and student login.
- [ ] Verify Community image authorization on expired/revoked tokens and archived/private channels.
- [ ] Run `npm audit` and triage vulnerabilities without forced breaking upgrades.
- [ ] Full Android and iOS navigation/device acceptance checks remain required.

## Private media review (October 24)
- [x] Private mobile image responses use inline Content-Disposition rather than forced download.
- [x] Add cache-control, no-sniff, and Authorization variance response headers.
- [ ] Confirm revoked-token, archived-channel, removed audience, and inactive guardian cases return no image bytes using integration tests.
- [ ] Confirm CDN and proxy configuration respects no-store for authenticated media.

## October 24 former-guardian authorization hardening
- [x] Both mobile Community read-state policy and website Community policy now require an active family relationship with an active student enrolled in the same production for the `production_guardians` audience. A stale guardian production membership alone no longer suffices for that audience.
- [ ] Run PHP lint and transaction-backed tests for: active primary guardian, active secondary guardian, relationship removed, inactive student, student removed from production, guardian of a different production, and simultaneous guardian relationships.
- [ ] Review explicit selected-channel membership and broader `production_members`/`production_adults` audiences separately; these may independently grant access by design and must not be misrepresented as revoked by this change.
- [ ] Confirm no former guardian receives new student-related messages in safeguarded conversations; channel authorization does not replace message revocation enforcement.

## October 25 theatrical design system implementation
- [x] Expo screens updated with ivory/crimson/gold palette and shared editorial heading/header composition.
- [x] Five-tab bar now uses dark stage background, gold active selection and high-contrast inactive icons.
- [x] Home upcoming event hero, Messages conversation cards, Community feed cards, Theatre filters, and More profile/rows receive matching branded visual treatment.
- [ ] Import the official CTSMD logo as a native image asset. Current circular mask icon is a placeholder, not the actual supplied logo.
- [ ] Add optimized licensed/organization-owned production artwork and curtain/stage imagery to match illustrated mockups; no show images have been fabricated or uploaded to the app.
- [ ] Run npm typecheck, Expo launch and accessibility/contrast review on iOS and Android at small text and large font scaling.
- [ ] Keep safeguarding authorization and migration/deployment readiness separate from visual work.

## October 25 stage graphic pass
- [x] Added reusable pure React Native `StageBackdrop` curtain/fold/light motif, without remote image loading or new native dependencies.
- [x] Wired stage graphic into shared header, Home hero, Messages new-conversation CTA, and More profile hero.
- [x] Changed shared header icon to twin theatre-mask symbolism. This remains a placeholder until the official CTSMD brand logo is shipped as an approved asset.
- [ ] Validate clipping, overlay touch behavior, accessibility scaling, typecheck, and real Android/iOS rendering.
- [ ] Add organization-approved images and production artwork for a closer match to conceptual mockups.

## October 25 Android visual-feedback correction
- [x] Tightened shared app header, page title, and screen vertical spacing after reviewing Android screenshot.
- [x] Reduced Home hero height, event card padding and typography scale.
- [x] Increased contrast of pure-native curtain artwork; it remains illustrative rather than a real photographic asset.
- [ ] Confirm layout visually on Android after pulling; check text scaling, narrow devices and app tab bar.
- [ ] Integrate approved photographic stage image and authentic CTSMD logo when source assets are ready.
