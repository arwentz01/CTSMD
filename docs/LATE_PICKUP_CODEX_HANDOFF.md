# Codex handoff — Late pickup occurrence tracking
Branch: audit/board-pilot-readiness
Repo: arwentz01/CTSMD

Implement a comprehensive late student pickup workflow in CTSMD Connect. Work in this branch; do not create a new one, and protect concurrent changes. Preserve active-guardian safeguarding and audit requirements. The arrival-time work in attendance was just updated (migration 083, AttendanceService, AttendanceExperience, attendance.css): don't revert it.

## Goal
Staff can record **Late Check-out** on the existing per-schedule-item Attendance screen, entering an actual pickup time, and optionally correcting it later with audit records. Store distinct late-pickup occurrences per student per event. Within each production, staff configure a threshold (e.g., three occurrences) for when a review notice is generated. Crossing a threshold flags the student for staff review and generates a **DRAFT** guardian notification, not an automatic email. Staff can review the occurrence dates and approximate pickup times, add optional case-specific wording, explicitly approve the notice, and then send it to currently authorized parent/guardian recipients using the existing MailService queue. Keep a reliable audit trail.

## Specific requirements
- A dedicated production page, e.g. `/production/late-pickups`, linked from Attendance and production navigation/workspace. Use compact rows with slide-out drawers for reviewing occurrences, settings and draft notices (never modal popups).
- Late checkout is a separate attendance/pickup field, not a new replacement for Present/Late status. A student may arrive on time but be picked up late.
- Pickup threshold for a scheduled event: explicitly define which scheduled end/release time is authoritative and configurable. Late occurrence must be based on a recorded actual pickup timestamp relative to that threshold; support grace minutes configurable per production. Do not use a student's check-in time to decide late pickup.
- Record the occurrence with schedule_item_id, production_id, student_user_id, actual_checkout_at, expected_release_at, late_minutes, recorded_by_user_id, recorded_at, corrected_at, correction_reason and audited original/new values. Support clearing an occurrence only with explicit documented reversal reason, never hard deletion.
- Deduplicate by student/event when appropriate. Repeated edits should not create duplicate occurrences. Do not equate a late mark on attendance with a late pickup.
- Track current production occurrence count based on non-voided records. If reclassification of past occurrences causes threshold crossing (or reversal drops it below threshold), recompute status and handle draft notices safely.
- Allow production managers to configure occurrence threshold and grace period, and to preview what will trigger review. Default conservative settings and never assume organization policy or any actual fee amount.
- At threshold breach, create an **internal staff action item** with student, count, recent dates, time windows and history; do not queue or deliver external communications automatically. Staff should be able to dismiss/resolve with a reason.
- Generate a guardian notice draft: respectful, fact-based, concise, dates and approximate pickup times, rehearsal/release expectations, a neutral notice that continued late pickups **may** have financial repercussions **if permitted under actual CTSMD policy**, with no invented amount or automatic assessment. Template editable by authorized staff, include preview and revision history, and record who approved final content and when.
- Before approval/sending, check **current** active guardians and eligible email addresses. A removed guardian must not receive new messages. Use existing MailService queue with idempotency keys to avoid duplicate sends. Store recipient snapshot at send time, queue IDs/status, content snapshot, approval and audit logs. Staff must explicitly select Send after approval; avoid background sending of unapproved drafts.
- Make audit and content retention distinct from live recipient access. Notification pages should not expose new child information to former guardians.
- Staff can see draft, pending approval, approved, queued/sent, failed and superseded status; clarify that queued does not prove delivered. Use the existing email operations worker and do not bypass mail safety controls.
- Non-staff students/guardians should not see internal occurrence records or sensitive staff notes; communications only through authorized existing family channels.
- Native Expo: expose any student/family-visible *approved* notices through authorized mobile API and Action Center as appropriate; full staff edit workflow can remain web-first and document parity. Never expose private internal notes over mobile API.
- Reuse existing database conventions/migrations; follow migration 083 with next available sequence, but inspect branch before naming. Add tests for access control, threshold counting, reversal/correction, duplicate suppression, guardian revocation, cross-production isolation, approval gating, queue idempotency and late check-out on relevant schedule item.
- Keep work separate from Volunteer Lead design; this feature should not silently confer production management or billing permissions.

## Acceptance criteria
1. Staff can mark a student's checkout after expected release, see a Late Check-out indicator with editable time in Attendance, and revisit/correct it with audit trail.
2. On the production late-pickups page, each student's occurrence count and expanded history show correct dates and recorded pickup times.
3. Threshold crossing alerts staff, creates a review task and guardian message **draft**, and sends **nothing** externally yet.
4. Staff review/edit/approve and explicitly send. Only current authorized guardians are included; prior guardians are excluded.
5. Re-editing an occurrence or clicking Send twice does not create phantom extra occurrences or duplicate recipient sends.
6. Safe behavior for inactive student, closed production, cancelled event, old guardian relationship and unsent stale draft.
7. PHP lint, test suite and mobile builds as applicable; document migrations and test commands with actual results. No blind production deployment.

Treat claims of financial penalties as policy-dependent warnings only. Do not invent CTSMD policy, fees, legal requirements or enforce financial charges in this build.
