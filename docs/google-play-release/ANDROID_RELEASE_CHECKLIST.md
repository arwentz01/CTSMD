# Android release checklist

- `mobile/app.json` package is `com.ctsmd_connect_v01`; verify it against the existing Play listing and generated AAB manifest. Do not change application ID or signing identities without approval.
- `mobile/eas.json` contains an internal APK preview profile and a production Android app-bundle profile. Inspect actual EAS account/project, production variables, signing and Play App Signing; not verified here.
- Version is `0.1.0`; no explicit `versionCode` in app.json. Establish unique monotonic versionCode and reproducible version source before upload.
- Android SDK target must meet current [Google Play requirements](https://support.google.com/googleplay/android-developer/answer/11926878): new apps and updates must target API 36 or higher from August 31, 2026. Verify the built AAB manifest; no AAB was built here.
- Inventory actual merged Android permissions and SDKs, including notification and image selection, from the release build. Verify camera/media behavior rather than inferring all permissions from JS.
- Native notification registration returns `native_pending` (`src/MobileApi.php`); verify Firebase/FCM setup and delivery on a real build. Do not describe registration as working delivery.
- TypeScript and 17 mobile integration tests passed on November 4, 2026. Expo Doctor: 20/20 checks passed. Actual Android build, signing, FCM delivery, deep links, physical-device keyboard and accessibility checks remain required.
- Owner approves privacy/deletion and data safety, obtains sanitized screenshots and review account, creates signed AAB with approved credentials, uploads to internal testing, completes Console app content, then tests install, login, family access, Community, media, notifications and web handoffs. Upload and release require separate explicit authorization.
