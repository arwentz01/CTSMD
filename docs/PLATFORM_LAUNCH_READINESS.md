# CTSMD Connect — Platform launch readiness

Updated 2026-10-26. **Supporting launch-audit reference, not a second maintained To-Do.** All actionable priorities and completions are tracked in [TODO.md](TODO.md). Separate from per-production Readiness or Tasks.

**Evidence rule:** "In code" is not "verified live". Mark production-ready only after acceptance with date, environment, tester, and evidence. This checklist does not supersede the pilot NO-GO in [BOARD_PILOT_READINESS.md](BOARD_PILOT_READINESS.md).

| Work item | Priority | Repository assessment | Next action |
|---|---|---|---|
| Privacy notice: student, guardian, photos, messages, retention and app use | Release gate | Not verified | Legal/policy owner approve published privacy text and placement |
| Terms, acceptable use and guardian consent | Release gate | Not verified | Approve policy and acceptance flow; do not invent legal language |
| Secrets / uploads / frontend exposure | Release gate | Partial protections in .htaccess, server storage service | Inspect deployed Apache rules, direct file URLs, mobile bundles, backup paths, logs |
| HTTPS / TLS / secure cookie behavior | Release gate | Auth uses HTTPS-sensitive Secure cookie; no verified canonical redirect | Test production and proxy, enforce trusted HTTPS redirect/HSTS only after TLS acceptance |
| Child-safeguarding revocation | Release gate | Existing policy services and tests, not reverified in this pass | Re-run former-guardian, cross-production, mobile, cached-link scenarios |
| Server-side form checks / spam and rate limiting | Release gate | Auth and RateLimiter modules exist | Audit every public POST and upload; rate-limit anonymous endpoints and invitations |
| Backups / restore and incident response | Release gate | Incident docs exist | Named owner, backup retention, restore drill, RPO/RTO decisions |
| Native parity, push, deep links | Release gate | Expo source and mobile API exist, incomplete staff parity | Build devices, test Action Center, notification taps, authorization |
| Accessibility: keyboard, alt, contrast, labels | High | Not comprehensively verified | WCAG-focused audit of drawers, schedules, forms, attendance, statuses |
| Mobile web responsiveness and performance | High | Responsive CSS exists; not benchmarked | Test representative Android/iOS/desktop views; network profiling |
| Image optimization and upload limits | High | MIME storage handling exists | Test size limits, downscaling, headshot/sponsor/production art safety |
| Error pages and broken links | High | Routing/fallback exists; no audited 404 | Add branded safe 404; automated link crawl, auth redirects |
| Public metadata / link previews / favicon | Medium | Manifest/icon exists; per-route metadata unverified | Audit public production, playbill and casting pages |
| robots.txt and search boundaries | Medium | Added root robots.txt in this slice | Verify on deployed host; keep private routes auth protected regardless of crawler directives |
| XML sitemap | Medium | Not present | Build only from approved public canonical URLs; omit member/student/private routes |
| Cookie/tracking consent | Conditional | No consent decision yet | Inventory cookies/analytics; implement consent only for applicable tracking |
| Analytics | Conditional | Not verified | Privacy review first; aggregate, minimize student data, retention controls |
| Public calls to action | Medium | Tickets/resources in app | Assess separate public CTSMD website and open-access announcements |

## Immediate implementation in this slice
- Root-level `robots.txt` identifies private application route families as not for crawling. Robots directives **do not authorize or secure anything**.
- Docs navigation and roadmap correction; no claims that compliance, legal approval, or production security acceptance are complete.

## Work queue and sequence
1. **P0:** safeguarding permission matrix, HTTPS/secret file checks, backup recovery, release evidence.
2. **P0:** privacy/terms policy-owner review and publication; app-store privacy claims aligned to actual practices.
3. **P1:** test pass for Expo/native API + forms, uploads, email/push, attendance and guardian access.
4. **P1:** accessibility, performance, broken link and branded error handling.
5. **P2:** public-only sitemap and SEO/social metadata, favicon, image optimization, optional privacy-safe analytics.

Do not automatically create a cookie banner, generic legal copy, speculative sitemap URLs or broad HSTS rollout before the relevant prerequisites.
