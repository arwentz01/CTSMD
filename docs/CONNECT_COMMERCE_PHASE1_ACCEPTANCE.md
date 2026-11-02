# Connect Commerce — Phase 1 acceptance recheck

Rechecked October 31, 2026 on `audit/board-pilot-readiness`, after recognizing the attached brief is identical to the original Phase 1 request. Existing implementation and later user-approved refinements were retained. No deployment, hosted migration, live payment, customer email or production-account test was performed.

The core reserved-seat sandbox is implemented and backed by real MySQL/HTTP/concurrency tests. Hosted and physical-device readiness are separate from this result.

| Deliverable | Implementation / evidence | Status |
| --- | --- | --- |
| Architecture and ownership | `CommerceAccess`, `CommerceStore`, migrations 101/102; organization-scoped grants and composite FKs; independent module switches | Implemented. Whole-platform unrelated-organization isolation remains explicitly unsupported. |
| Venue/seating designer | `CommerceVenueService`, editor/map; sections/rows, arbitrary/angled positions, zones, wheelchair/companion/view attributes, aisles/spaces/stage and ASL markers | Implemented. Published versions are immutable; clone to change future arrangements. |
| Performance inventory | `CommerceInventoryService`; existing productions, per-performance snapshots, prices, blocks, house and complimentary allocation, lifecycle guards and audit | Implemented. Complimentary allocation and audited zero-value ticket issuance are available; see the subsequent implementation update. |
| Atomic holds | Per-performance transactional lock, expiring owned holds, request hashes, unique inventory, idempotent retries and pending-payment protection | Implemented. Separate-process race produced one hold and one rejection. |
| Customer selection / guest checkout | Circular map, pan/zoom, hover/focus details, seat list, shared ticket-type quantities, zone validation and itemized server review | Implemented. Authenticated preview operator is separate from guest purchaser details; no duplicate audience account is created. |
| Simulated payments | Provider interface, stored scenarios, server-authoritative settlement, duplicate fulfillment protection and audited timeout reconciliation | Implemented. Real providers, credentials and live webhook/settlement infrastructure are not implemented. |
| Tickets / receipt | Cryptographically random QR tokens, one ticket per seat, validity state and escaped `.invalid`-only receipt capture | Implemented. Actual email delivery is deliberately absent. |
| Box office / admission | Restricted order lookup, manual/wedge input, conditional camera scanning, duplicate/wrong-show/revoked/refunded rejection, reversal and attendance | Server/manual workflows implemented and tested. Physical camera/scanner operation is not verified. |
| Administration and permissions | Compact Commerce workspace; hosted application-administrator-only access; local Commerce capabilities; visible setup and pause states | Implemented according to the later hosted-preview request. Local staff/tester grants do not grant hosted access. |
| Verification and limitations | PHP/JS checks, real database and HTTP suites, actual multiprocess races, earlier desktop/tablet/mobile evidence and this recheck | Automated results below passed. No remote CI, deployed site or physical-device pass is asserted here. |

## Fresh automated evidence

All database tests targeted the explicitly disposable loopback schema `ctsmd_rc1_commerce_20261030` with synthetic accounts and fixture cleanup. See [machine-readable results](evidence/connect-commerce-phase1/acceptance-recheck-20261031.json).

| Suite | Fresh result |
| --- | --- |
| Foundation | 10 checks passed |
| Venues | 12 checks passed |
| ASL/stage landmarks | 8 checks passed |
| Inventory | 18 checks passed |
| Hold concurrency | Exactly one winner and one rejection, separate PHP processes/connections |
| Ticket quantities | 16 checks passed |
| Payments / receipt | 34 checks passed |
| Tickets / permissions | 20 checks passed, including simultaneous scan rejection |
| Hosted permissions | 10 checks passed |
| Hosted HTTP, production configuration | 12 checks passed |
| Hosted HTTP, copied-local configuration | 12 checks passed |
| Commerce HTTP / security | 26 checks passed |
| Geometry | Angles, rigid rotation, bounds rejection, unchanged originals and marker-aware fit passed |
| Apache camera policy | 12 real Apache HTTP cases passed after the targeted fix |

PHP syntax and browser-JS syntax were checked. Existing PHP safety tests were rerun. Earlier rendered desktop/tablet/mobile screenshots and keyboard workflow evidence are linked from [the implementation/runbook](CONNECT_COMMERCE.md); this recheck did not repeat an exhaustive visual or assistive-technology audit.

## Concrete fix from this recheck

The global Apache policy denied cameras on every route, so supported browsers would still be blocked from scanning on the hosted Box Office page. The targeted `.htaccess` override permits same-origin camera access only on the check-in page. All other routes retain camera denial, and microphone/geolocation remain denied. Actual Apache testing covers front-controller rewrites, subdirectory paths, query ordering and lookalike-route rejection. Browser consent and application authorization remain required. No camera permission was granted or real camera scan claimed during this run.

## Remaining acceptance boundaries

- Actual ctsmd.me file/schema state and administrator entry are not verified by these local tests. The user deploys by FTP; SQL imports are a separate reviewed hosting operation. No production migration was run.
- Physical iOS/Android camera behavior, camera-denied behavior on actual devices, hardware scanners and screen-reader usability require direct testing. A hosting-level policy may also override the application header.
- Basic adjacent-seat recommendation is implemented as a non-reserving geometric suggestion. Actual venue plans and sightlines still require operator verification.
- Complimentary allocation and issuance are implemented. Partial refunds and exchanges remain outside the current sandbox scope.
- Merchandise and paid education have independent configuration/data boundaries, not completed selling/enrollment workflows. Reserved-seat ticketing is the implemented Phase 1 priority.
- Real payments, public guest entry, production email fulfillment, financial/tax policy, settlement/recovery under load and full multi-organization isolation remain outside sandbox acceptance.
- Offline multi-device check-in is unsupported. Every admission requires server verification.

This is evidence of a working simulated core and explicit remaining limits, not authorization for public sales or live financial processing.

## Subsequent completion: complimentary admissions

Dedicated complimentary issuance is now implemented, with 28 service/concurrency checks and 37 HTTP/security checks. It uses the same inventory lock and ticket fulfillment as payment settlement, requires a distinct staff capability and reason, and creates no provider attempt. Cancellation/check-in retain the existing safeguards. This closes the complimentary-issuance gap identified above; [implementation details and screenshots](CONNECT_COMMERCE.md#complimentary-admission-issuance).

## Subsequent completion: neighboring-seat suggestions

The optional suggestion helper is implemented with 20 domain checks; the HTTP/security suite now has 42 assertions. Suggestions respect inventory, row/zone boundaries, aisles, large gaps and accessible positions, and do not reserve seats. Atomic checkout remains authoritative. Closed/archived productions now show read-only seat controls. [Implementation details and latest screenshots](CONNECT_COMMERCE.md#neighboring-seat-suggestions).
