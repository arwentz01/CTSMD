# Production allergies and accommodations from public intake

**Status:** Implemented on `audit/board-pilot-readiness` as a web feature; **not yet migration-tested or deployed**.

## Family / participant experience

The public audition, event and interest signup page (`/register?slug=...`) now asks two **optional** questions:

- **Allergies or sensitivities:** any relevant allergy and practical precaution.
- **Accommodations or accessibility needs:** what would make participation safe and inclusive.

Both use free text, max 2,000 characters each. The page explains that responses go to authorized CTSMD staff and discourages unrelated medical history. Blank means **no information provided**, not a declaration that there are no allergies.

Answers are attached to the **specific registration submission**, not to the guardian account, production membership, public cast, or Playbill.

## Authorized staff surfaces

1. **Registration intake detail:** `/admin/registrations/intake?submission=...` shows each submitted answer under its own label. This existing page requires `forms.manage` through its authorization gate.
2. **Production summary:** `/production/support-needs` uses the *selected production context* and displays two sections, allergies and accommodations, with participant name, source opportunity, registration status and a link to that intake. The Production Workspace links to it for qualified staff.

**Access to the production summary explicitly requires both `production.manage` and `forms.manage` permissions.** The service audits access before retrieving sensitive content. All summary responses send private, no-store, noindex and no-referrer headers. It is not intended for parent/student or public access.

The report uses submitted, accepted or waitlisted registrations attached to opportunities for the current production, and excludes cancelled/declined submissions. A person may appear twice if they have multiple applicable registrations; labels identify the source. It does not claim to capture allergies for participants who have never submitted an associated intake.

## Storage

Migration: `database/migrations/088_intake_support_needs.sql` adds nullable `allergy_information` and `accommodation_information` columns to `registration_submissions`.

Do not include these answers in notifications, emails, audit JSON, group messaging, public casts, Playbills, volunteer lists, broad reporting exports or client application caches.

## Operational / safeguarding considerations

- These are **sensitive records about children** and should only be accessed for legitimate operational support.
- No diagnosis is required. Staff should seek clarification through established safeguarded guardian communication rather than inferring severity.
- Consider a later, explicit family-update flow and participant-level carry-forward policy instead of silently reusing the response across shows.
- Once there is a defined data retention policy, implement deletion/restriction accordingly. Current implementation follows registration-submission lifecycle.
- The consolidated page is intentionally read-only and does not imply staff authorization to make medical decisions.

## Staging verification required

- Apply migration 088 after existing numbered migrations; validate fresh and upgrade paths.
- Verify optional blank and filled submissions, Unicode, long text limits, XSS escaping and successful signed-in/guest registration.
- Confirm each intake record displays only that submission's responses.
- Confirm one selected production does not show another production's answers; test archived/non-current productions.
- Confirm both permissions are required in the production summary. Test guardian, student, volunteer, production staff without `forms.manage`, and full authorized staff.
- Confirm request logs, HTML responses and audit metadata never expose sensitive values.
- Verify cancelled/declined submissions disappear from the summary, and active/waitlisted remain distinguishable.
- Verify no caching, indexing, accidental printing, or response leakage on browser back-navigation.
- Verify no public Playbill/casting or native mobile response was expanded to include these fields.
- Review actual staff usability and whether incomplete/all-blank submissions need a follow-up list.
- Run PHP lint, migration and database-backed browser tests prior to production deployment.

**Scope limitation:** This is a production-**intake** support summary, not a canonical health profile for every cast member. A future household-managed persistent profile and explicit per-show confirmation may be useful, but should be designed with consent, access control and minimal retention.
