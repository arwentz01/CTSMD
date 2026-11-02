# Production Tasks extraction and Volunteer Leads

New route: `/production/tasks`. The Production navigation and workspace link to this page. Readiness now retains its automated indicators and a link to Tasks, but not the task editor.

Migration: `database/migrations/082_production_volunteer_leads.sql`. Apply after 081 and before deploying PHP. The production checklist/assignments remain in their original database tables; no data migration is required.

Tasks retain the current compact cards, expandable per-person completion, assignment drawer, group selection, creation form, and Action Center integration.

### Owner responsibility
Task owners include active staff, administrators, or designated Volunteer Leads with active production memberships. `Manage Volunteer Leads` is in a Tasks slide-out drawer. The designation is production-scoped and does **not** grant administrative permissions. Only authorized production managers can assign or remove the designation. Current lead candidates are active staff and guardian production members (adult identities). Volunteers who are not active adult members of this production must first be linked through the existing member management process.

### Deferred
The Volunteer Lead designation currently controls task owner eligibility and labeling only. Wider volunteer shift leadership powers, qualification checks, training requirements, and delegation to manage volunteers remain for a separate safeguarding/permissions build.

### Verify before deployment
- PHP syntax/lint for new Tasks experience, updated Readiness, AppNavigation, ProductionWorkspace and ProductionReadinessService.
- Migration 082 installation on target MySQL.
- Production header navigation and workspace links.
- Readiness no longer shows full task cards.
- Tasks creation, editing, group selection, completion summary/drawer and Action Center.
- Owners: administrator, staff, designated lead; refuse arbitrary student/guardian owner without lead designation.
- Grant then revoke Volunteer Lead and re-check owner eligibility.
- Volunteer Leads must not inherit production-management permissions.
This was committed through the GitHub connector; no PHP runtime or live database tests have been executed.
