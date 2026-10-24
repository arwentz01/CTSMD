# CTSMD Connect — React Native / Expo

Native CTSMD Connect source lives under `mobile/` on the existing `audit/board-pilot-readiness` branch. The CTSMD PHP backend remains responsible for authentication, safeguarding, role checks and volunteer eligibility.

This is a **migration work in progress**, not a deployed or accepted app. Install Node.js and run `npm install` and `npx expo start` from `mobile/` once remaining screen modules are landed. Never use real family/student data to test the app. Use `EXPO_PUBLIC_CTSMD_API_BASE_URL=https://ctsmd.me` (public origin only, no client secrets).

Native-specific safety: token credentials in Expo SecureStore; no persistent student messaging caches; every server operation must revalidate access. Native push/device testing still pending.
