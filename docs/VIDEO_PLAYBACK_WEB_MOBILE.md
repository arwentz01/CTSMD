# CTSMD Video Library playback — Web + native Expo

## Status

Implemented in `audit/board-pilot-readiness`, **not deployed or runtime validated**. Bluehost storage and authorization remain the source of truth. Apply existing video migrations 091, then 092 before opening the library.

## Web

The HTML5 video element streams privately using `/videos/play?id=<id>`, supporting HTTP byte ranges for seeking. Native browser controls remain for fullscreen, play/pause and scrubbing.

`assets/js/video-library-player.js` adds:
- 0.5× / 0.75× / 1× / 1.25× / 1.5× playback speeds
- Rewind 10 seconds
- A–B section loop, with a reset control

This is a dependency-free improvement rather than integrating a CDN-hosted Video.js bundle. It avoids an extra external video dependency while using Bluehost's current MP4 range stream. Video.js can be adopted later if media formats, captions/track selection, or accessibility testing require it.

## Native Expo SDK 58

- Add `expo-video` (`~58.0.12`), via `npx expo install expo-video` inside `mobile/`.
- Native navigation: More → Video Library → select video.
- `mobile/app/videos/index.tsx` lists video metadata from `/api/mobile/videos`.
- `mobile/app/videos/[id].tsx` uses `expo-video` to render native controls, fullscreen, playback speeds and rewind.
- Playback loads `/api/mobile/video-play?id=<id>` with `Authorization: Bearer <mobile token>` **in the native video request headers**. No tokens in query strings, video filenames, or browser storage.
- The API checks mobile authentication, current production access, per-group and guardian access, and archived status. Videos are streamed via the same Bluehost filesystem byte-range implementation.
- `useCaching:false` is deliberate: these are potentially sensitive private videos, and revoked materials should not remain playable from the device video cache.

**Lockfile notice:** `mobile/package.json` includes `expo-video`, but `mobile/package-lock.json` has **not yet been regenerated**. The current environment could not access the npm registry to regenerate a verified lockfile. Run `cd mobile && npx expo install expo-video && npm install`, commit the resulting lockfile, then run `npm ci && npm run typecheck && npx expo-doctor` before building the app. Do not claim `npm ci` passes until this is done.

## Required QA

1. Web desktop and mobile Safari/Chrome: pause, seek, speed options, A–B loop, fullscreen.
2. Expo dev build on Android and iOS: bearer-token video request headers with HTTP range seeking, pause, fullscreen, playbackRate.
3. Cross-role access: CTSMD-wide vs selected production vs Production Group, and guardians after relationship removal. Unknown video IDs and expired mobile tokens return errors, never video bytes.
4. Signed-out user, caregiver-only user, disabled/suspended user should not get unrestricted streaming.
5. Confirm `Content-Type: video/mp4`, `Accept-Ranges: bytes`, `206 Partial Content`, and sensible buffering on Bluehost.
6. Confirm streaming does not leak files from `storage/private/` through static URLs. Ensure mobile file access uses token header, not token query parameters.
7. Test MP4/H.264/AAC; MOV and WebM playback depend on target-device codec support.
8. Check no stale CDN caching and monitor Bluehost PHP worker usage. Local video streaming can be expensive on shared hosting.

**Deferred:** adaptive bitrate HLS, offline sync, automatic video transcoding, caption uploads and reliable video analytics.
