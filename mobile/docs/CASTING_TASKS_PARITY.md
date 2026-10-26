# Native Expo parity for Connect casting and production tasks

Branch: audit/board-pilot-readiness.

Implemented:
- Mobile API daily-tasks now returns `production_todos` from the web's guardian-aware checklist assignment service and `casting_offers` from the secured casting offer service.
- POST mobile endpoints: `production-todo-complete`, `casting-offer-respond`. These delegate authorization, family membership checks, status transitions, and audit logging to the existing web service classes.
- Native `/action-center` screen permits students and authorized guardians to mark tasks complete or accept/decline casting offers (decline note required).
- Home screen entry to Action Center.
- Production detail's published cast grouped by character/role, including multiple roles per student.

Not yet native:
- Production staff CRUD for casting decisions, multi-select roles, casting publication, or role library.
- Staff Tasks management (creation, assignee/group multi-select, per-person completion drawer).
- Volunteer Lead appointment (currently web-only; broader delegation is awaiting specifications).
- Native push event routing/deep-link testing for these new actions.

Release/deployment prerequisites: apply migrations 079–082 as applicable, deploy the PHP backend first, build the updated Expo client, then conduct actual device tests. Web session cookies are not exchanged for mobile bearer tokens; staff features must use secured mobile API endpoints rather than merely opening authenticated web URLs. No device build or end-to-end test was performed through the GitHub connector.
