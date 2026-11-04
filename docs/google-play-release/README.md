# CTSMD Connect: Google Play submission preparation

Status: **NOT READY FOR SUBMISSION**. Prepared November 4, 2026 from repository evidence; no production settings or Play Console fields were inspected or changed. The public privacy policy and deletion pages are drafts for CTSMD and legal approval, not approved disclosures. Do not deploy or submit until approved.

Canonical policy for both Play and Apple: https://ctsmd.me/privacy. Proposed public deletion instructions: https://ctsmd.me/account-deletion. Neither is verified deployed. Local unauthenticated GET returned 200 and POST returned 405 for both routes. The existing public contact form is an interim request path; CTSMD must confirm it is monitored, can process privacy requests, and is appropriate for minors before publication.

Read APP_CONTENT_DECLARATIONS.md, DATA_SAFETY_MATRIX.md, PRIVACY_POLICY_REVIEW.md, REVIEWER_ACCESS.md, STORE_LISTING.md, ANDROID_RELEASE_CHECKLIST.md, and OPEN_DECISIONS.md together. Repository behavior is not proof of production configuration or third-party practices.

Verification: PHP syntax checks passed for changed policy/auth/router files; TypeScript and 17 mobile integration tests passed; Expo Doctor reported 20/20 checks. Expo config reports Android package `com.ctsmd_connect_v01`, version `0.1.0`, and no explicit versionCode. No AAB, signing, delivery or Play Console verification was performed. Candidate graphics and the missing screenshot plan are recorded in STORE_LISTING.md.
