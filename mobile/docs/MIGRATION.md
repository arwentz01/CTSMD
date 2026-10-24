# CTSMD native mobile migration

Source migrated from the CTSMD Connect Floot proof of concept. This code is React Native and Expo Router, not a webview wrapper. Web and native applications share the CTSMD PHP backend for authorization.

## Carried forward

- Login/session and typed CTSMD API contracts
- Home, Theatre, Community, Messages, Forms, and Volunteer flows
- Volunteer signup, coordinator approval request/withdrawal, cancellation
- Messaging replies and conversation creation using server-eligible recipient lists
- Tickets and policy links

## Required acceptance before pilot

- Install dependencies, typecheck and run iOS/Android development builds
- Deploy/test required mobile API routes; use disposable accounts
- Validate guardian revocation and conversation restrictions while app is open
- Verify form submission and volunteer operations against migrated staging database
- Implement/verify native notification token transport, OS presentation, deep links and revocation
- Test small screens, keyboard/screen-reader accessibility and error recovery

Do not treat these client-side flows as authorization or assume native release readiness.
