# Play Console app content: recommendations for review

Do not submit these as declarations without owner confirmation of deployed behavior and current Play Console questions. Sources: [Prepare your app for review](https://support.google.com/googleplay/android-developer/answer/9859455), [User Data](https://support.google.com/googleplay/android-developer/answer/10144311), [Families policy](https://support.google.com/googleplay/android-developer/answer/9893335), [Account deletion](https://support.google.com/googleplay/android-developer/answer/13327111).

| Section | Current recommendation / evidence | Owner decision or blocker |
|---|---|---|
| Privacy policy | Intended canonical URL `https://ctsmd.me/privacy`; public draft in `src/PrivacyExperience.php`. | Approve final policy, effective date, contact, provider and retention disclosures; deploy only after approval and verify externally. |
| App access | Authentication is required for member areas (`mobile/src/auth/AuthProvider.tsx`, `src/MobileApi.php`). | Supply a dedicated synthetic review account, valid steps, reviewer contact, and enough authorized content; never place credentials in this repository. |
| Ads | No advertising SDK identified in direct dependencies (`mobile/package.json`). | Confirm all transitive SDKs, web content and deployed integrations before answering. |
| Content rating | Messages, community posts, media and videos are present (`src/CommunicationExperience.php`, `src/CommunityExperience.php`, `mobile/app/community/`). | Complete IARC questionnaire from actual behavior; verify reporting/blocking and moderation. Do not predict a rating. |
| Target audience | Students, guardians and adults use role-dependent workflows; children may access accounts (`src/PlatformOnboardingExperience.php`, `src/SafeguardedConversationPolicy.php`). | Owner and counsel must choose accurate age groups and assess Families, child data, SDKs and messaging. Do not infer adult-only or child-directed status. |
| Data safety | See DATA_SAFETY_MATRIX.md. Account, family, communications, media, optional support-needs and device tokens have code evidence. | Map actual mobile/webview data flows, every processor, collection/sharing and required/optional practices. Encryption at rest and retention are unverified. |
| Government app | No government affiliation established by repository. | Organization must confirm affiliation and any official-government functions. |
| Financial features | Ticketing and payment test code exists; native links externally (`mobile/app/(tabs)/theatre.tsx`, `src/CommerceStripeProvider.php`). | Confirm deployed payment flows and all financial-feature questions against Console definitions. Do not infer answer from sandbox labels. |
| Health | Optional allergies and accommodations exist in registration (`database/migrations/088_intake_support_needs.sql`, `src/PublicExperience.php`). | Review health declaration definitions, actual collection and audience with counsel; do not default to no. |

No field should be marked complete merely because a route or SDK exists. Privacy URL is the intended value, not ready to submit until the approved page is deployed and verified.
