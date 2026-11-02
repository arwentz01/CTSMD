# CTSMD Connect — Expo SDK 58

Native CTSMD Connect is maintained in `mobile/` on `audit/board-pilot-readiness`. The PHP backend remains responsible for authentication, safeguarding, eligibility, and authorization.

## Visual Studio Code setup

Use Node.js 22.13 or later, then open the `mobile` directory in VS Code:

```bash
cd mobile
npm install
npx expo install --fix
npm run typecheck
npx expo-doctor@latest
npx expo start --clear
```

For a simulator or installable development build, use Expo's native development-build workflow (for example `npx expo run:android` or `npx expo run:ios` on macOS with Xcode). Expo Go SDK 58 availability varies by store/platform during the release rollout.

The source does not commit `ios/` or `android/`: native projects are generated for the target SDK during prebuild. Regenerate any old SDK 54 native directories rather than reusing them. If an SDK upgrade fails due to conflicting dependencies, use `npx expo install --fix` and inspect the output, rather than forcing npm peer dependencies.

Backend base URL: `EXPO_PUBLIC_CTSMD_API_BASE_URL=https://ctsmd.me` (HTTPS only; not a secret). Don't put credentials or API secrets into EXPO_PUBLIC values.

## Safety and release gates

See [Phase 4 parity and acceptance evidence](../docs/NATIVE_PHASE4_ACCEPTANCE.md). Run `npm ci`, `npm run typecheck`, `npm run test:integration`, and `npx expo install --check`. CI also exports Android/iOS bundles; those are not device tests. Native notification registration remains `native_pending`: APNs/FCM delivery is not configured or verified. Use Settings to explicitly register/remove this device; do not infer delivery from a successful registration.

This is not yet a released or security-accepted app. Use disposable accounts in staging; do not test on actual student records. Guardian relationship revocation, staff role removal, push delivery, message visibility and active device-session invalidation require end-to-end native acceptance. App-generated requests must use backend authorization, not client role display logic.
