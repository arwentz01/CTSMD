# CTSMD member profile photos and messaging identity: rollout

Branch: `audit/board-pilot-readiness`. This feature is not deployed or submitted to app stores.

## Deploy dependency
Run `database/migrations/129_member_profile_photos.sql` before deploying the PHP code. The migration creates `member_profile_photos`, storing one current original-file reference and three private optimized avatar paths per user. Back up the database and private media storage first. The runtime still uses existing private `stored_files` and `STORAGE_PATH` settings.

## Implemented
- Website My Account: JPEG/PNG/WebP upload (6 MB maximum), circular crop preview, zoom and two-axis repositioning. JavaScript failure falls back to a center-cropped image; server validates real image content, dimension and pixel limits. Squares are stored as 512-, 96- and 40-pixel private JPEG renditions.
- Native My Account: the operating system's square editing/cropping picker and an authenticated multipart upload endpoint. The native cropper does not currently include the website's circular overlay.
- Website and native Community: current account avatars, initials fallback, and an inline Message shortcut only when contact is currently eligible. The shortcut uses the existing conversation composer, not a second message service.
- Website and native Messages: current account avatars in inbox, participants and messages; inbox only uses individual avatars where one non-guardian direct recipient can be identified.
- Recipient search: at least two search characters, name search only, a maximum of 25 visible selectable results, 300 ms client debounce, per-account request limiting, and a non-sensitive unique member ID to distinguish duplicate names.
- Server-side conversation creation still enforces student-to-student prohibition, staff-only student contact, required active guardians and safeguarded participant membership. The shortcut does not grant new conversation permissions.
- Image delivery: session-authenticated `GET /account/avatar?id=...&size=small|medium|large` or bearer-authenticated `GET /api/mobile/avatar`. Only image owners, authorized existing conversation participants, linked active parents/guardians and viewers of visible Community authors can load the rendition. No public static photo URLs.

## Validation before pilot rollout
1. Apply migration to an isolated database and verify the account photo screen loads and saves a JPEG, PNG, WebP, and rejects corrupt, oversized and non-image files; test EXIF orientation and extreme dimensions.
2. Upload and replace a profile photo, ensure new and old posts show the current avatar; inspect storage for old thumbnail cleanup, no public access to originals and safe rollback on failures.
3. Verify native image picker on physical iOS and Android devices (square crop, uploads, saved avatar and fallback); check a photo taken with an HEIC-enabled device.
4. Test a member and an unrelated account cannot retrieve the private avatar URL unless they share permitted content, guardian relationships or conversation access. Revoke a relationship/channel/participant and confirm access is lost.
5. Test duplicate-name searches, keyboard arrows/Enter/Escape, screen reader labels, touch interactions, debounce, result limits and rate limiting, including cross-role account visibility.
6. Test adult-to-adult, staff-to-student, student-to-staff, guardian safeguarding and student-to-student prohibition; try forged recipient IDs and stale Community shortcut links.
7. Verify no message author avatar is substituted for an organization/represented identity if that attribution is enabled in future. This version follows CTSMD's existing user-attributed Community records.
8. Confirm account photos appear in feed, channel, replies, thread list, participants and the mobile app. Check large feeds for acceptable query performance.
9. Confirm existing attachments, recall, archive, notifications, reactions and safeguarding audit still work.
10. Before any deployment, run `php tests/member_profile_identity.php`, `php -l` for changed PHP, mobile `npm run typecheck`, integration tests, isolated MySQL acceptance and an authenticated browser review.

## Known verification and operational limits
- An authenticated staging/database run, device testing, image security penetration tests and end-to-end browser acceptance have not been performed as part of this repository-only pass.
- The GitHub Actions PHP safety workflow contains an unrelated existing `RegistrationOperationsExperience` baseline-contract failure.
- The Expo workflow currently reports dependency compatibility drift (`expo install --check`), separate from avatar functionality.
- No email, push message, app-store submission, website deployment or production schema migration was performed.
