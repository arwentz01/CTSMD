# Temporary closed-test update indicator

CTSMD Connect Android builds starting with the build containing the update
indicator check `https://ctsmd.me/mobile-release.json` for the latest
**available** closed-testing release.

The header shows a gold cloud-download icon only when:
- running on installed Android (not Expo Go);
- the manifest has `track: "closed_testing"` and `available: true`;
- the manifest's integer `versionCode` is **greater** than the native Android
  `versionCode` installed on the device.

Tapping the icon opens the CTSMD Connect Google Play app listing. Only users
enrolled in the closed test can access the test release. The app does not attempt
to download or install APKs, or interrupt normal navigation if offline.

## Publishing the next closed-test build

1. Increase `expo.android.versionCode` in `mobile/app.json` for each Play
   submission. Set `expo.version` to the user-facing version.
2. Build the AAB and upload it to the CTSMD closed-testing track.
3. **After** Google Play confirms the release is available to enrolled testers,
   update the root `mobile-release.json`:
   - `android.version`: the version actually available;
   - `android.versionCode`: the build actually available;
   - `android.available`: `true`.
4. Deploy `mobile-release.json` to the CTSMD.me web root so that
   `https://ctsmd.me/mobile-release.json` responds with that JSON. Confirm its
   contents from an unauthenticated browser before expecting the icon to appear.
5. To disable the temporary indicator, set `available` to `false` and deploy.

The initial manifest deliberately has `available:false` for version 0.2.0
until its Play closed-test rollout is confirmed. This avoids a premature prompt.
The app rechecks periodically and when brought to the foreground.

**Important:** The original 0.1.0 binary does not include this code and cannot
gain a header icon retroactively. The indicator only works after the version
that contains it has been installed; it can then announce later test releases.

Android-only for now. An iOS TestFlight update link and release feed should be
handled separately if iOS closed testing becomes part of the rollout.
