# Per-production To-Do assignments

Migration: `database/migrations/081_checklist_assignments.sql`. Run before uploading updated PHP. The existing single staff owner and aggregate task status remain available for production staff.

## Staff
On Production Readiness, create a checklist item and check multiple eligible people (students, guardians, staff), with **Required for each selected person** enabled by default. Open **Manage assignees / status** for an existing task to adjust assignments and inspect each recipient's Pending/Completed status and timestamp. Completed records also retain the acting user ID. Staff can keep a separate owner for operational oversight.

## Student and guardian
Uncompleted assigned tasks appear under Needs you in Notifications. A guardian sees their own assigned tasks and separate student-assigned tasks labeled with the student's full name. A currently active guardian may mark a student's task complete on their behalf; audit fields distinguish assigned_user_id and completed_by_user_id. Removing a guardian relationship immediately removes that student's items from the former guardian's current list and denies future completion. Production membership and account status are checked at both task rendering and completion.

## Safety and scope
- One task can have multiple independently completed assignments.
- Removing an assignee removes their assignment record, including any previous completion timestamp. Production staff should avoid removing completed assignees unless intended; audit improvement needed for reassignment history.
- Existing checklist-level completion and individual assignment completion are **distinct**. Marking the parent checklist Done does not automatically complete everyone else's task.
- Notification Action Center integration displays tasks dynamically rather than generating broadcast notifications.
- No external email is sent on task assignment in this build.

## Acceptance checklist
1. Run migration in staging; verify constraints and foreign keys.
2. Assign one required task to two students and one parent. Confirm each has a distinct record and the guardian Action Center labels the right student.
3. Complete as student, then as guardian for a different student. Verify independent timestamps and actor IDs.
4. Verify removed guardian cannot view or complete another student's tasks.
5. Verify an inactive production member cannot complete an old task.
6. Edit assignments in drawer, preserve category/title/notes/due date/sort and previous completion for unchanged assignees.
7. Verify mobile drawer layout, focus trap, screen-reader labels, and PHP lint.
8. Confirm production lifecycle blocking and test database migrations. These runtime checks were not performed through GitHub edits.
