# Late pickup workflow

Migration 084 adds production-scoped late pickup settings, one auditable pickup record per Student and schedule item, review action items, versioned guardian notice drafts, and send-time recipient snapshots.

The authoritative expected release is `schedule_items.ends_at`. Attendance status and `arrived_at` never determine pickup lateness. Actual pickups after release are retained so a later grace-period change can reclassify them; the production count includes only non-reversed records whose `late_minutes` exceed the current grace period.

Crossing the configured threshold opens a staff review and creates a draft. It does not queue email. Staff must edit or review the draft, submit it for approval, approve final content, and explicitly send. Approval and send both require an active Student production membership and a current active guardian with a valid email. The mail worker rechecks the guardian relationship before delivery and suppresses the queue row if access was revoked after queuing.

Corrections update the existing Student/event row and require a reason. Clearing a saved pickup is a documented reversal, never deletion. Each change stores original/new JSON in `late_pickup_occurrence_revisions`. A count falling below threshold supersedes unsent drafts; later crossing creates a fresh draft. Queued notices are retained as immutable send history.

Family and native Action Center readers expose only approved, queued, or sent content and re-evaluate the current family relationship on every read. Staff-only notes, occurrence revisions, and internal resolution reasons are not returned by the mobile API.
