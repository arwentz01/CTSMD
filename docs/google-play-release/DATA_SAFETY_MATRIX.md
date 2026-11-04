# Data inventory and disclosure review

Evidence describes capability; it does not establish what is collected on every device, shared with providers, encrypted at rest, deleted, or retained. The owner must validate deployment, SDK and provider data flows before any Play Data safety declaration.

| Category | Evidence and purpose | Open questions |
|---|---|---|
| Name, email, phone, address, birth date | `src/PlatformOnboardingExperience.php`, `src/MyAccountExperience.php`; identity, access, contact and eligibility. | Required/optional by route, web handoffs, processor access and retention. |
| Student, guardian and pickup relationships | `src/PlatformOnboardingExperience.php`, `src/FamilyPickupExperience.php`; household access and logistics. | Data-subject rights, guardian verification and deletion exceptions. |
| Messages, posts, attachments, photos/video | `src/CommunicationExperience.php`, `src/CommunityExperience.php`, `src/CommunityAttachmentService.php`, `mobile/src/components/CommunityPhotos.tsx`; communications and production content. | Moderation, reporting/blocking, shared records, provider access and retention. |
| Allergy/accommodation and safety records | `database/migrations/088_intake_support_needs.sql`, `src/PublicExperience.php`, `src/SafeguardingStatementService.php`; optional participation support and safety. | Sensitive-data classifications, access, retention, consent and privacy disclosure. |
| Notification/device tokens | `mobile/src/notifications/device.ts`, `src/MobileApi.php`; optional device registration. Native delivery is marked `native_pending`. | Actual FCM/APNs routing, identifiers, provider sharing and deletion. |
| Credentials and session | `mobile/src/auth/session.ts` uses Expo SecureStore; API client requires HTTPS. | Server/TLS configuration, backups, data-at-rest encryption, logs and production token lifetime. |
| Payment/order data | `src/CommerceShoppingCheckoutService.php`, `src/CommerceStripeProvider.php`; gated test functionality. | Whether any payment integration is actually enabled, and provider/operator roles and fields. |

No evidence of a dedicated analytics, advertising or crash SDK in direct `mobile/package.json` dependencies is not proof that no data is collected by transitive SDKs, host services or linked sites. Do not use it as a final declaration. Data deletion is a request process pending approved operational procedure; no automatic account deletion was established.
