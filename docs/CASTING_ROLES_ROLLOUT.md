# Structured casting roles rollout

Migration: `database/migrations/079_cast_roles.sql` must run before deploying the new Casting workspace. No database changes have been applied to CTSMD.me from GitHub.

- Casting staff create production-specific role titles, categories, and order.
- Separate Callbacks and Cast lists retain By Student / By Role views.
- Registered students and manual production credits support multi-select role assignments. Saving roles updates the compatibility `role_title` field; for registered cast members with an active roster link, `production_memberships.participation_role` also changes.
- Role changes are **private** until the production team republishes the corresponding callback/cast snapshot. Previously published snapshots remain unchanged.
- Public cast publication groups credits by role and shows first name plus last initial. The authenticated publication continues to use saved snapshot data.
- The Playbill's initial cast profile import uses the latest casting role title when available; existing manually edited/approved Playbill records are not overwritten by casting changes.
- Existing slash-separated roles remain visible, but are *not automatically backfilled* into the new role library. Staff can create roles and reassign credits incrementally.

## Verify before production acceptance
1. Run migration and check indexes/foreign keys on the target MySQL version.
2. Add Scrooge, Minor Character 3, Ensemble; assign two roles to a registered student and a manual cast credit.
3. Test each callback/cast student/role view and distinct status boundaries.
4. Finalize casting and verify roster permissions and guardian safeguarding unchanged.
5. Publish and confirm a child appears under each assigned role; public names remain limited.
6. Verify an old publication does not change until explicitly republished.
7. Import a new Playbill and verify multi-role credit, while preserving manually edited existing Playbill copy.
8. Run PHP lint and project tests. None have been run in this connector-only edit.

**Known follow-up:** The standalone role library currently supports adding/updating names by matching title. Role renaming, archival, custom sorting in published lists, and migration/backfill from historical compound text need dedicated UI and acceptance tests. Do not claim full role administration or production release readiness yet.
